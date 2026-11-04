<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Enum;

enum AnnouncementPlacement: string
{
    /** Strip above the header, on every page (rotates when there are several). */
    case TopBar = 'top_bar';
    /** Home page, below the hero. */
    case Home = 'home';
    /** Top of product listings. */
    case Catalog = 'catalog';
    /** Cart page and checkout. */
    case Cart = 'cart';
}
