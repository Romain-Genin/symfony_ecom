<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Search\Meilisearch;

use Psr\Log\LoggerInterface;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductPage;
use StudioAstra\AstraCheckoutBundle\Repository\ProductRepository;
use StudioAstra\AstraCheckoutBundle\Search\DatabaseSearchEngine;
use StudioAstra\AstraCheckoutBundle\Search\SearchEngineInterface;

/**
 * Meilisearch: typo tolerance ("swaet" finds "sweat"), synonyms, results in a few milliseconds
 * on large catalogs. Meilisearch only returns ids; products are then hydrated from the database
 * (prices and stock always up to date). If Meilisearch is down, the database engine answers.
 */
final class MeilisearchSearchEngine implements SearchEngineInterface
{
    private const SORTS = [
        'price_asc' => ['minPrice:asc'],
        'price_desc' => ['minPrice:desc'],
        'newest' => ['createdAt:desc'],
        'name_asc' => ['nameSort:asc'],
    ];

    public function __construct(
        private readonly MeilisearchIndex $index,
        private readonly ProductRepository $products,
        private readonly DatabaseSearchEngine $fallback,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function search(ProductListCriteria $criteria): ProductPage
    {
        try {
            $result = $this->index->search(array_filter([
                'q' => (string) $criteria->search,
                'filter' => $this->filters($criteria),
                'sort' => self::SORTS[$criteria->sort] ?? null,
                'offset' => $criteria->offset(),
                'limit' => $criteria->perPage,
                'attributesToRetrieve' => ['id'],
                'facets' => ['minPrice'],
            ], static fn ($v) => null !== $v && [] !== $v));
        } catch (MeilisearchException $e) {
            $this->logger?->error('AstraCheckout: Meilisearch search failed, falling back to the database: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return $this->fallback->search($criteria);
        }

        $ids = array_map(static fn (array $hit): int => (int) $hit['id'], $result['hits'] ?? []);
        $stats = $result['facetStats']['minPrice'] ?? null;

        return new ProductPage(
            $this->products->hydrateInOrder($ids),
            (int) ($result['estimatedTotalHits'] ?? $result['totalHits'] ?? \count($ids)),
            $criteria->page,
            $criteria->perPage,
            null === $stats ? null : ['min' => (int) $stats['min'], 'max' => (int) $stats['max']],
        );
    }

    public function suggest(string $query, int $limit): array
    {
        try {
            $result = $this->index->search(['q' => $query, 'limit' => $limit, 'attributesToRetrieve' => ['id']]);
        } catch (MeilisearchException $e) {
            $this->logger?->error('AstraCheckout: Meilisearch suggest failed, falling back to the database: {message}', ['message' => $e->getMessage()]);

            return $this->fallback->suggest($query, $limit);
        }

        return $this->products->hydrateInOrder(array_map(static fn (array $hit): int => (int) $hit['id'], $result['hits'] ?? []));
    }

    /** @return list<string> */
    private function filters(ProductListCriteria $c): array
    {
        $filters = [];
        if ([] !== $c->categoryIds) {
            $filters[] = 'categoryIds IN [' . implode(', ', array_map('intval', $c->categoryIds)) . ']';
        }
        if (null !== $c->minPrice) {
            $filters[] = 'minPrice >= ' . $c->minPrice;
        }
        if (null !== $c->maxPrice) {
            $filters[] = 'minPrice <= ' . $c->maxPrice;
        }
        if ($c->inStockOnly) {
            $filters[] = 'available = 1';
        }
        if ($c->onSaleOnly) {
            $filters[] = 'onSale = 1';
        }

        return $filters;
    }
}
