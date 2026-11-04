<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Presentation;

use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductPage;
use StudioAstra\AstraCheckoutBundle\Entity\Category;

/** Everything a product listing block displays. */
final class ListingView
{
    /**
     * @param list<Category> $subcategories
     */
    public function __construct(
        public readonly ProductPage $page,
        public readonly ProductListCriteria $criteria,
        public readonly ?Category $category,
        public readonly bool $isSearch,
        public readonly array $subcategories,
    ) {
    }

    /** ETag base for HTTP caching of your listing page. */
    public function fingerprint(): string
    {
        return hash('xxh128', $this->page->fingerprint() . '|' . serialize($this->criteria) . '|' . ($this->category?->getId() ?? 0));
    }
}
