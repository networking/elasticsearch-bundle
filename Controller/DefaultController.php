<?php

namespace Networking\ElasticSearchBundle\Controller;

use Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter;
use Networking\InitCmsBundle\Controller\FrontendPageController;
use Networking\InitCmsBundle\Entity\Page;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Knp\Component\Pager\Paginator;

class DefaultController extends FrontendPageController
{

    /**
     * @Route("/search/", name="site_search")
     * @Template
     */
    public function searchAction()
    {
        /** @var $page Page */
        $page = $this->getRequest()->get('_content');

        if ($page instanceof PageSnapshot) {
            $params = $this->liveAction($this->getRequest());
        } else {
            $params = $this->indexAction($this->getRequest());
        }

        $searchTerm = $this->getRequest()->query->get('search');

        if (!$searchTerm) {
            return array_merge($params,array('paginator' => array(), 'search_term' => $searchTerm));
        }

        /** @var $finder \Elastica_Index */
        $indexName = $this->container->getParameter('elastic_search_index');
        $finder = $this->get('fos_elastica.index.'.$indexName);

        $query = new \Elastica_Query_QueryString($searchTerm);
        $query->setFields(array('name', 'content', 'content.content'));
        $query->setAnalyzeWildcard(true);
        $query->setPhraseSlop(40);
        $query->setUseDisMax(true);

        $query = new \Elastica_Query($query);

        $localeQuery = new \Elastica_Query_Text();
        $localeQuery->setFieldQuery('locale', $this->getRequest()->getLocale());
        $query->setFilter(new \Elastica_Filter_Query($localeQuery));


        /** @var $paginator Paginator */
        $paginator = $this->get('knp_paginator');
        $currentPage = $this->get('request')->query->get('page', 1);

        $paginatorAdaptor = new RawPaginatorAdapter($finder, $query);
        /** @var \Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination $pagePaginator */
        $pagePaginator = $paginator->paginate($paginatorAdaptor, $currentPage);
        $pagePaginator->setParam('search', $searchTerm);
        $pagePaginator->setUsedRoute('site_search_de');
        $pagePaginator->setTemplate('NetworkingElasticSearchBundle:Pagination:twitter_bootstrap_pagination.html.twig');


        $params = array_merge(
            $params,
            array(
                'paginator' => $pagePaginator,
                'search_term' => explode(' ', trim($searchTerm)),
                'url_prefix' =>  $this->get('kernel')->getEnvironment() == 'dev' ? '/app_dev.php' : ''
            )
        );

        return $params;
    }
}
