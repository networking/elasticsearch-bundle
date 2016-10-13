<?php

namespace Networking\ElasticSearchBundle\Controller;

use Elastica\Index;
use Elastica\Query;
use Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter;
use Networking\InitCmsBundle\Controller\FrontendPageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;


class DefaultController extends FrontendPageController
{

    /**
     * @Route("/search/", name="site_search")
     * @Template()
     */
    public function searchAction(Request $request)
    {

        $params = array();
        try{
            $request = $this->getPageHelper()->matchContentRouteRequest($request);
            $params = $this->getPageParameters($request);

        }catch (ResourceNotFoundException $e){

        }

        $template =  $request->get('_template');

        if($template instanceof \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template )
        {
            $template = $template->getTemplate();
        }

        $searchTerm = $request->query->get('search');


        if (!$searchTerm) {
            return array_merge($params);
        }

        if($params instanceof RedirectResponse){
            return $params;
        }

        /** @var $finder Index */
        $indexName = $this->container->getParameter('elastic_search_index');
        $finder = $this->get('fos_elastica.index.' . $indexName);

        $keywordQuery = new Query\QueryString($searchTerm);
        $keywordQuery->setFields(['content', 'name', 'file.content']);
        $keywordQuery->setAnalyzeWildcard(true);
        $keywordQuery->setPhraseSlop(40);
        $keywordQuery->setUseDisMax(true);

        $query = new Query($keywordQuery);

        $localeQuery = new Query\QueryString($request->getLocale());
        $localeQuery->setFields(array('locale'));
        $query->setPostFilter($localeQuery);
        $request->query->set('search', $searchTerm);

        $query->setHighlight(array(
            'fields' => array(
                'file.content' => new \stdClass(),
                'content' => new \stdClass(),
                'name' => new \stdClass()
            )
        ));

        /** @var $paginator Paginator */
        $paginator = $this->get('knp_paginator');
        $currentPage = $this->get('request')->query->get('page', 1);

        $paginatorAdaptor = new RawPaginatorAdapter($finder, $query);
        /** @var \Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination $pagePaginator */
        $pagePaginator = $paginator->paginate($paginatorAdaptor, $currentPage);
        $pagePaginator->setParam('search', $searchTerm);
        $pagePaginator->setUsedRoute('site_search_' . substr($request->getLocale(), 0, 2));
        $pagePaginator->setTemplate('NetworkingElasticSearchBundle:Pagination:twitter_bootstrap_pagination.html.twig');

        $params = array_merge(
            $params,
            array(
                'paginator' => $pagePaginator,
                'search_term' => explode(' ', trim($searchTerm)),
                'url_prefix' => $this->get('kernel')->getEnvironment() == 'dev' ? '/app_dev.php' : ''
            )
        );

        return $this->render($template, $params);
    }


}
