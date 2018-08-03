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

use Doctrine\ORM\Query;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Networking\InitCmsBundle\Model\PageSnapshotManagerInterface;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class PageSnapshotProvider implements PagerProviderInterface
{
    /**
     * @var PageSnapshotManagerInterface
     */
    protected $pageSnapshotManager;

    /**
     * PageSnapshotProvider constructor.
     *
     * @param PageSnapshotManagerInterface $pageSnapshotManager
     */
    public function __construct(PageSnapshotManagerInterface $pageSnapshotManager)
    {
        $this->pageSnapshotManager = $pageSnapshotManager;
    }

    public function provide(array $options = [])
    {
        $query = $this->getAllSortByQuery();

        $pager = new PagerfantaPager(new Pagerfanta(new DoctrineORMAdapter($query)));

        return $pager;
    }

    /**
     * @return Query
     */
    public function getAllSortByQuery()
    {
        $qb = $this->pageSnapshotManager->createQueryBuilder('ps');
        $qb2 = $this->pageSnapshotManager->createQueryBuilder('pp');
        $subselect = $qb2->select('MAX(pp.id) as snapshotId')
            ->groupBy('pp.page')
            ->getDQL();
        $qb->select('ps')
            ->where($qb->expr()->in('ps.id', $subselect));

        return $qb->getQuery();
    }
}
