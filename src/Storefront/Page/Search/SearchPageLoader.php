<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\ScoreQuery;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Api\Search\Term\EntityScoreQueryBuilder;
use Shopware\Context\Struct\ShopContext;
use Shopware\Storefront\Bridge\Product\Repository\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Framework\Config\ConfigServiceInterface;

class SearchPageLoader
{
    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var KeywordSearchTermInterpreter
     */
    private $termInterpreter;

    /**
     * @var EntityScoreQueryBuilder
     */
    private $scoreQueryBuilder;

    public function __construct(
        ConfigServiceInterface $configService,
        StorefrontProductRepository $productRepository,
        KeywordSearchTermInterpreter $termInterpreter,
        EntityScoreQueryBuilder $scoreQueryBuilder
    ) {
        $this->configService = $configService;
        $this->productRepository = $productRepository;
        $this->termInterpreter = $termInterpreter;
        $this->scoreQueryBuilder = $scoreQueryBuilder;
    }

    /**
     * @param string      $searchTerm
     * @param Request     $request
     * @param ShopContext $context
     *
     * @return SearchPageStruct
     */
    public function load(string $searchTerm, Request $request, ShopContext $context): SearchPageStruct
    {
        $config = $this->configService->getByShop($context->getShop()->getUuid(), $context->getShop()->getParentUuid());

        $criteria = $this->createCriteria(trim($searchTerm), $request, $context);

        $products = $this->productRepository->search($criteria, $context);

        $listingPageStruct = new SearchPageStruct();
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);
        $listingPageStruct->setProductBoxLayout($config['searchProductBoxLayout']);

        return $listingPageStruct;
    }

    private function createCriteria(
        string $searchTerm,
        Request $request,
        ShopContext $context
    ): Criteria {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('page', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setFetchCount(true);
        $criteria->addFilter(new TermQuery('product.active', 1));

        $pattern = $this->termInterpreter->interpret($searchTerm, $context->getTranslationContext());
        $keywords = $queries = [];
        foreach ($pattern->getTerms() as $term) {
            $queries[] = new ScoreQuery(
                new TermQuery('product.searchKeywords.keyword', $term->getTerm()),
                $term->getScore(),
                'product.searchKeywords.ranking'
            );
            $keywords[] = $term->getTerm();
        }

        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        $criteria->addFilter(new TermsQuery(
            'product.searchKeywords.keyword',
            array_values($keywords)
        ));

        $criteria->addFilter(new TermQuery(
            'product.searchKeywords.shopUuid',
            $context->getShop()->getUuid()
        ));

        $criteria->addFilter(new TermQuery(
            'product.categoryTree',
            $context->getShop()->getCategory()->getUuid()
        ));

        return $criteria;
    }
}
