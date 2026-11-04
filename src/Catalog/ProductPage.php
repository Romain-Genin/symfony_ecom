<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Catalog;

use RomainGenin\AstraCheckoutBundle\Entity\Product;

/** One page of a product listing. */
final class ProductPage implements \IteratorAggregate, \Countable
{
    /**
     * @param list<Product> $products
     * @param array{min: int, max: int}|null $priceRange price bounds of the listing without price filters
     */
    public function __construct(
        public readonly array $products,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?array $priceRange = null,
    ) {
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->products);
    }

    public function count(): int
    {
        return \count($this->products);
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function firstIndex(): int
    {
        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastIndex(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    /** Pages to show in the pagination, with null for gaps: [1, null, 4, 5, 6, null, 12]. */
    public function window(int $around = 1): array
    {
        $last = $this->pageCount();
        $pages = [];
        $previous = 0;
        for ($i = 1; $i <= $last; ++$i) {
            if (1 === $i || $last === $i || abs($i - $this->page) <= $around) {
                if ($i - $previous > 1) {
                    $pages[] = null;
                }
                $pages[] = $i;
                $previous = $i;
            }
        }

        return $pages;
    }

    /** Fingerprint for HTTP validation (ETag). */
    public function fingerprint(): string
    {
        $parts = [$this->total, $this->page];
        foreach ($this->products as $p) {
            $parts[] = $p->getId() . ':' . $p->getUpdatedAt()->format('U.u');
        }

        return hash('xxh128', implode('|', $parts));
    }
}
