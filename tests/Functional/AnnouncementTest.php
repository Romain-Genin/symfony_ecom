<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Tests\Functional;

use RomainGenin\AstraCheckoutBundle\Entity\Announcement;
use RomainGenin\AstraCheckoutBundle\Entity\Promotion;
use RomainGenin\AstraCheckoutBundle\Enum\AnnouncementPlacement;
use RomainGenin\AstraCheckoutBundle\Enum\PromotionType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class AnnouncementTest extends ShopTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new \Symfony\Component\Clock\NativeClock());
        parent::tearDown();
    }

    private function announce(string $message, AnnouncementPlacement $placement = AnnouncementPlacement::TopBar, ?callable $configure = null): Announcement
    {
        $a = new Announcement($message, $placement);
        if ($configure) {
            $configure($a);
        }
        $this->em()->persist($a);
        $this->em()->flush();

        return $a;
    }

    public function testTopBarShowsActiveAnnouncementsInOrder(): void
    {
        $this->announce('Deuxième', configure: fn (Announcement $a) => $a->setPosition(2));
        $this->announce('Première', configure: fn (Announcement $a) => $a->setPosition(1)->setLinkUrl('/c/maison')->setLinkLabel('Voir la maison'));
        $this->announce('Désactivée', configure: fn (Announcement $a) => $a->setEnabled(false));
        $this->announce('Anglaise', configure: fn (Announcement $a) => $a->setLocale('en'));

        $crawler = $this->client->request('GET', '/products');
        $slides = $crawler->filter('aside[aria-roledescription] [role="group"]');
        self::assertSame(['Première Voir la maison', 'Deuxième'], $slides->each(fn ($n) => trim(preg_replace('/\s+/', ' ', $n->text()))));
        self::assertNull($slides->eq(0)->attr('hidden'), 'The first one is visible without JavaScript.');
        self::assertNotNull($slides->eq(1)->attr('hidden'));
        self::assertSelectorExists('aside[aria-roledescription] [role="group"][aria-label="Annonce 1 sur 2"]');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'), 'Announcements keep catalog pages cacheable.');
    }

    public function testScheduling(): void
    {
        Clock::set(new MockClock('2026-12-01 10:00:00'));
        $this->announce('Black Friday', configure: fn (Announcement $a) => $a->setStartsAt(new \DateTimeImmutable('2026-11-27 00:00'))->setEndsAt(new \DateTimeImmutable('2026-11-30 23:59')));
        $this->announce('Noël', configure: fn (Announcement $a) => $a->setStartsAt(new \DateTimeImmutable('2026-12-01 00:00')));

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('aside[aria-roledescription]', 'Noël');
        self::assertSelectorTextNotContains('aside[aria-roledescription]', 'Black Friday');
    }

    public function testAnnouncementLinkedToAPromotionFollowsIt(): void
    {
        $promotion = (new Promotion('Code', PromotionType::Percentage, 1000))->setCode('HIVER');
        $this->em()->persist($promotion);
        $this->announce('-10 % avec le code', configure: fn (Announcement $a) => $a->setPromotion($promotion));

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('aside[aria-roledescription] strong', 'HIVER');

        $promotion->setEnabled(false);
        $this->em()->flush();
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('aside[aria-roledescription]');
    }

    public function testPlacements(): void
    {
        $this->announce('Retrait en boutique possible', AnnouncementPlacement::Cart, fn (Announcement $a) => $a->setTone('info'));
        $this->announce('Nouvelle collection', AnnouncementPlacement::Home);
        $this->announce('Soldes en cours', AnnouncementPlacement::Catalog);

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('main [aria-label="Annonces"]', 'Nouvelle collection');
        $this->client->request('GET', '/c/maison');
        self::assertSelectorTextContains('main [aria-label="Annonces"]', 'Soldes en cours');
        $this->addToCart('MUG');
        $this->client->request('GET', '/cart');
        self::assertSelectorTextContains('main [aria-label="Annonces"]', 'Retrait en boutique');
        $this->client->request('GET', '/checkout/address');
        self::assertSelectorTextContains('main [aria-label="Annonces"]', 'Retrait en boutique');
    }

    public function testEditingInvalidatesTheCacheAndTheEtag(): void
    {
        $a = $this->announce('Version 1', configure: fn (Announcement $a) => $a->setDismissible(true));
        $this->client->request('GET', '/products');
        $etag = $this->client->getResponse()->getEtag();
        self::assertSelectorExists('script[data-dismissible]');

        $a->setMessage('Version 2');
        $this->em()->flush();
        $this->client->request('GET', '/products', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('aside[aria-roledescription]', 'Version 2');
    }
}
