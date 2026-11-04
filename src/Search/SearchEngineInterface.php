<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Search;

use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductPage;
use StudioAstra\AstraCheckoutBundle\Entity\Product;

/**
 * Full-text product search. Built-in: "database" (no extra service) and "meilisearch"
 * (typo tolerance, large catalogs). Set astra_checkout.search.engine to a service id to plug
 * Elasticsearch, Algolia, Typesense…
 */
interface SearchEngineInterface
{
    /** $criteria->search is not null; filters, sort and pagination must be honoured. */
    public function search(ProductListCriteria $criteria): ProductPage;

    /** @return list<Product> best matches for search-as-you-type */
    public function suggest(string $query, int $limit): array;
}
