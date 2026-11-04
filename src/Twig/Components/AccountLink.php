<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** "My account" link greeting the customer: <twig:Astra:AccountLink class="nav-link" :icon="false" /> */
#[AsTwigComponent('Astra:AccountLink', template: '@AstraCheckout/components/Astra/AccountLink.html.twig')]
final class AccountLink
{
    public bool $icon = true;
    public bool $greet = true;
    public ?string $label = null;
    public string $labelClass = 'sr-only sm:not-sr-only sm:text-sm sm:font-medium';
}
