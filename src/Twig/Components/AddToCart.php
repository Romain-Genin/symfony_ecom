<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * "Add to cart" button for any page: <twig:Astra:AddToCart :product="product" class="btn" />.
 * Products with options get a "choose" link to their page instead.
 */
#[AsTwigComponent('Astra:AddToCart', template: '@AstraCheckout/components/Astra/AddToCart.html.twig')]
final class AddToCart
{
    public ?Product $product = null;
    public ?ProductVariant $variant = null;
    public ?string $label = null;

    #[PostMount]
    public function load(): void
    {
        if (null === $this->variant && null !== $this->product && 1 === $this->product->getVariants()->count()) {
            $this->variant = $this->product->getVariants()->first() ?: null;
        }
        $this->product ??= $this->variant?->getProduct();
    }
}
