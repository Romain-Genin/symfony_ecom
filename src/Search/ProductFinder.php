<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Search;

use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductPage;
use StudioAstra\AstraCheckoutBundle\Repository\ProductRepository;

/** Category browsing stays in SQL (indexed columns); text search goes to the search engine. */
final class ProductFinder
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SearchEngineInterface $engine,
    ) {
    }

    public function find(ProductListCriteria $criteria): ProductPage
    {
        return null !== $criteria->search ? $this->engine->search($criteria) : $this->products->findForListing($criteria);
    }
}
