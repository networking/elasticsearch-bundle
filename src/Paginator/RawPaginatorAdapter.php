<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 

namespace Networking\ElasticSearchBundle\Paginator;

use Elastica\Query;
use Elastica\SearchableInterface;
use FOS\ElasticaBundle\Paginator\RawPaginatorAdapter as FOSRawPaginatorAdapter;
/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class RawPaginatorAdapter extends FOSRawPaginatorAdapter {

    /**
     * @var SearchableInterface 
     */
    protected $index;

    public function __construct(SearchableInterface $searchable, Query $query, array $options = [])
    {
        $this->index = $searchable;
        parent::__construct($searchable, $query, $options);
    }

    /**
     * Returns the paginated results.
     *
     * @return \FOS\ElasticaBundle\Paginator\PartialResultsInterface
     */
    public function getResults($offset, $itemCountPerPage)
    {
        return new RawResults($this->getElasticaResults($offset, $itemCountPerPage), $this->index);
    }
}
