<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Scheduled announcements: <twig:Astra:Announcements placement="home" /> (inline banners), or the
 * rotating top bar: <twig:Astra:Announcements placement="top_bar" />.
 */
#[AsTwigComponent('Astra:Announcements', template: '@AstraCheckout/components/Astra/Announcements.html.twig')]
final class Announcements
{
    /** top_bar | home | catalog | cart */
    public string $placement = 'home';
}
