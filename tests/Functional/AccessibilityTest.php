<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Tests\Functional;

use Masterminds\HTML5;
use RomainGenin\AstraCheckoutBundle\Entity\Order;

/**
 * Static WCAG checks on every page of the shop, as rendered by the server (what users get
 * before JavaScript runs, and what assistive technologies parse first).
 */
final class AccessibilityTest extends ShopTestCase
{
    public function testPublicPages(): void
    {
        foreach (['/', '/products', '/c/vetements', '/c/t-shirts?min=10&in_stock=1', '/p/t-shirt-bleu', '/p/mug', '/search?q=polo', '/search?q=zzz', '/cart', '/account/login', '/account/register', '/account/password-reset'] as $url) {
            $this->audit($url);
        }
    }

    public function testPromotionsAnnouncementsAndSearchPages(): void
    {
        $em = $this->em();
        $promotion = (new \RomainGenin\AstraCheckoutBundle\Entity\Promotion('Code', \RomainGenin\AstraCheckoutBundle\Enum\PromotionType::Percentage, 1000))->setCode('DIX');
        $em->persist($promotion);
        $em->persist((new \RomainGenin\AstraCheckoutBundle\Entity\Announcement('Livraison offerte'))->setDismissible(true));
        $em->persist((new \RomainGenin\AstraCheckoutBundle\Entity\Announcement('-10 % avec'))->setPromotion($promotion)->setLinkUrl('/products')->setLinkLabel('Voir les produits'));
        foreach (\RomainGenin\AstraCheckoutBundle\Enum\AnnouncementPlacement::cases() as $placement) {
            $em->persist(new \RomainGenin\AstraCheckoutBundle\Entity\Announcement('Annonce ' . $placement->value, $placement));
        }
        $em->flush();

        $this->audit('/');
        $this->audit('/products');
        $this->audit('/search?q=shirt');
        $this->addToCart('TS-S', 2);
        $this->client->request('POST', '/cart/coupon', ['code' => 'inconnu']);
        $this->client->followRedirect();
        $this->auditCurrent('cart (coupon error)');
        $this->client->request('POST', '/cart/coupon', ['code' => 'dix']);
        $this->client->followRedirect();
        $this->auditCurrent('cart (coupon applied)');
        $this->audit('/checkout/address');
    }

    public function testCartAndCheckoutPages(): void
    {
        $this->addToCart('TS-S', 2);
        $this->audit('/cart');
        $this->audit('/checkout/address');

        // Re-displayed with errors.
        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->filter('main form')->form(['checkout_address[email]' => 'x']));
        $this->auditCurrent('/checkout/address (errors)');

        $this->fillCheckout();
        $this->audit('/checkout/shipping');
        $crawler = $this->audit('/checkout/review');
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        $this->client->followRedirect();
        $this->auditCurrent('confirmation');
    }

    public function testAccountPages(): void
    {
        $this->createCustomer();
        $this->login();
        $this->addToCart('MUG');
        $this->fillCheckout();
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form(['place_order[acceptTerms]' => true]));
        $number = $this->em()->getRepository(Order::class)->findOneBy([])->getNumber();

        foreach (['/account', '/account/orders', '/account/orders/' . $number, '/account/addresses', '/account/addresses/new', '/account/profile', '/account/password', '/account/delete'] as $url) {
            $this->audit($url);
        }
    }

    private function audit(string $url): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->client->request('GET', $url);
        self::assertLessThan(400, $this->client->getResponse()->getStatusCode(), "$url responded " . $this->client->getResponse()->getStatusCode());
        $this->auditCurrent($url);

        return $crawler;
    }

    private function auditCurrent(string $page): void
    {
        $html = (string) $this->client->getResponse()->getContent();
        $doc = (new HTML5(['disable_html_ns' => true]))->loadHTML($html);
        $xp = new \DOMXPath($doc);
        $problems = [];

        // Ignore client-side <template> content (rendered by Alpine with its own data).
        foreach (iterator_to_array($xp->query('//template')) as $template) {
            $template->parentNode->removeChild($template);
        }

        $lang = $xp->query('/html/@lang')->item(0)?->nodeValue;
        if (!$lang) {
            $problems[] = 'html element has no lang attribute (3.1.1)';
        }
        if ('' === trim((string) $xp->query('//head/title')->item(0)?->textContent)) {
            $problems[] = 'empty <title> (2.4.2)';
        }
        if (1 !== $xp->query('//h1')->length) {
            $problems[] = sprintf('%d <h1> instead of 1 (1.3.1)', $xp->query('//h1')->length);
        }
        if (0 === $xp->query('//a[@href="#main"]')->length || 0 === $xp->query('//main[@id="main"]')->length) {
            $problems[] = 'no working skip link (2.4.1)';
        }

        // Heading levels never skip one (h2 → h4).
        $previous = 0;
        foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
            $level = (int) substr($h->nodeName, 1);
            if ($previous && $level > $previous + 1) {
                $problems[] = sprintf('heading level skipped: h%d → h%d "%s" (1.3.1)', $previous, $level, trim($h->textContent));
            }
            $previous = $level;
        }

        $ids = [];
        foreach ($xp->query('//*[@id]') as $el) {
            $id = $el->getAttribute('id');
            if (isset($ids[$id])) {
                $problems[] = "duplicate id \"$id\" (4.1.1)";
            }
            $ids[$id] = true;
        }

        foreach (['aria-labelledby', 'aria-describedby', 'aria-controls', 'for'] as $attribute) {
            foreach ($xp->query("//*[@$attribute]") as $el) {
                foreach (preg_split('/\s+/', trim($el->getAttribute($attribute))) as $ref) {
                    if ('' !== $ref && !isset($ids[$ref])) {
                        $problems[] = "$attribute=\"$ref\" on <{$el->nodeName}> points to no element (1.3.1)";
                    }
                }
            }
        }

        foreach ($xp->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")]|//select|//textarea') as $control) {
            $id = $control->getAttribute('id');
            $labelled = $control->hasAttribute('aria-label') || $control->hasAttribute('aria-labelledby')
                || ('' !== $id && $xp->query("//label[@for='$id']")->length > 0)
                || $xp->query('ancestor::label', $control)->length > 0;
            if (!$labelled) {
                $problems[] = sprintf('form control "%s" has no label (1.3.1 / 4.1.2)', $control->getAttribute('name'));
            }
            if ('true' === $control->getAttribute('aria-invalid') && !$control->hasAttribute('aria-describedby')) {
                $problems[] = sprintf('invalid control "%s" is not linked to its error (3.3.1)', $control->getAttribute('name'));
            }
        }

        foreach ($xp->query('//img[not(@alt)]') as $img) {
            $problems[] = 'image without alt attribute: ' . $img->getAttribute('src') . ' (1.1.1)';
        }

        foreach ($xp->query('//a[@href]|//button') as $el) {
            $name = trim(preg_replace('/\s+/u', ' ', $el->textContent) ?? '');
            foreach ($xp->query('.//img[@alt]', $el) as $img) {
                $name .= $img->getAttribute('alt');
            }
            if ('' === $name && !$el->hasAttribute('aria-label') && !$el->hasAttribute('aria-labelledby') && 'true' !== $el->getAttribute('aria-hidden')) {
                $problems[] = sprintf('<%s> without accessible name (%s) (2.4.4 / 4.1.2)', $el->nodeName, $el->getAttribute('href') ?: $el->getAttribute('class'));
            }
        }

        foreach ($xp->query('//*[@tabindex]') as $el) {
            if ((int) $el->getAttribute('tabindex') > 0) {
                $problems[] = 'positive tabindex (2.4.3)';
            }
        }

        foreach ($xp->query('//fieldset[not(legend)]') as $fieldset) {
            $problems[] = 'fieldset without legend (1.3.1)';
        }

        self::assertSame([], array_values(array_unique($problems)), "Accessibility problems on $page");
    }
}
