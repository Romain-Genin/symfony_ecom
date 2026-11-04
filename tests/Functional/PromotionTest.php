<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Tests\Functional;

use RomainGenin\AstraCheckoutBundle\Cart\CartContext;
use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\CartItem;
use RomainGenin\AstraCheckoutBundle\Entity\Category;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Entity\ProductVariant;
use RomainGenin\AstraCheckoutBundle\Entity\Promotion;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Enum\PromotionType;
use RomainGenin\AstraCheckoutBundle\Order\OrderStateMachine;
use RomainGenin\AstraCheckoutBundle\Pricing\TotalsCalculator;
use RomainGenin\AstraCheckoutBundle\Promotion\PromotionCalculator;

final class PromotionTest extends ShopTestCase
{
    private function promotion(string $name, PromotionType $type, int $value, ?string $code = null, ?callable $configure = null): Promotion
    {
        $promotion = (new Promotion($name, $type, $value))->setCode($code);
        if ($configure) {
            $configure($promotion);
        }
        $this->em()->persist($promotion);
        $this->em()->flush();

        return $promotion;
    }

    /** @param array<string, int> $lines sku => quantity */
    private function cart(array $lines, ?string $coupon = null): Cart
    {
        $cart = new Cart();
        foreach ($lines as $sku => $qty) {
            $variant = $this->em()->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku]);
            $cart->addItem(new CartItem($cart, $variant, $qty));
        }
        $cart->setCouponCode($coupon);

        return $cart;
    }

    private function totals(Cart $cart, ?string $shipping = ''): \RomainGenin\AstraCheckoutBundle\Pricing\Totals
    {
        self::getContainer()->get(\RomainGenin\AstraCheckoutBundle\Promotion\PromotionProvider::class)->reset();

        return self::getContainer()->get(TotalsCalculator::class)->calculate($cart, $shipping);
    }

    public function testAutomaticPercentageOnACategoryDiscountsTheVatBase(): void
    {
        $tshirts = $this->em()->getRepository(Category::class)->findOneBy(['slug' => 'vetements']);
        $this->promotion('Vêtements -10 %', PromotionType::Percentage, 1000, null, fn (Promotion $p) => $p->addCategory($tshirts));

        $totals = $this->totals($this->cart(['TS-S' => 2, 'MUG' => 1])); // 5000 (sub-category of Vêtements) + 1200
        self::assertSame(500, $totals->discount);
        self::assertSame(5700, $totals->total);
        // VAT 20 % on 4500 = 750 ; 5.5 % on 1200 = 63
        self::assertSame(813, $totals->tax);
        self::assertSame('Vêtements -10 %', $totals->promotions->discounts[0]->label);
    }

    public function testFixedAmountIsSpreadToTheCent(): void
    {
        $this->promotion('-10 €', PromotionType::FixedAmount, 1000);
        $totals = $this->totals($this->cart(['TS-S' => 1, 'MUG' => 1, 'PO-1' => 1])); // 2500 + 1200 + 3000
        self::assertSame(1000, $totals->discount);
        self::assertSame(1000, array_sum($totals->promotions->itemDiscounts));
        $split = PromotionCalculator::allocate([0 => 1, 1 => 1, 2 => 1], 10);
        self::assertSame(10, array_sum($split));
        self::assertLessThanOrEqual(1, max($split) - min($split));
    }

    public function testStackingAndExclusivityGiveTheBestOffer(): void
    {
        $this->promotion('-5 %', PromotionType::Percentage, 500, null, fn (Promotion $p) => $p->setPriority(2));
        $this->promotion('-2 €', PromotionType::FixedAmount, 200, null, fn (Promotion $p) => $p->setPriority(1));
        $this->promotion('-20 % exclusive', PromotionType::Percentage, 2000, 'VIP20', fn (Promotion $p) => $p->setExclusive(true));

        // Stacked: 5 % of 5000 = 250, then 2 € = 450 in total.
        self::assertSame(450, $this->totals($this->cart(['TS-S' => 2]))->discount);
        // The exclusive code (1000) beats the stack (450): applied alone.
        $totals = $this->totals($this->cart(['TS-S' => 2], 'VIP20'));
        self::assertSame(1000, $totals->discount);
        self::assertCount(1, $totals->promotions->discounts);
        self::assertTrue($totals->promotions->hasCode('VIP20'));
    }

    public function testSaleItemsCanBeExcluded(): void
    {
        $this->promotion('-50 % hors soldes', PromotionType::Percentage, 5000, null, fn (Promotion $p) => $p->setExcludeSaleItems(true));
        $totals = $this->totals($this->cart(['TS-S' => 1, 'PO-1' => 1])); // the polo has a compare-at price
        self::assertSame(1250, $totals->discount);
    }

    public function testFreeShippingAndMinimumSubtotal(): void
    {
        $this->promotion('Livraison offerte', PromotionType::FreeShipping, 0, 'LIVRAISON', fn (Promotion $p) => $p->setMinSubtotal(2000));
        self::assertSame(490, $this->totals($this->cart(['MUG' => 1], 'LIVRAISON'), 'standard')->shipping, 'Below the minimum.');
        $totals = $this->totals($this->cart(['MUG' => 2], 'LIVRAISON'), 'standard');
        self::assertSame(0, $totals->shipping);
        self::assertTrue($totals->promotions->freeShipping);
    }

    public function testScheduleAndUsageLimit(): void
    {
        $this->promotion('Futur', PromotionType::Percentage, 1000, null, fn (Promotion $p) => $p->setStartsAt(new \DateTimeImmutable('+1 day')));
        $this->promotion('Passé', PromotionType::Percentage, 1000, null, fn (Promotion $p) => $p->setEndsAt(new \DateTimeImmutable('-1 minute')));
        $this->promotion('Épuisé', PromotionType::Percentage, 1000, null, fn (Promotion $p) => $p->setUsageLimit(0));
        self::assertSame(0, $this->totals($this->cart(['TS-S' => 1]))->discount);
    }

    public function testCouponErrorsAreExplicit(): void
    {
        $this->promotion('Bienvenue', PromotionType::Percentage, 1000, 'BIENVENUE', fn (Promotion $p) => $p->setMinSubtotal(4000));
        $this->promotion('Ancien', PromotionType::Percentage, 1000, 'ANCIEN', fn (Promotion $p) => $p->setEndsAt(new \DateTimeImmutable('-1 day')));
        $this->addToCart('TS-S'); // 25 €

        foreach (['NOPE' => 'n’existe pas', 'ancien' => 'expiré', 'bienvenue' => 'ajoutez encore 15,00'] as $code => $message) {
            $this->client->request('POST', '/cart/coupon', ['code' => $code], [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseStatusCodeSame(422);
            self::assertStringContainsString($message, preg_replace('/\s/u', ' ', json_decode($this->client->getResponse()->getContent(), true)['message']));
        }

        // Without JavaScript: the error is shown next to the field, the typed code is kept.
        $this->client->request('POST', '/cart/coupon', ['code' => 'nope']);
        $this->client->followRedirect();
        self::assertSelectorExists('#astra-coupon[aria-invalid="true"][aria-describedby="astra-coupon-error"][value="NOPE"]');
        self::assertSelectorTextContains('#astra-coupon-error', 'n’existe pas');
    }

    public function testCouponIsAppliedOrderedAndCounted(): void
    {
        $promotion = $this->promotion('Bienvenue -10 %', PromotionType::Percentage, 1000, 'BIENVENUE', fn (Promotion $p) => $p->setUsageLimitPerCustomer(1)->setLabel('-10 % de bienvenue'));
        $this->addToCart('TS-S', 2);
        $this->client->request('POST', '/cart/coupon', ['code' => ' bienvenue '], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('-10 % de bienvenue', $data['cart']['discounts'][0]['label']);
        self::assertSame('45,00 €', preg_replace('/\s/u', ' ', $data['cart']['subtotal']));

        $this->client->request('GET', '/cart');
        self::assertSelectorTextContains('main', '-10 % de bienvenue');
        self::assertSelectorTextContains('main', '−5,00');

        $this->fillCheckout('guest@example.com');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', '−5,00');
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));

        $em = $this->em();
        $em->clear();
        $order = $em->getRepository(Order::class)->findOneBy([]);
        self::assertSame(500, $order->getDiscountTotal());
        self::assertSame(4500 + 490, $order->getTotal(), 'Below 60 € after discount: shipping is charged.');
        self::assertSame('BIENVENUE', $order->getCouponCode());
        self::assertSame(500, array_sum(array_map(fn ($i) => $i->getDiscountAmount(), $order->getItems()->toArray())));
        self::assertSame(750, $order->getTaxTotal() - 82, 'VAT on 4500 (750) + shipping 490 (82).');
        self::assertSame(1, $em->find(Promotion::class, $promotion->getId())->getUsageCount());
        self::assertEmailHtmlBodyContains(self::getMailerMessage(), '-10 % de bienvenue');

        // Same e-mail, second order: per-customer limit.
        $this->addToCart('TS-S', 2);
        $this->client->request('POST', '/cart/coupon', ['code' => 'BIENVENUE'], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful(); // the guest's e-mail is not known yet
        $crawler = $this->client->request('GET', '/checkout/address');
        $this->client->submit($crawler->filter('main form')->form([
            'checkout_address[email]' => 'guest@example.com',
            'checkout_address[shippingAddress][firstName]' => 'Jean',
            'checkout_address[shippingAddress][lastName]' => 'Dupont',
            'checkout_address[shippingAddress][street]' => '1 rue de la Paix',
            'checkout_address[shippingAddress][postalCode]' => '75002',
            'checkout_address[shippingAddress][city]' => 'Paris',
            'checkout_address[shippingAddress][phone]' => '0601020304',
        ]));
        $this->client->followRedirect(); // shipping step: the cart is revalidated with the e-mail now known
        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'ne s’applique plus');
        self::assertSelectorTextContains('main', 'déjà utilisé ce code');

        // Cancelling gives the use back.
        self::getContainer()->get(OrderStateMachine::class)->apply($em->getRepository(Order::class)->findOneBy([]), OrderStatus::Cancelled);
        self::assertSame(0, $em->find(Promotion::class, $promotion->getId())->getUsageCount());
    }

    public function testProductPageAdvertisesAutomaticPromotionsAndEtagChanges(): void
    {
        $this->client->request('GET', '/p/t-shirt-bleu');
        $etag = $this->client->getResponse()->getEtag();

        $category = $this->em()->getRepository(Category::class)->findOneBy(['slug' => 't-shirts']);
        $this->promotion('Été', PromotionType::Percentage, 1500, null, fn (Promotion $p) => $p->addCategory($category)->setLabel('−15 % sur les t-shirts')->setMinSubtotal(3000));

        $this->client->request('GET', '/p/t-shirt-bleu', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[aria-label="Nos offres"]', '−15 % sur les t-shirts');
        self::assertSelectorTextContains('[aria-label="Nos offres"]', 'dès 30,00');

        $this->client->request('GET', '/p/mug');
        self::assertSelectorNotExists('[aria-label="Nos offres"]');

        // Not advertised on items already on sale when the promotion excludes them.
        $this->em()->getRepository(Promotion::class)->findOneBy(['name' => 'Été'])->setExcludeSaleItems(true);
        $this->em()->flush();
        $this->client->request('GET', '/p/polo');
        self::assertSelectorNotExists('[aria-label="Nos offres"]');
        $this->client->request('GET', '/p/t-shirt-bleu');
        self::assertSelectorExists('[aria-label="Nos offres"]');
    }
}
