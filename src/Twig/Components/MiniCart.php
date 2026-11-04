<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** Mini-cart drawer (added automatically to pages containing shop blocks). */
#[AsTwigComponent('Astra:MiniCart', template: '@AstraCheckout/components/Astra/MiniCart.html.twig')]
final class MiniCart
{
}
