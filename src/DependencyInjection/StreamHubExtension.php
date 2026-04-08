<?php

namespace Ustal\StreamHub\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;
use Ustal\StreamHub\Core\Command\ModelCommandBusInterface;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\AppendStreamEventCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\CreateStreamCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\JoinStreamCommandHandler;
use Ustal\StreamHub\Core\Plugins\CoreStream\Command\MarkStreamReadCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\Command\SendMessageCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\Service\MessageEventFactory;

final class StreamHubExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('stream_hub.id_generators', $config['id_generators']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->registerConfiguredIdentifierGenerators($container, $config['id_generators']);

        if (($config['backend_service'] === null) !== ($config['context_service'] === null)) {
            throw new \InvalidArgumentException(sprintf(
                'Both "%s" and "%s" must be configured together to enable the Stream Hub runtime.',
                StreamBackendInterface::class,
                StreamContextInterface::class
            ));
        }

        if ($config['backend_service'] !== null && $config['context_service'] !== null) {
            $container->setParameter('stream_hub.backend_service', $config['backend_service']);
            $container->setParameter('stream_hub.context_service', $config['context_service']);
            $this->registerCoreHandlers($container);
            $this->registerMessageComposerServices($container, $config['id_generators']);
        }
    }

    /**
     * @param array<string, array<string, string>> $configuredGenerators
     */
    private function registerMessageComposerServices(ContainerBuilder $container, array $configuredGenerators): void
    {
        if (!isset($configuredGenerators['message-composer']['event_id'])) {
            return;
        }

        $container->setAlias(
            'stream_hub.identifier_generator.message-composer.event_id',
            new Alias($this->resolveIdentifierGeneratorServiceId($configuredGenerators['message-composer']['event_id']), false)
        );

        $container->setDefinition(MessageEventFactory::class, (new Definition(MessageEventFactory::class))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArguments([
                new Reference('stream_hub.identifier_generator.message-composer.event_id'),
            ]));

        $container->setDefinition(SendMessageCommandHandler::class, (new Definition(SendMessageCommandHandler::class))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArguments([
                new Reference(ModelCommandBusInterface::class),
                new Reference(MessageEventFactory::class),
            ])
            ->addTag('stream_hub.command_handler'));
    }

    private function registerCoreHandlers(ContainerBuilder $container): void
    {
        foreach ([
            CreateStreamCommandHandler::class,
            JoinStreamCommandHandler::class,
            AppendStreamEventCommandHandler::class,
            MarkStreamReadCommandHandler::class,
        ] as $handlerClass) {
            $container->setDefinition($handlerClass, (new Definition($handlerClass))
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('stream_hub.command_handler')
                ->addTag('stream_hub.model_command_handler'));
        }
    }

    /**
     * @param array<string, array<string, string>> $configuredGenerators
     */
    private function registerConfiguredIdentifierGenerators(ContainerBuilder $container, array $configuredGenerators): void
    {
        if (isset($configuredGenerators['message-composer']) && !isset($configuredGenerators['message-composer']['event_id'])) {
            throw new \InvalidArgumentException(
                'Module "message-composer" requires identifier generator "event_id". Configure it under stream_hub.id_generators.message-composer.event_id.'
            );
        }
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
}
