<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Entity\Product;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** <twig:Astra:ProductCard :product="product" /> — image, badges, name (single link), price. */
#[AsTwigComponent('Astra:ProductCard', template: '@AstraCheckout/components/Astra/ProductCard.html.twig')]
final class ProductCard
{
    public Product $product;
    /** First cards of a page: no lazy loading, high fetch priority (LCP). */
    public bool $eager = false;
    public int $headingLevel = 3;
}
