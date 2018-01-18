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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Elastica\Exception\Bulk\ResponseException as BulkResponseException;
use Networking\ElasticSearchBundle\Component\ObjectPersisterAwareInterface;
use FOS\ElasticaBundle\Provider\ProviderInterface;
use FOS\ElasticaBundle\Exception\InvalidArgumentTypeException;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

    /**
     * @var OptionsResolver
     */
    protected $resolver;

    public function __construct(EntityManager $em, array $options = array())
    {
        $this->em = $em;
        $this->resolver = new OptionsResolver();
        $this->configureOptions();
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
    public function populate(\Closure $loggerClosure = null, array $options = array())
    {
        $options = $this->resolver->resolve($options);
        $queryBuilder = $this->createQueryBuilder();
        $nbObjects = $this->countObjects($queryBuilder);
        $offset = $options['offset'];

        for (; $offset < $nbObjects; $offset += $options['batch_size']) {
            $sliceSize = $options['batch_size'];

            try{
            $objects = $this->fetchSlice($queryBuilder, $this->options['batch_size'], $offset);

                $sliceSize = count($objects);

                if($sliceSize > 0){
                    $this->objectPersister->insertMany($objects);
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
        $repository = $this->em->getRepository('NetworkingInitCmsBundle:Media');
        $qb = $repository->createQueryBuilder('a');
        $qb->where('a.providerName = :provider_name');
        $qb->setParameter(':provider_name', 'sonata.media.provider.file');
        return $qb;

    }


    /**
     * Configures the option resolver.
     */
    protected function configureOptions()
    {
        $this->resolver->setDefaults(array(
            'reset' => true,
            'batch_size' => 100,
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