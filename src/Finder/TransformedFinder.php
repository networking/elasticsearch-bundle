<?php
namespace Networking\ElasticSearchBundle\Finder;

use Elastica\Query;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Finder\TransformedFinder as FOSTransformedFinder;
use Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter;
use Networking\ElasticSearchBundle\Paginator\RawResults;

class TransformedFinder extends FOSTransformedFinder
{
    public function createRawPaginatorAdapter($query, array $options = []): PaginatorAdapterInterface
    {
        $query = Query::create($query);

        return new RawPaginatorAdapter($this->searchable, $query, $options);
    }

    /**
     * @param $query
     */
    public function findRawResultSet($query, ?int $limit = null, array $options = []): RawResults
    {

        $queryObject = Query::create($query);
        if (null !== $limit) {
            $queryObject->setSize($limit);
        }

        return new RawResults($this->searchable->search($queryObject, $options), $this->searchable);
    }
}