<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Catalog\ProductListCriteria;
use RomainGenin\AstraCheckoutBundle\Entity\Category;
use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Repository\CategoryRepository;
use RomainGenin\AstraCheckoutBundle\Repository\ProductRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * A grid of products for any page of your site (home page, landing pages, blog posts…):
 *   <twig:Astra:ProductGrid category="sweats" limit="4" />
 *   <twig:Astra:ProductGrid onSale sort="price_asc" limit="8" />
 *   <twig:Astra:ProductGrid :products="myProducts" />
 * Two light queries, no variant loaded. Customize a card with {% block card %}.
 */
#[AsTwigComponent('Astra:ProductGrid', template: '@AstraCheckout/components/Astra/ProductGrid.html.twig')]
final class ProductGrid
{
    /** @var iterable<Product>|null */
    public ?iterable $products = null;
    public Category|string|null $category = null;
    public bool $onSale = false;
    public bool $inStock = true;
    /** newest | price_asc | price_desc | name_asc */
    public string $sort = 'newest';
    public int $limit = 8;
    public int $headingLevel = 3;
    /** Number of cards loaded eagerly (above the fold). */
    public int $eager = 0;

    public function __construct(private readonly ProductRepository $repository, private readonly CategoryRepository $categories)
    {
    }

    #[PostMount]
    public function load(): void
    {
        if (null !== $this->products) {
            return;
        }
        $category = \is_string($this->category) ? $this->categories->findOneEnabledBySlug($this->category) : $this->category;
        if (\is_string($this->category) && null === $category) {
            $this->products = [];

            return;
        }
        $criteria = new ProductListCriteria(
            categoryIds: null === $category ? [] : $this->categories->getDescendantIds($category),
            inStockOnly: $this->inStock,
            onSaleOnly: $this->onSale,
            sort: \in_array($this->sort, ProductListCriteria::SORTS, true) ? $this->sort : 'newest',
            perPage: max(1, min(60, $this->limit)),
        );
        $this->products = $this->repository->findForListing($criteria, false)->products;
    }
}
