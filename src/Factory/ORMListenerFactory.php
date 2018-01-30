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


use Elastica\Index;
use Elastica\Type;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Logger\ElasticaLogger;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use FOS\ElasticaBundle\Provider\Indexable;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use JMS\Serializer\SerializerInterface;
use Networking\ElasticSearchBundle\Transformer\MediaToElasticaTransformer;
use Networking\ElasticSearchBundle\Transformer\PageSnapshotToElasticaTransformer;
use Networking\ElasticSearchBundle\EventListener\Listener;
use Networking\InitCmsBundle\Entity\Media;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\Routing\RouterInterface;

class ORMListenerFactory
{
    /**
     * @var Index
     */
    protected $index;

    /**
     * @var
     */
    protected $configManager;

    /**
     * @var Indexable
     */
    protected $indexable;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var string;
     */
    protected $indexName;

    /**
     * @var \FOS\ElasticaBundle\Logger\ElasticaLogger|null|object
     */
    protected $logger = null;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Pool
     */
    protected $pool;

    /**
     * @var string
     */
    protected $webDir;


    /**
     * ORMListenerFactory constructor.
     * @param Index $index
     * @param ConfigManager $configManager
     * @param Indexable $indexable
     * @param SerializerInterface $serializer
     * @param ElasticaLogger $elasticaLogger
     * @param RouterInterface $router
     * @param Pool $pool
     * @param $indexName
     * @param $webDir
     */
    public function __construct(Index $index, ConfigManager $configManager, Indexable $indexable, SerializerInterface $serializer, ElasticaLogger $elasticaLogger, RouterInterface $router, Pool $pool, $indexName, $webDir)
    {
        $this->index = $index;
        $this->configManager = $configManager;
        $this->indexable = $indexable;
        $this->serializer = $serializer;
        $this->indexName = $indexName;
        $this->logger =  $elasticaLogger;
        $this->router = $router;
        $this->webDir = $webDir.'/../web';
        $this->pool = $pool;
    }

    /**
     * @return Listener
     * @throws \Exception
     */
    public function createPageListener()
    {

        $transformer = new PageSnapshotToElasticaTransformer($this->serializer);

        $persister = $this->createPersister($transformer, 'page', PageSnapshot::class);

        $config = [
            'identifier' => 'id',
            'indexName' => $this->indexName,
            'typeName' => 'page',
        ];

        return new Listener($persister, $this->indexable, $config, $this->logger);
    }

    /**
     * @return Listener
     * @throws \Exception
     */
    public function createMediaListener()
    {
        $transformer = new MediaToElasticaTransformer($this->router, $this->pool, $this->webDir);

        $persister = $this->createPersister($transformer, 'media', Media::class);

        $config = [
            'identifier' => 'id',
            'indexName' => $this->indexName,
            'typeName' => 'media',
        ];

        $listener = new Listener($persister, $this->indexable, $config, $this->logger);
        $listener->setIsMedia(true);

        return $listener;
    }

    /**
     * @param ModelToElasticaTransformerInterface $transformer
     * @param $typeName
     * @param $className
     * @return ObjectPersister
     * @throws \Exception
     */
    protected function createPersister(ModelToElasticaTransformerInterface $transformer, $typeName, $className ){

        /** @var Type $type */
        $type = $this->index->getType($typeName);

        $fields = $this->getFields($typeName);

        return new ObjectPersister($type, $transformer, $className, $fields);
    }

    /**
     * @param string $typeName
     * @return mixed
     */
    protected function getFields($typeName)
    {
        $typeConfig = $this->configManager->getTypeConfiguration('app', $typeName)->getMapping();

        return $typeConfig['properties'];
    }
}