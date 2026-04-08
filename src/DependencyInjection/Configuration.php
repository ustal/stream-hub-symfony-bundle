<?php

namespace Ustal\StreamHub\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('stream_hub');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('backend_service')->defaultNull()->end()
                ->scalarNode('context_service')->defaultNull()->end()
                ->arrayNode('id_generators')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('plugin')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->scalarPrototype()->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
