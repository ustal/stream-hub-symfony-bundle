<?php

namespace Ustal\StreamHub\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;

final class StreamHubExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('stream_hub.assets.public_prefix', $config['assets']['public_prefix']);
        $container->setParameter('stream_hub.enabled_plugins', $config['enabled_plugins']);
        $container->setParameter('stream_hub.root_slots', $config['root_slots']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

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
        }
    }
}
