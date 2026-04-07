<?php

namespace Ustal\StreamHub\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Ustal\StreamHub\Plugins\DialogScaffold\DialogScaffoldPlugin;
use Ustal\StreamHub\Plugins\SidebarScaffold\SidebarScaffoldPlugin;
use Ustal\StreamHub\Plugins\TwoColumnLayout\TwoColumnLayoutPlugin;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('stream_hub');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('backend_service')->defaultNull()->end()
                ->scalarNode('context_service')->defaultNull()->end()
                ->arrayNode('enabled_plugins')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        TwoColumnLayoutPlugin::class,
                        SidebarScaffoldPlugin::class,
                        DialogScaffoldPlugin::class,
                    ])
                ->end()
                ->arrayNode('root_slots')
                    ->scalarPrototype()->end()
                    ->defaultValue(['main'])
                ->end()
                ->arrayNode('assets')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('public_prefix')->defaultValue('stream-hub')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
