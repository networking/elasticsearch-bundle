<?php

declare(strict_types=1);

/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Networking\ElasticSearchBundle\Provider;

use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Networking\InitCmsBundle\Entity\Media;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class MediaProvider implements PagerProviderInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function provide(array $options = [])
    {
        $query = $this->createQueryBuilder();

        $pager = new PagerfantaPager(new Pagerfanta(new QueryAdapter($query)));

        return $pager;
    }

    /**
     * @see \FOS\ElasticaBundle\Provider\AbstractProvider::createQueryBuilder()
     */
    protected function createQueryBuilder()
    {
        $repository = $this->em->getRepository(Media::class);
        $qb = $repository->createQueryBuilder('a');
//        $qb->where('a.providerName = :provider_name');
//        $qb->setParameter(':provider_name', 'sonata.media.provider.file');

        return $qb->getQuery();
    }
}
