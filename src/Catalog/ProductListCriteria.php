<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Catalog;

use Symfony\Component\HttpFoundation\Request;

/** Filters / sorting / pagination of a product listing, parsed from the query string. */
final class ProductListCriteria
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc', 'name_asc'];

    /**
     * @param list<int> $categoryIds category and its descendants
     */
    public function __construct(
        public readonly array $categoryIds = [],
        public readonly ?string $search = null,
        public readonly ?int $minPrice = null,
        public readonly ?int $maxPrice = null,
        public readonly bool $inStockOnly = false,
        public readonly bool $onSaleOnly = false,
        public readonly string $sort = 'relevance',
        public readonly int $page = 1,
        public readonly int $perPage = 24,
    ) {
    }

    /**
     * Prices are typed in major units by the visitor ("25" → 2500 cents).
     *
     * @param list<int> $categoryIds
     */
    public static function fromRequest(Request $request, array $categoryIds, int $perPage): self
    {
        $q = $request->query;
        $search = trim(mb_substr((string) $q->get('q', ''), 0, 100));
        $price = static function (mixed $v): ?int {
            if (!is_scalar($v) || '' === trim((string) $v) || !is_numeric(str_replace(',', '.', (string) $v))) {
                return null;
            }

            return max(0, (int) round((float) str_replace(',', '.', (string) $v) * 100));
        };
        $sort = (string) $q->get('sort', '');
        if (!\in_array($sort, self::SORTS, true)) {
            $sort = '' !== $search ? 'relevance' : 'newest';
        }
        $min = $price($q->get('min'));
        $max = $price($q->get('max'));
        if (null !== $min && null !== $max && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return new self(
            categoryIds: $categoryIds,
            search: '' === $search ? null : $search,
            minPrice: $min,
            maxPrice: $max,
            inStockOnly: $q->getBoolean('in_stock'),
            onSaleOnly: $q->getBoolean('on_sale'),
            sort: $sort,
            page: max(1, min(10_000, $q->getInt('page', 1))),
            perPage: $perPage,
        );
    }

    public function hasFilters(): bool
    {
        return null !== $this->minPrice || null !== $this->maxPrice || $this->inStockOnly || $this->onSaleOnly;
    }

    public function withoutPriceFilters(): self
    {
        return new self($this->categoryIds, $this->search, null, null, $this->inStockOnly, $this->onSaleOnly, $this->sort, 1, $this->perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
