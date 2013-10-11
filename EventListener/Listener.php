<?php
/**
 * This file is part of the billag package.
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
use FOS\ElasticaBundle\Doctrine\ORM\Listener as ORMListener;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Networking\ElasticSearchBundle\Component\ObjectPersisterAwareInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Sandbox\InitCmsBundle\Entity\News;

class Listener extends ORMListener implements ObjectPersisterAwareInterface
{
    protected $isMedia = false;

    /**
     * @param ObjectPersisterInterface $objectClass
     * @param string $esIdentifierField
     */
    public function __construct($objectClass, array $events, $esIdentifierField = 'id')
    {
        $this->objectClass = $objectClass;
        $this->events = $events;
        $this->esIdentifierField = $esIdentifierField;

        $this->setIsIndexableCallback(array($this, 'isMediaActive'));
    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();

        if ($entity instanceof $this->objectClass && $this->isObjectIndexable($entity)) {

            if ($entity instanceof PageSnapshot) {
                $this->objectPersister->deleteById($entity->getPage()->getId());
            }

            $this->objectPersister->insertOne($entity);
        }
    }

    public function postRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();

        if ($entity instanceof $this->objectClass && $this->isObjectIndexable($entity)) {

            if ($entity instanceof PageSnapshot) {
                $this->objectPersister->deleteById($entity->getPage()->getId());
            }
        }
    }

    public function setIsMedia($isMedia)
    {
        $this->isMedia = $isMedia;
    }

    /**
     * @param ObjectPersisterInterface $objectPersister
     */
    public function setObjectPersister(ObjectPersisterInterface $objectPersister)
    {
        $this->objectPersister = $objectPersister;
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
}