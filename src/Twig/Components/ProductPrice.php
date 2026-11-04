<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** <twig:Astra:ProductPrice :price="1990" :compareAt="2490" /> — accessible sale price (<ins>/<del>). */
#[AsTwigComponent('Astra:ProductPrice', template: '@AstraCheckout/components/Astra/ProductPrice.html.twig')]
final class ProductPrice
{
    public int $price;
    public ?int $compareAt = null;
    /** Shows "from" (products with several prices). */
    public bool $from = false;
    /** "sm" or "lg" */
    public string $size = 'sm';
}
