<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Presentation\ProductPresenter;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/** <twig:Astra:RelatedProducts :product="product" limit="4" /> — same category, in stock. */
#[AsTwigComponent('Astra:RelatedProducts', template: '@AstraCheckout/components/Astra/RelatedProducts.html.twig')]
final class RelatedProducts
{
    public Product $product;
    public int $limit = 4;
    /** @var list<Product> */
    public array $products = [];

    public function __construct(private readonly ProductPresenter $presenter)
    {
    }

    #[PostMount]
    public function load(): void
    {
        $this->products = $this->presenter->related($this->product, $this->limit);
    }
}
