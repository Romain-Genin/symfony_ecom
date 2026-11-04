<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Tests\Functional;

use RomainGenin\AstraCheckoutBundle\Customer\Customer;
use RomainGenin\AstraCheckoutBundle\Entity\Order;

final class AccountTest extends ShopTestCase
{
    public function testAccountRequiresLogin(): void
    {
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/account/login');
    }

    public function testRegistrationLogsTheCustomerIn(): void
    {
        $crawler = $this->client->request('GET', '/account/register');
        self::assertSelectorExists('input[name="registration[plainPassword]"][autocomplete="new-password"]');
        $this->client->submit($crawler->filter('main form')->form([
            'registration[firstName]' => 'Zoé',
            'registration[lastName]' => 'Durand',
            'registration[email]' => 'Zoe@Example.com',
            'registration[plainPassword]' => 'correct horse battery',
        ]));
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Bonjour Zoé');
        self::assertNotNull($this->em()->getRepository(Customer::class)->findOneBy(['email' => 'zoe@example.com']));
    }

    public function testRegistrationValidation(): void
    {
        $this->createCustomer();
        $crawler = $this->client->request('GET', '/account/register');
        $this->client->submit($crawler->filter('main form')->form([
            'registration[firstName]' => 'A',
            'registration[lastName]' => 'B',
            'registration[email]' => 'alice@example.com',
            'registration[plainPassword]' => 'short',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#registration_email_errors', 'existe déjà');
        self::assertSelectorTextContains('#registration_plainPassword_errors', '8 caractères');
    }

    public function testLoginMergesTheAnonymousCart(): void
    {
        $this->createCustomer();
        $this->addToCart('TS-S', 2);
        $this->login();
        self::assertResponseRedirects();

        $this->client->request('GET', '/cart/summary');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(2, $data['count']);
        self::assertSame('Alice', $data['customer']['firstName']);
    }

    public function testWrongPassword(): void
    {
        $this->createCustomer();
        $this->login('alice@example.com', 'nope');
        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
        self::assertSelectorExists('#username[aria-invalid="true"]');
    }

    public function testAddressBookAndCheckoutPrefill(): void
    {
        $this->createCustomer();
        $this->login();

        $crawler = $this->client->request('GET', '/account/addresses/new');
        $this->client->submit($crawler->filter('main form')->form([
            'address[label]' => 'Maison',
            'address[postalAddress][street]' => '5 avenue Foch',
            'address[postalAddress][postalCode]' => '69006',
            'address[postalAddress][city]' => 'Lyon',
            'address[postalAddress][countryCode]' => 'FR',
        ]));
        self::assertResponseRedirects('/account/addresses');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '5 avenue Foch');
        self::assertSelectorTextContains('main', 'Par défaut');

        $this->addToCart('MUG');
        $crawler = $this->client->request('GET', '/checkout/address');
        self::assertSame('5 avenue Foch', $crawler->filter('#checkout_address_shippingAddress_street')->attr('value'));
        self::assertCount(0, $crawler->filter('input[name="checkout_address[email]"]'), 'Logged-in customers are not asked for their e-mail.');
    }

    public function testOrderHistoryIsPrivate(): void
    {
        $this->createCustomer();
        $this->createCustomer('bob@example.com');
        $this->login();
        $this->addToCart('MUG');
        $this->fillCheckout();
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        $number = $this->em()->getRepository(Order::class)->findOneBy([])->getNumber();

        $this->client->request('GET', '/account/orders');
        self::assertSelectorTextContains('main', $number);
        $this->client->request('GET', '/account/orders/' . $number);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[aria-current="step"]');

        $this->client->request('GET', '/account/logout');
        $this->login('bob@example.com');
        $this->client->request('GET', '/account/orders/' . $number);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCustomerCanCancelAPendingOrder(): void
    {
        $this->createCustomer();
        $this->login();
        $this->addToCart('TS-S');
        $this->fillCheckout();
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        $number = $this->em()->getRepository(Order::class)->findOneBy([])->getNumber();

        $crawler = $this->client->request('GET', '/account/orders/' . $number);
        $this->client->submit($crawler->filter('form[action$="/cancel"]')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Annulée');
    }

    public function testPasswordReset(): void
    {
        $this->createCustomer();
        $crawler = $this->client->request('GET', '/account/password-reset');
        $this->client->submit($crawler->filter('main form')->form(['password_reset_request[email]' => 'alice@example.com']));
        self::assertResponseRedirects('/account/login');
        self::assertEmailCount(1);
        preg_match('#/account/password-reset/([a-f0-9]{64})#', self::getMailerMessage()->getHtmlBody(), $m);
        self::assertNotEmpty($m);

        $this->client->request('GET', $m[0]);
        self::assertResponseRedirects('/account/password-reset/new');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['change_password[newPassword]' => 'brand new password']));
        self::assertResponseRedirects('/account/login');

        $this->login('alice@example.com', 'brand new password');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Bonjour Alice');

        // Single use.
        $this->client->request('GET', '/account/logout');
        $this->client->request('GET', $m[0]);
        $this->client->followRedirect();
        self::assertResponseRedirects('/account/password-reset');
    }

    public function testUnknownEmailGetsTheSameAnswer(): void
    {
        $crawler = $this->client->request('GET', '/account/password-reset');
        $this->client->submit($crawler->filter('main form')->form(['password_reset_request[email]' => 'nobody@example.com']));
        self::assertResponseRedirects('/account/login');
        self::assertEmailCount(0);
    }

    public function testAccountDeletionKeepsOrders(): void
    {
        $this->createCustomer();
        $this->login();
        $this->addToCart('MUG');
        $this->fillCheckout();
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));

        $crawler = $this->client->request('GET', '/account/delete');
        $this->client->submit($crawler->filter('main form')->form(['delete_account[password]' => 'secret-password']));
        self::assertResponseRedirects('/products');

        $em = $this->em();
        $em->clear();
        self::assertNull($em->getRepository(Customer::class)->findOneBy(['email' => 'alice@example.com']));
        $order = $em->getRepository(Order::class)->findOneBy([]);
        self::assertNotNull($order);
        self::assertNull($order->getCustomer());
    }
}
