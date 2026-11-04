<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Presentation;

use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Entity\ProductVariant;
use RomainGenin\AstraCheckoutBundle\Pricing\MoneyFormatter;
use RomainGenin\AstraCheckoutBundle\Promotion\PromotionProvider;
use RomainGenin\AstraCheckoutBundle\Repository\ProductRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Product page data: load by slug from your controller, variants for the picker, offers. */
final class ProductPresenter
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly MoneyFormatter $money,
        private readonly ShopSettings $settings,
        private readonly PromotionProvider $promotions,
        private readonly RequestStack $requestStack,
    ) {
    }

    /** For your controller: the enabled product, with variants, images and categories (2 queries). */
    public function find(string $slug): ?Product
    {
        return $this->products->findOneForDisplay($slug);
    }

    /** @throws NotFoundHttpException */
    public function get(string $slug): Product
    {
        return $this->find($slug) ?? throw new NotFoundHttpException(sprintf('Product "%s" not found.', $slug));
    }

    /** Variant requested by ?variant=, otherwise the first available one. */
    public function selectedVariant(Product $product): ?ProductVariant
    {
        $requested = $this->requestStack->getCurrentRequest()?->query->get('variant');
        foreach ($product->getVariants() as $variant) {
            if (null !== $requested && (string) $variant->getId() === (string) $requested) {
                return $variant;
            }
        }

        return $product->getDefaultVariant();
    }

    /** @return list<array<string, mixed>> minimal variant data for the Alpine picker */
    public function variantsForPicker(Product $product): array
    {
        $out = [];
        foreach ($product->getVariants() as $variant) {
            $out[] = [
                'id' => $variant->getId(),
                'sku' => $variant->getSku(),
                'label' => $variant->getLabel(),
                'options' => (object) $variant->getOptions(),
                'price' => $this->money->format($variant->getPrice()),
                'compareAt' => null !== $variant->getCompareAtPrice() && $variant->getCompareAtPrice() > $variant->getPrice() ? $this->money->format($variant->getCompareAtPrice()) : null,
                'available' => $variant->isAvailable(),
                'stock' => $variant->getStock(),
                'low' => $variant->isLowStock($this->settings->getLowStockThreshold()),
            ];
        }

        return $out;
    }

    /** @return list<\RomainGenin\AstraCheckoutBundle\Entity\Promotion> automatic promotions advertised on this product */
    public function promotions(Product $product): array
    {
        return $this->promotions->forProduct($product);
    }

    /** @return list<Product> */
    public function related(Product $product, int $limit = 4): array
    {
        return $this->products->findRelated($product, $limit);
    }

    /** ETag base for HTTP caching of your product page. */
    public function fingerprint(Product $product): string
    {
        return hash('xxh128', $product->getId() . '|' . $product->getUpdatedAt()->format('U.u') . '|' . $this->requestStack->getCurrentRequest()?->query->get('variant'));
    }
}
