<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Presentation;

use RomainGenin\AstraCheckoutBundle\Catalog\ProductListCriteria;
use RomainGenin\AstraCheckoutBundle\Catalog\ProductPage;
use RomainGenin\AstraCheckoutBundle\Entity\Category;

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
