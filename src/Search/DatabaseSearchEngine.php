<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Search;

use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductPage;
use StudioAstra\AstraCheckoutBundle\Repository\ProductRepository;

/**
 * Search in SQL on a pre-normalized column (accents, case, plurals, compound words, SKUs,
 * options and categories), ranked by relevance: exact name > name starting with the query >
 * every word in the name > other fields. Fine up to a few tens of thousands of products.
 */
final class DatabaseSearchEngine implements SearchEngineInterface
{
    /**
     * @param array<string, list<string>> $synonyms
     */
    public function __construct(
        private readonly ProductRepository $products,
        private readonly array $synonyms = [],
    ) {
    }

    public function search(ProductListCriteria $criteria): ProductPage
    {
        return $this->products->findForListing($criteria, true, $this->terms((string) $criteria->search));
    }

    public function suggest(string $query, int $limit): array
    {
        $page = $this->products->findForListing(new ProductListCriteria(search: $query, sort: 'relevance', perPage: $limit), false, $this->terms($query));

        return $page->products;
    }

    /** @return array{terms: list<list<string>>, normalized: string} */
    private function terms(string $query): array
    {
        return ['terms' => TextNormalizer::queryTerms($query, $this->synonyms), 'normalized' => TextNormalizer::normalize($query)];
    }
}
