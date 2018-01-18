<?php
/**
 * This file is part of the forel-2016  package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Networking\ElasticSearchBundle\Factory;


use Elastica\Type;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use FOS\ElasticaBundle\Provider\Indexable;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use Networking\ElasticSearchBundle\Transformer\MediaToElasticaTransformer;
use Networking\ElasticSearchBundle\Transformer\PageSnapshotToElasticaTransformer;
use Symfony\Component\DependencyInjection\Container;
use Networking\ElasticSearchBundle\EventListener\Listener;

class ORMListenerFactory
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Indexable
     */
    protected $indexable;

    /**
     * @var string;
     */
    protected $indexName;

    /**
     * @var \FOS\ElasticaBundle\Logger\ElasticaLogger|null|object
     */
    protected $logger = null;

    /**
     * ORMListenerFactory constructor.
     * @param Container $container
     * @param Indexable $indexable
     */
    public function __construct(Container $container, Indexable $indexable)
    {
        $this->container = $container;
        $this->indexable = $indexable;
        $this->indexName = $container->getParameter('elastic_search_index');
        if($this->container->has('fos_elastica.logger')){
            $this->logger =  $this->container->get('fos_elastica.logger');
        }
    }

    /**
     * @return Listener
     */
    public function createPageListener()
    {

        $transformer = new PageSnapshotToElasticaTransformer($this->container->get('serializer'));

        $persister = $this->createPersister($transformer, 'page', 'Networking\InitCmsBundle\Entity\PageSnapshot');

        $config = array(
            'identifier' => 'id',
            'indexName' => $this->indexName,
            'typeName' => 'page',
        );

        return new Listener($persister, $this->indexable, $config, $this->logger);
    }

    /**
     * @return Listener
     */
    public function createMediaListener()
    {
        $transformer = new MediaToElasticaTransformer($this->container);

        $persister = $this->createPersister($transformer, 'media', 'Networking\InitCmsBundle\Entity\Media');

        $config = array(
            'identifier' => 'id',
            'indexName' => $this->indexName,
            'typeName' => 'media',
        );

        $listener = new Listener($persister, $this->indexable, $config, $this->logger);
        $listener->setIsMedia(true);

        return $listener;
    }

    /**
     * @param ModelToElasticaTransformerInterface $transformer
     * @param $typeName
     * @param $className
     * @return ObjectPersister
     */
    protected function createPersister(ModelToElasticaTransformerInterface $transformer, $typeName, $className ){

        /** @var Type $type */
        $type = $this->container->get(sprintf('fos_elastica.index.%s.%s', $this->indexName, $typeName));

        $fields = $this->getFields($typeName);

        return new ObjectPersister($type, $transformer, $className, $fields);
    }

    /**
     * @param string $typeName
     * @return mixed
     */
    protected function getFields($typeName)
    {
        /** @var ConfigManager $config */
        $config = $this->container->get('fos_elastica.config_manager');
        $typeConfig = $config->getTypeConfiguration($this->indexName, $typeName)->getMapping();

        return $typeConfig['properties'];
    }
}