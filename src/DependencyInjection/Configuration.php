<?php

namespace Networking\ElasticSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('networking_elastic_search');
        $rootNode
            ->children()
                ->scalarNode('base_template')
                    ->defaultValue('@ApplicationNetworkingInitCms/layout.html.twig')
                ->end()
                ->scalarNode('search_template')
                    ->defaultValue('@NetworkingElasticSearch/Default/search.html.twig')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
