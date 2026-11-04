<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** Search field with instant suggestions (ARIA combobox): <twig:Astra:SearchBox id="site-search" inputClass="…" /> */
#[AsTwigComponent('Astra:SearchBox', template: '@AstraCheckout/components/Astra/SearchBox.html.twig')]
final class SearchBox
{
    public string $id = 'astra-search';
    public string $inputClass = '';
    public ?string $value = null;
}
