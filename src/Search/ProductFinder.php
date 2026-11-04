<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Search;

use RomainGenin\AstraCheckoutBundle\Catalog\ProductListCriteria;
use RomainGenin\AstraCheckoutBundle\Catalog\ProductPage;
use RomainGenin\AstraCheckoutBundle\Repository\ProductRepository;

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
