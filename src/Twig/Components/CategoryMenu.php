<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Category links for your navigation (cached tree): <twig:Astra:CategoryMenu class="flex gap-4" linkClass="nav-link" />.
 * depth="2" adds a disclosure sub-menu per category.
 */
#[AsTwigComponent('Astra:CategoryMenu', template: '@AstraCheckout/components/Astra/CategoryMenu.html.twig')]
final class CategoryMenu
{
    public int $depth = 1;
    public string $linkClass = '';
    public string $label = '';
}
