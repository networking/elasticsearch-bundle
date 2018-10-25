<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\EventListener;


/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
use FOS\ElasticaBundle\Doctrine\Listener as ORMListener;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Symfony\Component\DependencyInjection\ContainerInterface;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Psr\Log\LoggerInterface;

class Listener extends ORMListener
{
    protected $isMedia = false;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var IndexableInterface
     */
    private $indexable;

    /**
     * Configuration for the listener.
     *
     * @var array
     */
    private $config;


    /**
     * Constructor.
     *
     * @param ObjectPersisterInterface $objectPersister
     * @param IndexableInterface       $indexable
     * @param array                    $config
     * @param LoggerInterface          $logger
     */
    public function __construct(
        ObjectPersisterInterface $objectPersister,
        IndexableInterface $indexable,
        array $config = array(),
        LoggerInterface $logger = null
    )
    {
        $this->indexable = $indexable;
        $this->config = $config;
        parent::__construct($objectPersister, $indexable, $config, $logger);

    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->objectPersister->handlesObject($entity) && $this->isObjectIndexable($entity)) {

            if ($entity instanceof PageSnapshot) {
                $this->objectPersister->deleteById($entity->getPage()->getId());
                $this->objectPersister->insertOne($entity);
            } else {
                $this->objectPersister->insertOne($entity);
            }


        }
    }

	public function postUpdate(LifecycleEventArgs $eventArgs){
		$entity = $eventArgs->getObject();

		if ($this->objectPersister->handlesObject($entity) && $this->isObjectIndexable($entity)) {

			$this->objectPersister->replaceOne($entity);
		}
	}

    public function postRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->objectPersister->handlesObject($entity) && $this->isObjectIndexable($entity)) {

            if ($entity instanceof PageSnapshot) {
                $this->objectPersister->deleteById($entity->getPage()->getId());
            } elseif ($this->isMedia) {
                $this->objectPersister->deleteById($entity->getId());
            }
        }
    }

    public function setIsMedia($isMedia)
    {
        $this->isMedia = $isMedia;
    }


    public function isMediaActive($object)
    {
        if ($this->isMedia) {
            if ($object->getContentType() == 'application/pdf') {
                return $object->getEnabled();
            } else {
                return false;
            }
        }

        return true;
    }


    /**
     * Checks if the object is indexable or not.
     *
     * @param object $object
     *
     * @return bool
     */
    private function isObjectIndexable($object)
    {
        if($this->isMedia){
            return $this->isMediaActive($object);
        }

        return $this->indexable->isObjectIndexable(
            $this->config['indexName'],
            $this->config['typeName'],
            $object
        );
    }
}
