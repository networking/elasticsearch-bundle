<?php

namespace Networking\ElasticSearchBundle\Transformer;

use FOS\ElasticaBundle\Doctrine\AbstractElasticaToModelTransformer;
use Elastica_Document;
use Doctrine\ORM\Query;
use JMS\Serializer\Serializer;
use Symfony\Component\Form\Util\PropertyPath;

/**
 * Maps Elastica documents with Doctrine objects
 * This mapper assumes an exact match between
 * elastica documents ids and doctrine object ids
 */
class ElasticaToPageSnapshotTransformer extends AbstractElasticaToModelTransformer
{
    protected $serializer;

    public function __construct($registry, $objectClass, Serializer $serializer, array $options = array())
    {
        $this->registry = $registry;
        $this->objectClass = $objectClass;


        $this->serializer = $serializer;

    }

    /**
     * Fetch objects for theses identifier values
     *
     * @param array $identifierValues ids values
     * @param Boolean $hydrate whether or not to hydrate the objects, false returns arrays
     * @return array of objects or arrays
     */
    protected function findByIdentifiers(array $identifierValues, $hydrate)
    {
        $pages = array();

        if (empty($identifierValues)) {
            return array();
        }
        $hydrationMode = $hydrate ? Query::HYDRATE_OBJECT : Query::HYDRATE_ARRAY;
        $qb = $this->registry
            ->getManagerForClass($this->objectClass)
            ->getRepository($this->objectClass)
            ->createQueryBuilder('o');
        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb->where($qb->expr()->in('o.' . $this->options['identifier'], ':values'))
            ->setParameter('values', $identifierValues);

        $pageSnapshots = $qb->getQuery()->setHydrationMode($hydrationMode)->execute();

        if (!is_array($pageSnapshots)) {
            return $pageSnapshots;
        }

        foreach ($pageSnapshots as $snapshot) {
            $pages[] = $this->serializer->deserialize(
                $snapshot->getVersionedData(),
                'Networking\InitCmsBundle\Entity\Page',
                'json'
            );
        }

        return $pageSnapshots;
    }


    /**
     * Transforms an array of elastica objects into an array of
     * model objects fetched from the doctrine repository
     *
     * @param array of elastica objects
     * @return array
     **/
    public function transform(array $elasticaObjects)
    {
        $ids = $highlights = array();
        foreach ($elasticaObjects as $elasticaObject) {
            $ids[] = $elasticaObject->getId();
            $highlights[$elasticaObject->getId()] = $elasticaObject->getHighlights();
        }

        $objects = $this->findByIdentifiers($ids, $this->options['hydrate']);
        if (count($objects) < count($elasticaObjects)) {
            throw new \RuntimeException('Cannot find corresponding Doctrine objects for all Elastica results.');
        }
        ;

        foreach ($objects as $object) {
            if ($object instanceof HighlightableModelInterface) {
                $object->setElasticHighlights($highlights[$object->getId()]);
            }
        }

        $identifierProperty = new PropertyPath($this->options['identifier']);

        // sort objects in the order of ids
        $idPos = array_flip($ids);
        usort($objects, function ($a, $b) use ($idPos, $identifierProperty) {
            return $idPos[$identifierProperty->getValue($a)] > $idPos[$identifierProperty->getValue($b)];
        });

        return $objects;
    }
}
