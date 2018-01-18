<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Elastica\Exception\Bulk\ResponseException as BulkResponseException;
use JMS\Serializer\Serializer;
use Networking\ElasticSearchBundle\Component\ObjectPersisterAwareInterface;
use FOS\ElasticaBundle\Provider\ProviderInterface;
use FOS\ElasticaBundle\Exception\InvalidArgumentTypeException;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Networking\InitCmsBundle\Model\PageSnapshot;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class PageSnapshotProvider implements ProviderInterface, ObjectPersisterAwareInterface
{


    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \JMS\Serializer\Serializer
     */
    protected $serializer;

    /**
     * @var \FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
     */
    protected $objectPersister;

    /**
     * @var OptionsResolver
     */
    protected $resolver;

    protected $indexedPages = [];

    public function __construct(EntityManager $em, Serializer $serializer)
    {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->resolver = new OptionsResolver();
        $this->configureOptions();
    }


    public function setObjectPersister(ObjectPersisterInterface $objectPersister)
    {
        $this->objectPersister = $objectPersister;
    }

    /**
     * @param \Closure|null $loggerClosure
     * @param array $options
     */
    public function populate(\Closure $loggerClosure = null, array $options = array())
    {
        $options = $this->resolver->resolve($options);

        $queryBuilder = $this->createQueryBuilder();
        $nbObjects = $this->countPublishedObjects($queryBuilder);
        $allObjects = $this->countAllObjects($queryBuilder);

        $offset = $options['offset'];

        for (; $offset < $allObjects; $offset += $options['batch_size']) {
            $sliceSize = $options['batch_size'];

            try{
                $objects = $this->fetchSlice($queryBuilder, $options['batch_size'], $offset);

                $objects = new ArrayCollection($objects);
                $self = $this;

                $objects = $objects->filter(function (PageSnapshot $pageSnapshot) use($self) {
                    if (in_array($pageSnapshot->getPage()->getId(), $self->indexedPages)) {
                        return false;
                    }
                    $self->indexedPages[] = $pageSnapshot->getPage()->getId();
                    return true;

                });

                $sliceSize = $objects->count();

                if(count($objects->toArray()) > 0){
                    $this->objectPersister->insertMany($objects->toArray());
                }
            } catch (BulkResponseException $e) {
                if (!$options['ignore_errors']) {
                    throw $e;
                }

                if (null !== $loggerClosure) {
                    $loggerClosure(
                        $options['batch_size'],
                        $nbObjects,
                        sprintf('<error>%s</error>', $e->getMessage())
                    );
                }
            }

            if (null !== $loggerClosure) {
                $loggerClosure($sliceSize, $nbObjects);
            }
        }
    }

    /**
     * @see \FOS\ElasticaBundle\Provider\AbstractProvider::countObjects()
     */
    protected function countPublishedObjects($queryBuilder)
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new InvalidArgumentTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
        }

        /* Clone the query builder before altering its field selection and DQL,
         * lest we leave the query builder in a bad state for fetchSlice().
         */
        $qb = clone $queryBuilder;
        $query =  $qb
            ->select('DISTINCT (p.page) as page')
            // Remove ordering for efficiency; it doesn't affect the count
            ->resetDQLPart('orderBy')
            ->getQuery();


        return count($query->getArrayResult());
    }

    protected function countAllObjects($queryBuilder)
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new InvalidArgumentTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
        }

        /* Clone the query builder before altering its field selection and DQL,
         * lest we leave the query builder in a bad state for fetchSlice().
         */
        $rootAliases = $queryBuilder->getRootAliases();

        $rootAlias = $rootAliases[0];
        $qb = clone $queryBuilder;
        return $qb
            ->select($qb->expr()->count($rootAlias))
            // Remove ordering for efficiency; it doesn't affect the count
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

    }

    /**
     * @see \FOS\ElasticaBundle\Provider\AbstractProvider::fetchSlice()
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
     * @see \FOS\ElasticaBundle\Provider\AbstractProvider::createQueryBuilder()
     */
    protected function createQueryBuilder()
    {
        $repository = $this->em->getRepository('NetworkingInitCmsBundle:PageSnapshot');

        $qb = $repository->createQueryBuilder('p')
            ->addOrderBy('p.version', 'DESC');

        return $qb;

    }


    /**
     * Configures the option resolver.
     */
    protected function configureOptions()
    {
        $this->resolver->setDefaults(array(
            'reset' => true,
            'batch_size' => 10,
            'skip_indexable_check' => false,
            'clear_object_manager' => true,
            'debug_logging'        => false,
            'ignore_errors'        => false,
            'offset'               => 0,
            'query_builder_method' => 'createQueryBuilder',
            'sleep'                => 0
        ));
    }
}