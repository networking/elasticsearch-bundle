<?php

namespace Networking\ElasticSearchBundle\Repository;

use Elastica\Query;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Repository;
use Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter;
use Networking\ElasticSearchBundle\Paginator\RawResults;
use Networking\ElasticSearchBundle\Query\BoolQuery;
use Pagerfanta\Pagerfanta;

class SearchRepository extends Repository
{

    public function createQuery($searchTerm, $locale = null)
    {
        $keywordQuery = new Query\QueryString($searchTerm);
        $keywordQuery->setFields(['content'])
            ->setAnalyzeWildcard(true)
            ->setPhraseSlop(40);

        $nameQuery = new Query\QueryString($searchTerm);
        $nameQuery->setFields(['name'])
            ->setAnalyzeWildcard(true)
            ->setPhraseSlop(40)
            ->setBoost(2.0);


        $metaTitle = new Query\QueryString($searchTerm);
        $metaTitle->setFields(['metaTitle'])
            ->setAnalyzeWildcard(true)
            ->setPhraseSlop(40);

        $disMax = new Query\DisMax();
        $disMax->addQuery($nameQuery)
            ->addQuery($keywordQuery)
            ->addQuery($metaTitle)
            ->setTieBreaker(0.3);

        $query = new Query($disMax);


        if($locale){
            $localeQuery = new Query\Match('locale', $locale);

            $missingLocaleQuery =  new Query\Exists('locale');
            $or = new BoolQuery();
            $or->addMustNot( $missingLocaleQuery );

            $booleanQuery = new BoolQuery();
            $booleanQuery->addShould( $localeQuery );
            $booleanQuery->addShould( $or );

            $query->setPostFilter($booleanQuery);
        }

        $query->setHighlight(
            [
                'fields' => [
                    'content' => new \stdClass(),
                    'name' => new \stdClass(),
                ],
            ]
        );

        return $query;
    }
    /**
     * @param string $searchTerm
     * @param int $page
     * @param int $limit
     * @return \Pagerfanta\Pagerfanta|null
     */
    public function findWithPaginator(string $searchTerm, ?string $locale = null)
    {
        if (!$searchTerm) {
            return null;
        }
        $query = $this->createQuery($searchTerm, $locale);

        return $this->findPaginated($query);

        return null;
    }

    public function findRawPaginated(string $searchTerm, ?string $locale = null)
    {
        if (!$searchTerm) {
            return null;
        }
        $query = $this->createQuery($searchTerm, $locale);
        return $this->finder->createRawPaginatorAdapter($query);


    }

    /**
     * @param string $searchTerm
     * @param int $page
     * @param int $limit
     * @return array|null
     */
    public function findRawResults(string $searchTerm, ?string $locale = null): ?ResultSet
    {
        if ($searchTerm) {
            $query = $this->createQuery($searchTerm, $locale);

            $resultSet = $this->finder->findRawResultSet($query);
            return new RawResults($resultSet, $this->index);
        }

        return null;
    }

}