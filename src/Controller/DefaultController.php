<?php

namespace Networking\ElasticSearchBundle\Controller;

use Elastica\Index;
use Elastica\Query;
use FOS\ElasticaBundle\Paginator\RawPaginatorAdapter;
use Networking\InitCmsBundle\Controller\FrontendPageController;
use Knp\Component\Pager\Paginator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class DefaultController extends FrontendPageController
{
    /**
     * @var Index
     */
    private $index;

    /**
     * @var string
     */
    private $baseTemplate;

    /**
     * @var string
     */
    private $searchTemplate;

    /**
     * DefaultController constructor.
     *
     * @param Index $index
     * @param $baseTemplate
     * @param $searchTemplate
     */
    public function __construct(Index $index, $baseTemplate, $searchTemplate)
    {
        $this->index = $index;
        $this->baseTemplate = $baseTemplate;
        $this->searchTemplate = $searchTemplate;
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchAction(Request $request)
    {
        $params = [];
        try {
            $request = $this->getPageHelper()->matchContentRouteRequest($request);
            $params = $this->getPageParameters($request);
        } catch (ResourceNotFoundException $e) {
        }

        $template = $request->get('_template', $this->searchTemplate);

        if ($template instanceof \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template) {
            $template = $template->getTemplate();
        }

        $searchTerm = $request->query->get('search');

        if (!$searchTerm) {
            return array_merge($params);
        }

        if ($params instanceof RedirectResponse) {
            return $params;
        }

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



        $localeQuery = new Query\Match('locale', $request->getLocale());
        $missingLocaleQuery = new Query\Missing('locale');

        $or = new Query\BoolQuery();
	    $or->addShould($localeQuery);
	    $or->addShould($missingLocaleQuery);
        $booleanQuery = new Query\BoolQuery();
        $booleanQuery->addMust($or);


        $query->setPostFilter($booleanQuery);
        $request->query->set('search', $searchTerm);

        $query->setHighlight([
            'fields' => [
                'content' => new \stdClass(),
                'name' => new \stdClass(),
            ],
        ]);

        /** @var $paginator Paginator */
        $paginator = $this->get('knp_paginator');
        $currentPage = $request->query->get('page', 1);

        $paginatorAdaptor = new \Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter($this->index, $query);
        /** @var \Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination $pagePaginator */
        $pagePaginator = $paginator->paginate($paginatorAdaptor, $currentPage);
        $pagePaginator->setParam('search', $searchTerm);
        $pagePaginator->setUsedRoute('site_search_'.substr($request->getLocale(), 0, 2));
        $pagePaginator->setTemplate('@NetworkingElasticSearch/Pagination/twitter_bootstrap_pagination.html.twig');

        $params = array_merge(
            $params,
            [
                'paginator' => $pagePaginator,
                'search_term' => explode(' ', trim($searchTerm)),
                'url_prefix' => $this->get('kernel')->getEnvironment() == 'dev' ? '/app_dev.php' : '',
                'base_template' => $this->baseTemplate,
            ]
        );

        return $this->render($template, $params);
    }
}
