<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** <twig:Astra:Breadcrumb :items="[{label: 'Accueil', url: '/'}, {label: 'Sweats', url: null}]" /> + BreadcrumbList JSON-LD. */
#[AsTwigComponent('Astra:Breadcrumb', template: '@AstraCheckout/components/Astra/Breadcrumb.html.twig')]
final class Breadcrumb
{
    /** @var list<array{label: string, url: ?string}> */
    public array $items = [];
}
