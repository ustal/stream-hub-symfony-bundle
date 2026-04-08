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
use Ustal\StreamHub\Component\Plugin\RequiresIdentifierGeneratorsInterface;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;
use Ustal\StreamHub\Core\Command\ModelCommandBusInterface;
use Ustal\StreamHub\Plugins\MessageComposer\Command\SendMessageCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\MessageComposerPlugin;
use Ustal\StreamHub\Plugins\MessageComposer\Service\MessageEventFactory;

final class StreamHubExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('stream_hub.assets.public_prefix', $config['assets']['public_prefix']);
        $container->setParameter('stream_hub.enabled_plugins', $config['enabled_plugins']);
        $container->setParameter('stream_hub.root_slots', $config['root_slots']);
        $container->setParameter('stream_hub.id_generators', $config['id_generators']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->registerConfiguredIdentifierGenerators($container, $config['enabled_plugins'], $config['id_generators']);

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
            $loader->load('runtime.yaml');
            $this->registerEnabledPluginServices($container, $config['enabled_plugins']);
        }
    }

    /**
     * @param list<class-string> $enabledPlugins
     */
    private function registerEnabledPluginServices(ContainerBuilder $container, array $enabledPlugins): void
    {
        if (in_array(MessageComposerPlugin::class, $enabledPlugins, true)) {
            $container->setDefinition(MessageEventFactory::class, (new Definition(MessageEventFactory::class))
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setArguments([
                    new Reference($this->getPluginIdentifierGeneratorServiceId(MessageComposerPlugin::getName(), 'event_id')),
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
    }

    /**
     * @param list<class-string> $enabledPlugins
     * @param array<string, array<string, string>> $configuredGenerators
     */
    private function registerConfiguredIdentifierGenerators(
        ContainerBuilder $container,
        array $enabledPlugins,
        array $configuredGenerators,
    ): void {
        foreach ($enabledPlugins as $pluginClass) {
            $pluginId = $pluginClass::getName();
            $requirements = $this->getIdentifierGeneratorRequirements($pluginClass);

            if ($requirements === []) {
                continue;
            }

            foreach ($requirements as $key) {
                if (!isset($configuredGenerators[$pluginId][$key]) || $configuredGenerators[$pluginId][$key] === '') {
                    throw new \InvalidArgumentException(sprintf(
                        'Plugin "%s" requires identifier generator "%s". Configure it under stream_hub.id_generators.%s.%s.',
                        $pluginId,
                        $key,
                        $pluginId,
                        $key
                    ));
                }

                $container->setAlias(
                    $this->getPluginIdentifierGeneratorServiceId($pluginId, $key),
                    new Alias($this->resolveIdentifierGeneratorServiceId($configuredGenerators[$pluginId][$key]), false)
                );
            }
        }
    }

    /**
     * @param class-string $pluginClass
     * @return list<string>
     */
    private function getIdentifierGeneratorRequirements(string $pluginClass): array
    {
        if (!is_subclass_of($pluginClass, RequiresIdentifierGeneratorsInterface::class)) {
            return [];
        }

        return $pluginClass::getIdentifierGeneratorRequirements();
    }

    private function getPluginIdentifierGeneratorServiceId(string $pluginId, string $key): string
    {
        return sprintf('stream_hub.identifier_generator.%s.%s', $pluginId, $key);
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
