<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** Cart link + live badge, opens the mini-cart: <twig:Astra:CartButton class="nav-link" /> */
#[AsTwigComponent('Astra:CartButton', template: '@AstraCheckout/components/Astra/CartButton.html.twig')]
final class CartButton
{
    public bool $icon = true;
    public string $labelClass = 'sr-only sm:not-sr-only sm:text-sm sm:font-medium';
    public string $badgeClass = 'absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-accent-700 px-1 text-xs font-bold text-white sm:static';
}
