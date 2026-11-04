<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

use StudioAstra\AstraCheckoutBundle\Entity\Address;
use StudioAstra\AstraCheckoutBundle\Entity\Order;
use StudioAstra\AstraCheckoutBundle\Tests\Functional\App\User;

/**
 * The shop added to an existing website: the site keeps its User entity, login pages and layout,
 * and builds its OWN pages (own routes, own templates) with the shop blocks <twig:Astra:… />.
 */
final class HostSiteTest extends ShopTestCase
{
    protected const ENVIRONMENT = 'host';

    private function user(): User
    {
        $user = new User('marie@example.com', 'secret', 'Marie', 'Curie');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function siteLogin(): void
    {
        $this->client->request('POST', '/connexion', ['_username' => 'marie@example.com', '_password' => 'secret']);
    }

    public function testBlocksInTheSitePages(): void
    {
        // A site page using a single block: products + shop assets injected.
        $crawler = $this->client->request('GET', '/');
        self::assertSelectorTextContains('h1', 'Accueil du site');
        self::assertCount(2, $crawler->filter('.site-grid article'));
        self::assertSelectorExists('.site-grid a[href="/produit/mug"]');
        self::assertCount(1, $crawler->filter('#astra-config'));
        self::assertCount(1, $crawler->filter('dialog#astra-minicart'));

        $crawler = $this->client->request('GET', '/produits');
        self::assertSelectorTextContains('h1', 'Tous les produits');
        self::assertSelectorExists('main a[href="/produit/t-shirt-bleu"]');
        self::assertSelectorExists('nav[aria-label="Fil d’Ariane"] a[href="/"]');
        $this->client->request('GET', '/categorie/vetements');
        self::assertSelectorTextContains('h1', 'Vêtements');
        self::assertSelectorExists('a[href="/categorie/t-shirts"]');
        $this->client->request('GET', '/recherche?q=mug');
        self::assertSelectorTextContains('main article', 'Mug');

        $this->client->request('GET', '/produit/t-shirt-bleu');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'T-shirt bleu');
        self::assertSelectorExists('form[action="/panier/add"]');
        $this->client->request('GET', '/produit/inconnu');
        self::assertResponseStatusCodeSame(404);

        // The shop pages replaced by the site's are not registered.
        foreach (['/products', '/p/mug', '/cart', '/checkout'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testTheSiteLayoutAndHeader(): void
    {
        $crawler = $this->client->request('GET', '/produits');
        self::assertSelectorExists('header.site-header');
        self::assertSelectorTextContains('footer', 'Pied de page du site');
        self::assertSelectorExists('head script[src*="astra-checkout-components.js"]');
        self::assertSelectorNotExists('link[href*="astra-checkout.css"]', 'The site Tailwind build styles the shop.');
        self::assertSelectorExists('.site-nav a.site-nav-link[href="/panier"]');
        self::assertSelectorExists('.site-nav input#site-search[role="combobox"]');
        self::assertSelectorExists('.site-nav a[href="/mon-compte"]');
        self::assertSelectorExists('.site-menu a[href="/categorie/vetements"]');
        self::assertCount(1, $crawler->filter('#astra-config'));
    }

    public function testTheShopDoesNotRegisterItsOwnAuthenticationPages(): void
    {
        foreach (['/account/login', '/account/register', '/account/password-reset', '/account/profile', '/account/delete'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
        // An account block on a site page sends anonymous visitors to the site login.
        $this->client->request('GET', '/mon-compte');
        self::assertResponseRedirects('/connexion');
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM sqlite_master WHERE name = 'astra_customer'"));
    }

    public function testOrderingThroughTheSitePages(): void
    {
        $user = $this->user();
        $this->addToCart('TS-S', 2);
        $this->siteLogin();

        $this->client->request('GET', '/panier');
        self::assertSelectorTextContains('main', 'T-shirt bleu');
        self::assertSelectorExists('main a[href="/commande"]');

        $crawler = $this->client->request('GET', '/commande');
        self::assertCount(0, $crawler->filter('input[name="checkout_address[email]"]'), 'The e-mail comes from the site account.');
        self::assertSame('Marie', $crawler->filter('#checkout_address_shippingAddress_firstName')->attr('value'));
        self::assertSame('/commande?step=address', $crawler->filter('main form')->attr('action'));

        // Invalid submission: errors on the site page, answered with 422.
        $this->client->submit($crawler->filter('main form')->form(['checkout_address[shippingAddress][city]' => '']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#checkout_address_shippingAddress_city[aria-invalid="true"]');

        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->filter('main form')->form([
            'checkout_address[shippingAddress][street]' => '11 rue Pierre et Marie Curie',
            'checkout_address[shippingAddress][postalCode]' => '75005',
            'checkout_address[shippingAddress][city]' => 'Paris',
            'checkout_address[shippingAddress][phone]' => '0102030405',
            'checkout_address[saveAddress]' => true,
        ]));
        self::assertResponseRedirects('/commande?step=shipping', 303);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form());
        self::assertResponseRedirects('/commande?step=review', 303);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        self::assertResponseRedirects();
        self::assertStringStartsWith('/commande/merci/', $this->client->getResponse()->headers->get('Location'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Merci');

        $em = $this->em();
        $em->clear();
        $order = $em->getRepository(Order::class)->findOneBy([]);
        self::assertSame($user->getId(), $order->getCustomer()->getId());
        self::assertCount(1, $em->getRepository(Address::class)->findBy(['customer' => $order->getCustomer()]));

        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('main', $order->getNumber());
        self::assertSelectorExists('main a[href="/mon-compte/commande/' . $order->getNumber() . '"]');
        $this->client->request('GET', '/mon-compte/commande/' . $order->getNumber());
        self::assertSelectorTextContains('main', '11 rue Pierre et Marie Curie');
        $this->client->request('GET', '/mon-compte/commande/AS-000000-XXXXXX');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAddressBookOnSitePages(): void
    {
        $this->user();
        $this->siteLogin();
        $crawler = $this->client->request('GET', '/mon-compte/adresses/nouvelle');
        $this->client->submit($crawler->filter('main form')->form([
            'address[postalAddress][street]' => '1 place Bellecour',
            'address[postalAddress][postalCode]' => '69002',
            'address[postalAddress][city]' => 'Lyon',
        ]));
        self::assertResponseRedirects('/mon-compte/adresses', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', '1 place Bellecour');
        $id = $this->em()->getRepository(Address::class)->findOneBy([])->getId();
        self::assertSelectorExists('main a[href="/mon-compte/adresses/' . $id . '"]');
        $edit = '/mon-compte/adresses/' . $id;
        $this->client->request('GET', $edit);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main form input[value="1 place Bellecour"]');
    }

    public function testGuestsAreSentToTheSiteLoginAndRegistration(): void
    {
        $this->addToCart('MUG');
        $this->client->request('GET', '/commande');
        self::assertSelectorExists('a[href="/connexion?checkout=1"]');

        $this->fillCheckout('invite@example.com');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/inscription"]');
    }
}
