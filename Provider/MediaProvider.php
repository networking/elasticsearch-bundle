<?php
/**
 * This file is part of the billag package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Provider;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Elastica_Type;
use Networking\ElasticSearchBundle\Component\ObjectPersisterAwareInterface;
use FOS\ElasticaBundle\Provider\ProviderInterface;
use FOS\ElasticaBundle\Exception\InvalidArgumentTypeException;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class MediaProvider implements ProviderInterface, ObjectPersisterAwareInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var ObjectPersisterInterface;
     */
    protected $objectPersister;

    /**
     * @var array
     */
    protected $options;

    public function __construct(EntityManager $em, array $options = array())
    {
        $this->em = $em;
        $this->options = array_merge(array(
            'batch_size' => 100,
        ), $options);
    }

    public function setObjectPersister(ObjectPersisterInterface $objectPersister)
    {
        $this->objectPersister = $objectPersister;
    }

    /**
     * Insert the repository objects in the type index
     *
     * @param \Closure $loggerClosure
     */
    public function populate(\Closure $loggerClosure = null)
    {
        $queryBuilder = $this->createQueryBuilder();
        $nbObjects = $this->countObjects($queryBuilder);
        $stepStartTime = 0;
        for ($offset = 0; $offset < $nbObjects; $offset += $this->options['batch_size']) {
            if ($loggerClosure) {
                $stepStartTime = microtime(true);
            }
            $objects = $this->fetchSlice($queryBuilder, $this->options['batch_size'], $offset);

            $this->objectPersister->insertMany($objects);

            if ($loggerClosure) {
                $stepNbObjects = count($objects);
                $stepCount = $stepNbObjects + $offset;
                $percentComplete = 100 * $stepCount / $nbObjects;
                $objectsPerSecond = $stepNbObjects / (microtime(true) - $stepStartTime);
                $loggerClosure(sprintf('%0.1f%% (%d/%d), %d objects/s', $percentComplete, $stepCount, $nbObjects, $objectsPerSecond));
            }
        }
    }

    /**
     * @see FOS\ElasticaBundle\Doctrine\AbstractProvider::countObjects()
     */
    protected function countObjects($queryBuilder)
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new InvalidArgumentTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
        }

        /* Clone the query builder before altering its field selection and DQL,
         * lest we leave the query builder in a bad state for fetchSlice().
         */
        $qb = clone $queryBuilder;
        $rootAliases = $queryBuilder->getRootAliases();

        $rootAlias = $rootAliases[0];
        return $qb
            ->select($qb->expr()->count($rootAlias))
            // Remove ordering for efficiency; it doesn't affect the count
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @see FOS\ElasticaBundle\Doctrine\AbstractProvider::fetchSlice()
     */
    protected function fetchSlice($queryBuilder, $limit, $offset)
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new InvalidArgumentTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
        }

        return $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @see FOS\ElasticaBundle\Doctrine\AbstractProvider::createQueryBuilder()
     */
    protected function createQueryBuilder()
    {
        $repository = $this->em->getRepository('NetworkingInitCmsBundle:Media');
        $qb = $repository->createQueryBuilder('a');
        $qb->where('a.providerName = :provider_name');
        $qb->andWhere('a.enabled = 1');
        $qb->setParameter(':provider_name', 'sonata.media.provider.file');
        return $qb;

    }
}