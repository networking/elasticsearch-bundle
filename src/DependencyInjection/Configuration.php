<?php

namespace Networking\ElasticSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('networking_elastic_search');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('base_template')
                    ->defaultValue('::layout.html.twig')
                ->end()
                ->scalarNode('search_template')
                    ->defaultValue('@NetworkingElasticSearch/Default/search.html.twig')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
