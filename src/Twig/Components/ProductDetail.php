<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Http\PageResponse;
use StudioAstra\AstraCheckoutBundle\Presentation\ProductPresenter;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Complete product page block: breadcrumb, gallery, buy box (price, variants, stock, add to
 * cart, offers), description, related products and JSON-LD.
 *   <twig:Astra:ProductDetail :product="product" />   {# product = ProductPresenter::get($slug) in your controller #}
 *   <twig:Astra:ProductDetail product="{{ slug }}" /> {# answers 404 when missing #}
 * Or compose your own page with ProductGallery, BuyBox and RelatedProducts.
 */
#[AsTwigComponent('Astra:ProductDetail', template: '@AstraCheckout/components/Astra/ProductDetail.html.twig')]
final class ProductDetail
{
    public Product|string|null $product = null;
    public bool $breadcrumb = true;
    public bool $description = true;
    public bool $related = true;
    public bool $jsonLd = true;

    public function __construct(private readonly ProductPresenter $presenter, private readonly PageResponse $page)
    {
    }

    #[PostMount]
    public function load(): void
    {
        if (\is_string($this->product)) {
            $this->product = $this->presenter->find($this->product);
        }
        if (null === $this->product) {
            $this->page->setStatus(404);
        }
    }

    public function getSelectedVariant(): ?\StudioAstra\AstraCheckoutBundle\Entity\ProductVariant
    {
        return $this->product instanceof Product ? $this->presenter->selectedVariant($this->product) : null;
    }
}
