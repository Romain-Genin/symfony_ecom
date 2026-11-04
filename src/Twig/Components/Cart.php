<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Presentation\CartPresenter;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/** Complete cart: lines, quantities, promo code, free-shipping progress, totals, checkout button. <twig:Astra:Cart /> */
#[AsTwigComponent('Astra:Cart', template: '@AstraCheckout/components/Astra/Cart.html.twig')]
final class Cart
{
    public bool $heading = true;
    public bool $announcements = true;
    /** @var array<string, mixed> */
    public array $view = [];

    public function __construct(private readonly CartPresenter $presenter)
    {
    }

    #[PostMount]
    public function load(): void
    {
        $this->view = $this->presenter->view();
    }
}
