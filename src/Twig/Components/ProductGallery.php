<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Entity\Product;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** <twig:Astra:ProductGallery :product="product" /> — main image + keyboard-friendly thumbnails. */
#[AsTwigComponent('Astra:ProductGallery', template: '@AstraCheckout/components/Astra/ProductGallery.html.twig')]
final class ProductGallery
{
    public Product $product;
    public string $sizes = '(min-width: 1024px) 50vw, 100vw';
}
