<?php

namespace Networking\ElasticSearchBundle\Controller;

use Elastica\Aggregation\Filter;
use Elastica\Index;
use Elastica\Query;
use Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter;
use Networking\InitCmsBundle\Controller\FrontendPageController;
use Networking\InitCmsBundle\Model\Page;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Networking\InitCmsBundle\Model\PageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Knp\Component\Pager\Paginator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;


class DefaultController extends FrontendPageController
{

    /**
     * @Route("/search/", name="site_search")
     * @Template
     */
    public function searchAction(Request $request)
    {
        /** @var $page Page */
        $page = $request->get('_content');

        if ($page instanceof PageSnapshot) {

            /** @var $page PageSnapshot */
            $page = $this->get('serializer')->deserialize(
                $page->getVersionedData(),
                $this->container->getParameter('networking_init_cms.admin.page.class'),
                'json'
            );
        }

        $searchTerm = $request->query->get('search');

        $params = array('paginator' => array(), 'page' => $page, 'admin_pool' => $this->getAdminPool());

        if (!$searchTerm) {
            return array_merge($params);
        }

        //Get Contact Form
//        $params = $this->processForms($params);

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

        return $params;
    }


}
