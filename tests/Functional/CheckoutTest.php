<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Tests\Functional;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Entity\ProductVariant;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Order\OrderStateMachine;

final class CheckoutTest extends ShopTestCase
{
    public function testEmptyCartCannotCheckout(): void
    {
        $this->client->request('GET', '/checkout');
        self::assertResponseRedirects('/cart');
        $this->client->request('GET', '/checkout/review');
        self::assertResponseRedirects('/cart');
    }

    public function testStepsCannotBeSkipped(): void
    {
        $this->addToCart('TS-S');
        $this->client->request('GET', '/checkout/review');
        self::assertResponseRedirects('/checkout/address');
        $this->client->request('GET', '/checkout');
        self::assertResponseRedirects('/checkout/address');
    }

    public function testInvalidAddressShowsAccessibleErrors(): void
    {
        $this->addToCart('TS-S');
        $crawler = $this->client->request('GET', '/checkout/address');
        $this->client->submit($crawler->filter('main form')->form(['checkout_address[email]' => 'not-an-email']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"] a[href="#checkout_address_email"]');
        self::assertSelectorExists('#checkout_address_email[aria-invalid="true"][aria-describedby~="checkout_address_email_errors"]');
        self::assertSelectorExists('#checkout_address_shippingAddress_city[aria-invalid="true"]');
    }

    public function testGuestCheckoutPlacesTheOrder(): void
    {
        $this->addToCart('TS-S', 2);   // 2 × 25 €
        $this->addToCart('MUG', 1);    // 12 €, VAT 5.5 %
        $this->fillCheckout();

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'guest@example.com');
        self::assertSelectorTextContains('main', '1 rue de la Paix');
        self::assertSelectorTextContains('button[type="submit"]', 'obligation de paiement');

        // Terms not accepted.
        $this->client->submit($crawler->filter('main form')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'conditions générales');

        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        self::assertResponseRedirects();
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailHtmlBodyContains($email, 'IBAN');

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Merci');
        self::assertSelectorTextContains('main', 'IBAN');

        $em = $this->em();
        $em->clear();
        /** @var Order $order */
        $order = $em->getRepository(Order::class)->findOneBy([]);
        self::assertSame(OrderStatus::PendingPayment, $order->getStatus());
        self::assertSame(6200, $order->getSubtotal());
        self::assertSame(0, $order->getShippingTotal(), 'Free shipping from 60 €.');
        self::assertSame(6200, $order->getTotal());
        // VAT included: 5000 × 20/120 = 833 ; 1200 × 5.5/105.5 = 63
        self::assertSame(896, $order->getTaxTotal());
        self::assertCount(2, $order->getItems());
        self::assertSame('Jean Dupont', $order->getShippingAddress()->getFullName());
        self::assertSame('Paris', $order->getBillingAddress()->getCity());
        self::assertSame(8, $em->getRepository(ProductVariant::class)->findOneBy(['sku' => 'TS-S'])->getStock());
        self::assertSame(0, $em->getRepository(Cart::class)->count([]), 'The cart is deleted once ordered.');

        // Confirmation page is private and the cart badge is now empty.
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $this->client->request('GET', '/cart/summary');
        self::assertSame(0, json_decode($this->client->getResponse()->getContent(), true)['count']);
    }

    public function testShippingIsChargedBelowTheFreeThreshold(): void
    {
        $this->addToCart('MUG');
        $this->fillCheckout();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '4,90');
    }

    public function testOrderFailsWhenStockWasSoldMeanwhile(): void
    {
        $this->addToCart('TS-M', 2);
        $this->fillCheckout();
        $crawler = $this->client->followRedirect();

        $variant = $this->em()->getRepository(ProductVariant::class)->findOneBy(['sku' => 'TS-M']);
        $variant->setStock(1);
        $this->em()->flush();

        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        self::assertResponseRedirects('/cart');
        self::assertSame(0, $this->em()->getRepository(Order::class)->count([]));
    }

    public function testCancellingAnOrderRestocks(): void
    {
        $this->addToCart('TS-S', 3);
        $this->fillCheckout();
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));

        $em = $this->em();
        $em->clear();
        $order = $em->getRepository(Order::class)->findOneBy([]);
        self::assertSame(7, $em->getRepository(ProductVariant::class)->findOneBy(['sku' => 'TS-S'])->getStock());

        $machine = self::getContainer()->get(OrderStateMachine::class);
        $machine->apply($order, OrderStatus::Cancelled);
        self::assertSame(10, $em->getRepository(ProductVariant::class)->findOneBy(['sku' => 'TS-S'])->getStock());

        $this->expectException(\LogicException::class);
        $machine->apply($order, OrderStatus::Shipped);
    }
}
