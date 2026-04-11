<?php

namespace Ustal\StreamHub\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;
use Ustal\StreamHub\Core\Command\CommandBus;
use Ustal\StreamHub\Core\Command\CommandBusInterface;
use Ustal\StreamHub\Core\Command\GuardedCommandBus;
use Ustal\StreamHub\Core\Command\ModelCommandBusInterface;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\AppendStreamEventCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\CreateStreamCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\JoinStreamCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\LeaveStreamCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\MarkStreamReadCommandHandler;
use Ustal\StreamHub\Core\StreamHub;
use Ustal\StreamHub\Core\StreamHubInterface;
use Ustal\StreamHub\Plugins\MessageComposer\Command\SendMessageCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\Service\MessageEventFactory;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\JoinStreamCommandHandler as LifecycleJoinStreamCommandHandler;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\LeaveStreamCommandHandler as LifecycleLeaveStreamCommandHandler;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\StartStreamCommandHandler;
use Ustal\StreamHub\Plugins\StreamLifecycle\Service\LifecycleSystemEventFactory;
use Ustal\StreamHub\SymfonyBundle\Registry\StreamHubRegistry;

final class StreamHubExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('stream_hub.id_generators', $config['id_generators']);

        $instances = $this->normalizeInstances($config);

        foreach ($instances as $name => $instanceConfig) {
            $this->validateRuntimeConfiguration($name, $instanceConfig);
            $this->validateConfiguredIdentifierGenerators($name, $instanceConfig['id_generators']);
            $this->registerInstance($container, $name, $instanceConfig);
        }

        $this->registerRegistry($container, array_keys($instances));
    }

    /**
     * @param array{
     *     backend_service: ?string,
     *     context_service: ?string,
     *     id_generators: array<string, array<string, string>>,
     *     instances: array<string, array{
     *         backend_service: ?string,
     *         context_service: ?string,
     *         id_generators: array<string, array<string, string>>
     *     }>
     * } $config
     * @return array<string, array{
     *     backend_service: ?string,
     *     context_service: ?string,
     *     id_generators: array<string, array<string, string>>
     * }>
     */
    private function normalizeInstances(array $config): array
    {
        $instances = $config['instances'];

        $hasLegacyDefaultConfig = $config['backend_service'] !== null
            || $config['context_service'] !== null
            || $config['id_generators'] !== [];

        if ($hasLegacyDefaultConfig) {
            if (isset($instances['default'])) {
                throw new \InvalidArgumentException('The "default" Stream Hub instance cannot be configured both at the root level and under stream_hub.instances.default.');
            }

            $instances = [
                'default' => [
                    'backend_service' => $config['backend_service'],
                    'context_service' => $config['context_service'],
                    'id_generators' => $config['id_generators'],
                ],
                ...$instances,
            ];
        }

        return $instances;
    }

    /**
     * @param array{
     *     backend_service: ?string,
     *     context_service: ?string,
     *     id_generators: array<string, array<string, string>>
     * } $instanceConfig
     */
    private function validateRuntimeConfiguration(string $instanceName, array $instanceConfig): void
    {
        if (($instanceConfig['backend_service'] === null) !== ($instanceConfig['context_service'] === null)) {
            throw new \InvalidArgumentException(sprintf(
                'Stream Hub instance "%s" must configure both backend_service and context_service together.',
                $instanceName
            ));
        }
    }

    /**
     * @param array<string, array<string, string>> $configuredGenerators
     */
    private function validateConfiguredIdentifierGenerators(string $instanceName, array $configuredGenerators): void
    {
        if (isset($configuredGenerators['message-composer']) && !isset($configuredGenerators['message-composer']['event_id'])) {
            throw new \InvalidArgumentException(sprintf(
                'Stream Hub instance "%s": module "message-composer" requires identifier generator "event_id". Configure it under the default stream_hub.id_generators.message-composer.event_id path or the named stream_hub.instances.%s.id_generators.message-composer.event_id path.',
                $instanceName
                ,
                $instanceName
            ));
        }

        if (isset($configuredGenerators['stream-lifecycle']) && !isset($configuredGenerators['stream-lifecycle']['system_event_id'])) {
            throw new \InvalidArgumentException(sprintf(
                'Stream Hub instance "%s": module "stream-lifecycle" requires identifier generator "system_event_id". Configure it under the default stream_hub.id_generators.stream-lifecycle.system_event_id path or the named stream_hub.instances.%s.id_generators.stream-lifecycle.system_event_id path.',
                $instanceName
                ,
                $instanceName
            ));
        }
    }

    /**
     * @param array{
     *     backend_service: ?string,
     *     context_service: ?string,
     *     id_generators: array<string, array<string, string>>
     * } $instanceConfig
     */
    private function registerInstance(ContainerBuilder $container, string $instanceName, array $instanceConfig): void
    {
        if ($instanceConfig['backend_service'] === null || $instanceConfig['context_service'] === null) {
            return;
        }

        $prefix = $this->instancePrefix($instanceName);
        $commandHandlerTag = $prefix . '.command_handler';
        $modelCommandHandlerTag = $prefix . '.model_command_handler';

        $container->setDefinition($prefix . '.command_bus.inner', (new Definition(CommandBus::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new TaggedIteratorArgument($commandHandlerTag),
            ]));

        $container->setDefinition($prefix . '.command_bus', (new Definition(GuardedCommandBus::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($prefix . '.command_bus.inner'),
                new TaggedIteratorArgument('stream_hub.command_guard'),
            ]));

        $container->setDefinition($prefix . '.model_command_bus', (new Definition(CommandBus::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new TaggedIteratorArgument($modelCommandHandlerTag),
            ]));

        $this->registerCoreHandlers($container, $instanceName, $commandHandlerTag, $modelCommandHandlerTag);
        $this->registerStreamHubFacade($container, $instanceName, $instanceConfig['backend_service'], $instanceConfig['context_service']);
        $this->registerMessageComposerServices($container, $instanceName, $instanceConfig['id_generators'], $commandHandlerTag);
        $this->registerStreamLifecycleServices($container, $instanceName, $instanceConfig['id_generators'], $commandHandlerTag);

        if ($instanceName === 'default') {
            $container->setAlias(CommandBusInterface::class, new Alias($prefix . '.command_bus', false));
            $container->setAlias(ModelCommandBusInterface::class, new Alias($prefix . '.model_command_bus', false));
            $container->setAlias(StreamHubInterface::class, new Alias($prefix . '.stream_hub', false));
            $container->setAlias(StreamHub::class, new Alias($prefix . '.stream_hub', false));
        }
    }

    private function registerRegistry(ContainerBuilder $container, array $instances): void
    {
        $serviceMap = [];

        foreach ($instances as $instanceName) {
            $prefix = $this->instancePrefix($instanceName);

            if ($container->hasDefinition($prefix . '.stream_hub')) {
                $serviceMap[$instanceName] = new Reference($prefix . '.stream_hub');
            }
        }

        $container->setDefinition(StreamHubRegistry::class, (new Definition(StreamHubRegistry::class))
            ->setAutowired(false)
            ->setAutoconfigured(true)
            ->setArguments([
                ServiceLocatorTagPass::register($container, $serviceMap),
                array_keys($serviceMap),
            ]));
    }

    private function registerMessageComposerServices(ContainerBuilder $container, string $instanceName, array $configuredGenerators, string $commandHandlerTag): void
    {
        if (!isset($configuredGenerators['message-composer']['event_id'])) {
            return;
        }

        $prefix = $this->instancePrefix($instanceName);
        $generatorAliasId = $prefix . '.identifier_generator.message-composer.event_id';
        $factoryServiceId = $prefix . '.message_composer.message_event_factory';
        $handlerServiceId = $prefix . '.message_composer.send_message_handler';

        $container->setAlias(
            $generatorAliasId,
            new Alias($this->resolveIdentifierGeneratorServiceId($configuredGenerators['message-composer']['event_id']), false)
        );

        $container->setDefinition($factoryServiceId, (new Definition(MessageEventFactory::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($generatorAliasId),
            ]));

        $container->setDefinition($handlerServiceId, (new Definition(SendMessageCommandHandler::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($prefix . '.model_command_bus'),
                new Reference($factoryServiceId),
            ])
            ->addTag($commandHandlerTag));

        if ($instanceName === 'default') {
            $container->setAlias('stream_hub.identifier_generator.message-composer.event_id', new Alias($generatorAliasId, false));
            $container->setAlias(MessageEventFactory::class, new Alias($factoryServiceId, false));
            $container->setAlias(SendMessageCommandHandler::class, new Alias($handlerServiceId, false));
        }
    }

    private function registerCoreHandlers(ContainerBuilder $container, string $instanceName, string $commandHandlerTag, string $modelCommandHandlerTag): void
    {
        $prefix = $this->instancePrefix($instanceName) . '.core_handler';

        $definitions = [
            'create_stream' => CreateStreamCommandHandler::class,
            'join_stream' => JoinStreamCommandHandler::class,
            'leave_stream' => LeaveStreamCommandHandler::class,
            'append_stream_event' => AppendStreamEventCommandHandler::class,
            'mark_stream_read' => MarkStreamReadCommandHandler::class,
        ];

        foreach ($definitions as $suffix => $handlerClass) {
            $serviceId = $prefix . '.' . $suffix;

            $container->setDefinition($serviceId, (new Definition($handlerClass))
                ->setAutowired(true)
                ->setAutoconfigured(false)
                ->addTag($commandHandlerTag)
                ->addTag($modelCommandHandlerTag));
        }
    }

    private function registerStreamLifecycleServices(ContainerBuilder $container, string $instanceName, array $configuredGenerators, string $commandHandlerTag): void
    {
        if (!isset($configuredGenerators['stream-lifecycle']['system_event_id'])) {
            return;
        }

        $prefix = $this->instancePrefix($instanceName);
        $generatorAliasId = $prefix . '.identifier_generator.stream-lifecycle.system_event_id';
        $streamIdGeneratorAliasId = $prefix . '.identifier_generator.stream-lifecycle.stream_id';
        $factoryServiceId = $prefix . '.stream_lifecycle.lifecycle_system_event_factory';
        $startHandlerServiceId = $prefix . '.stream_lifecycle.start_stream_handler';
        $joinHandlerServiceId = $prefix . '.stream_lifecycle.join_stream_handler';
        $leaveHandlerServiceId = $prefix . '.stream_lifecycle.leave_stream_handler';

        $container->setAlias(
            $generatorAliasId,
            new Alias($this->resolveIdentifierGeneratorServiceId($configuredGenerators['stream-lifecycle']['system_event_id']), false)
        );

        $container->setDefinition($factoryServiceId, (new Definition(LifecycleSystemEventFactory::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($generatorAliasId),
            ]));

        $container->setDefinition($startHandlerServiceId, (new Definition(StartStreamCommandHandler::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($prefix . '.model_command_bus'),
                new Reference($factoryServiceId),
            ])
            ->addTag($commandHandlerTag));

        if (isset($configuredGenerators['stream-lifecycle']['stream_id'])) {
            $container->setAlias(
                $streamIdGeneratorAliasId,
                new Alias($this->resolveIdentifierGeneratorServiceId($configuredGenerators['stream-lifecycle']['stream_id']), false)
            );

            $container->getDefinition($startHandlerServiceId)
                ->addMethodCall('setStreamIdGenerator', [new Reference($streamIdGeneratorAliasId)]);
        }

        $container->setDefinition($joinHandlerServiceId, (new Definition(LifecycleJoinStreamCommandHandler::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($prefix . '.model_command_bus'),
            ])
            ->addTag($commandHandlerTag));

        $container->setDefinition($leaveHandlerServiceId, (new Definition(LifecycleLeaveStreamCommandHandler::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($prefix . '.model_command_bus'),
                new Reference($factoryServiceId),
            ])
            ->addTag($commandHandlerTag));

        if ($instanceName === 'default') {
            $container->setAlias('stream_hub.identifier_generator.stream-lifecycle.system_event_id', new Alias($generatorAliasId, false));
            if (isset($configuredGenerators['stream-lifecycle']['stream_id'])) {
                $container->setAlias('stream_hub.identifier_generator.stream-lifecycle.stream_id', new Alias($streamIdGeneratorAliasId, false));
            }
            $container->setAlias(LifecycleSystemEventFactory::class, new Alias($factoryServiceId, false));
            $container->setAlias(StartStreamCommandHandler::class, new Alias($startHandlerServiceId, false));
            $container->setAlias(LifecycleJoinStreamCommandHandler::class, new Alias($joinHandlerServiceId, false));
            $container->setAlias(LifecycleLeaveStreamCommandHandler::class, new Alias($leaveHandlerServiceId, false));
        }
    }

    private function registerStreamHubFacade(ContainerBuilder $container, string $instanceName, string $backendService, string $contextService): void
    {
        $prefix = $this->instancePrefix($instanceName);

        $container->setDefinition($prefix . '.stream_hub', (new Definition(StreamHub::class))
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference($prefix . '.command_bus'),
                new Reference($backendService),
                new Reference($contextService),
            ]));
    }

    private function resolveIdentifierGeneratorServiceId(string $configuredGenerator): string
    {
        return match ($configuredGenerator) {
            'random_hex' => 'stream_hub.identifier_generator.random_hex',
            'uuid_v4' => 'stream_hub.identifier_generator.uuid_v4',
            'uuid_v7' => 'stream_hub.identifier_generator.uuid_v7',
            default => $configuredGenerator,
        };
    }

    private function instancePrefix(string $instanceName): string
    {
        return 'stream_hub.instance.' . $instanceName;
    }
}
