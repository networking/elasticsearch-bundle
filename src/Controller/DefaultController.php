<?php

namespace Networking\ElasticSearchBundle\Controller;

use Elastica\Aggregation\Missing;
use Elastica\Index;
use Elastica\Query;
use FOS\ElasticaBundle\Paginator\RawPaginatorAdapter;
use Networking\ElasticSearchBundle\Query\BoolQuery;
use Networking\InitCmsBundle\Controller\FrontendPageController;
use Knp\Component\Pager\Paginator;
use Knp\Component\Pager\PaginatorInterface;
use Networking\InitCmsBundle\Helper\LanguageSwitcherHelper;
use Networking\InitCmsBundle\Helper\PageHelper;
use Networking\InitCmsBundle\Cache\PageCacheInterface;
use Networking\InitCmsBundle\Model\PageManagerInterface;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DefaultController extends FrontendPageController
{
    /**
     * @var Index
     */
    protected $index;

    /**
     * @var string
     */
    protected $baseTemplate;

    /**
     * @var string
     */
    protected $searchTemplate;

    /**
     * @var PaginatorInterface
     */
    protected $paginator;

    /**
     * @var string
     */
    protected $environment;

    /**
     * DefaultController constructor.
     * @param PageCacheInterface $pageCache
     * @param TokenStorageInterface $tokenStorage
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param Pool $pool
     * @param LanguageSwitcherHelper $languageSwitcherHelper
     * @param PageManagerInterface $pageManager
     * @param PageHelper $pageHelper
     * @param Index $index
     * @param PaginatorInterface $paginator
     * @param KernelInterface $kernel
     * @param $baseTemplate
     * @param $searchTemplate
     */
    public function __construct(
        PageCacheInterface $pageCache,
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        Pool $pool,
        LanguageSwitcherHelper $languageSwitcherHelper,
        PageManagerInterface $pageManager,
        PageHelper $pageHelper,
        Index $index,
        PaginatorInterface $paginator,
        KernelInterface $kernel,
        $baseTemplate,
        $searchTemplate
    ) {
        parent::__construct(
            $pageCache,
            $tokenStorage,
            $authorizationChecker,
            $pool,
            $languageSwitcherHelper,
            $pageManager,
            $pageHelper
        );
        $this->environment = $kernel->getEnvironment();
        $this->paginator = $paginator;
        $this->pageHelper = $pageHelper;
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

        if ($params instanceof RedirectResponse) {
            return $params;
        }

        $searchTerm = $request->query->get('search', false);

        $pagePaginator = false;

        if ($searchTerm) {

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

            $missingLocaleQuery =  new Query\Exists('locale');
            $or = new BoolQuery();
            $or->addMustNot( $missingLocaleQuery );

            $booleanQuery = new BoolQuery();
            $booleanQuery->addShould( $localeQuery );
            $booleanQuery->addShould( $or );

            $query->setPostFilter($booleanQuery);
            $request->query->set('search', $searchTerm);

            $query->setHighlight(
                [
                    'fields' => [
                        'content' => new \stdClass(),
                        'name' => new \stdClass(),
                    ],
                ]
            );

            $currentPage = $request->query->get('page', 1);

            $paginatorAdaptor = new \Networking\ElasticSearchBundle\Paginator\RawPaginatorAdapter($this->index, $query);
            /** @var \Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination $pagePaginator */
            $pagePaginator = $this->paginator->paginate($paginatorAdaptor, $currentPage);
            $pagePaginator->setParam('search', $searchTerm);
            $pagePaginator->setUsedRoute('site_search_'.substr($request->getLocale(), 0, 2));
            $pagePaginator->setTemplate('@NetworkingElasticSearch/Pagination/twitter_bootstrap_pagination.html.twig');
        }


        $params = array_merge(
            $params,
            [
                'paginator' => $pagePaginator,
                'search_term' => $searchTerm ? explode(' ', trim($searchTerm)) : false,
                'url_prefix' => '',
                'base_template' => $this->baseTemplate,
            ]
        );

        return $this->render($template, $params);
    }
}
