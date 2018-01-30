<?php

namespace Networking\ElasticSearchBundle\DependencyInjection;

use FOS\ElasticaBundle\Persister\ObjectPersister;
use Networking\ElasticSearchBundle\Transformer\MediaToElasticaTransformer;
use Networking\ElasticSearchBundle\Transformer\PageSnapshotToElasticaTransformer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;


/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class NetworkingElasticSearchExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('networking_elastic_search.base_template', $config['base_template']);
        $container->setParameter('networking_elastic_search.search_template', $config['search_template']);

    }
}
