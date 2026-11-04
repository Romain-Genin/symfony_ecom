<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use StudioAstra\AstraCheckoutBundle\Search\TextNormalizer;

final class SearchTest extends ShopTestCase
{
    protected function loadFixtures(): void
    {
        parent::loadFixtures();
        $em = $this->em();
        $sweat = $this->product('Sweat à capuche écru', 'sweat-capuche', [], [['SW-ECRU-M', 6900, 4, ['Couleur' => 'Écru', 'Taille' => 'M']]], '-5 days');
        $sweat->setBrand('Astra');
        $this->product('Bougie parfumée', 'bougie', [], [['BG-01', 2400, 10, []]], '-6 days')->setShortDescription('Parfum sweat-shirt propre et coton');
        $em->flush();
        $em->clear();
    }

    /** @return list<string> */
    private function search(string $q, string $extra = ''): array
    {
        $crawler = $this->client->request('GET', '/search?q=' . urlencode($q) . $extra);
        self::assertResponseIsSuccessful();

        return $crawler->filter('main article h3')->each(fn ($n) => trim($n->text()));
    }

    public function testAccentsCaseAndPlurals(): void
    {
        self::assertSame(['Sweat à capuche écru'], $this->search('ECRU'));
        self::assertSame(['Sweat à capuche écru'], $this->search('sweats capuches'));
        self::assertSame(['Bougie parfumée'], $this->search('Bougies parfumees'));
    }

    public function testCompoundWordsSkusAndOptions(): void
    {
        self::assertContains('T-shirt bleu', $this->search('tshirt'));
        self::assertContains('T-shirt bleu', $this->search('t-shirt'));
        self::assertSame(['Sweat à capuche écru'], $this->search('sw-ecru-m'));
        self::assertSame(['T-shirt bleu'], $this->search('TS-L'));
    }

    public function testRelevanceAndSynonyms(): void
    {
        // Name matches before description matches.
        self::assertSame(['Sweat à capuche écru', 'Bougie parfumée'], $this->search('sweat'));
        // Configured synonym: hoodie → sweat.
        self::assertContains('Sweat à capuche écru', $this->search('hoodie'));
        // Filters still apply to search results.
        self::assertSame(['Bougie parfumée'], $this->search('sweat', '&max=30'));
    }

    public function testIndexFollowsProductChanges(): void
    {
        $product = $this->em()->getRepository(Product::class)->findOneBy(['slug' => 'mug']);
        $product->setName('Tasse émaillée');
        $product->addVariant(new ProductVariant('TASSE-XL', 1500, 3, ['Contenance' => '50 cl']));
        $this->em()->flush();

        self::assertSame(['Tasse émaillée'], $this->search('tasse'));
        self::assertSame(['Tasse émaillée'], $this->search('50 cl'));
        self::assertSame([], $this->search('lampe'), 'Disabled products are never found.');
    }

    public function testSuggestions(): void
    {
        $this->client->request('GET', '/search/suggest?q=swea');
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Sweat à capuche écru', $data['products'][0]['name']);
        self::assertSame('/p/sweat-capuche', $data['products'][0]['url']);
        self::assertSame('/search?q=swea', $data['searchUrl']);
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'));

        $this->client->request('GET', '/search/suggest?q=vete');
        self::assertSame([['name' => 'Vêtements', 'url' => '/c/vetements']], json_decode($this->client->getResponse()->getContent(), true)['categories']);

        $this->client->request('GET', '/search/suggest?q=s');
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true)['products'], 'Below the minimum length.');
    }

    public function testHeaderSearchIsAnAccessibleCombobox(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorExists('form[role="search"] input[role="combobox"][aria-autocomplete="list"][aria-controls="astra-search-listbox"][aria-expanded="false"]');
        self::assertSelectorExists('#astra-search-listbox[role="listbox"]');
        self::assertSelectorExists('#astra-search-status[role="status"]');
    }

    public function testNormalizer(): void
    {
        self::assertSame(' tshirt t shirt col v ', TextNormalizer::forIndex('T-shirt col V'));
        self::assertSame([['tshirt'], ['bleu']], TextNormalizer::queryTerms('T-shirts bleus'));
        self::assertSame([['sweat', 'hoodie', 'pull']], TextNormalizer::queryTerms('sweats', ['sweat' => ['hoodie', 'pull']]));
    }
}
