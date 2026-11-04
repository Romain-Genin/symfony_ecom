<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use StudioAstra\AstraCheckoutBundle\Presentation\ProductPresenter;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * <twig:Astra:BuyBox :product="product" /> — brand, name (h1 by default), price, offers, variant
 * picker, stock, quantity and "add to cart". Works without JavaScript.
 */
#[AsTwigComponent('Astra:BuyBox', template: '@AstraCheckout/components/Astra/BuyBox.html.twig')]
final class BuyBox
{
    public Product $product;
    public ?ProductVariant $selectedVariant = null;
    /** Heading level of the product name (1 on a product page). */
    public int $headingLevel = 1;
    public bool $reassurance = true;
    /** @var list<array<string, mixed>> */
    public array $variants = [];
    /** @var list<\StudioAstra\AstraCheckoutBundle\Entity\Promotion> */
    public array $promotions = [];

    public function __construct(private readonly ProductPresenter $presenter)
    {
    }

    #[PostMount]
    public function load(): void
    {
        $this->selectedVariant ??= $this->presenter->selectedVariant($this->product);
        $this->variants = $this->presenter->variantsForPicker($this->product);
        $this->promotions = $this->presenter->promotions($this->product);
    }
}
