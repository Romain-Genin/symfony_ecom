<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig;

use StudioAstra\AstraCheckoutBundle\Announcement\AnnouncementProvider;
use StudioAstra\AstraCheckoutBundle\Pricing\MoneyFormatter;
use StudioAstra\AstraCheckoutBundle\Repository\CategoryRepository;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AstraCheckoutExtension extends AbstractExtension implements GlobalsInterface
{
    public const CATEGORY_TREE_CACHE_KEY = 'astra_checkout.category_tree';

    /** @var array<string, string> */
    private array $assetVersions = [];

    public function __construct(
        private readonly ShopSettings $settings,
        private readonly MoneyFormatter $money,
        private readonly CategoryRepository $categories,
        private readonly CacheInterface $cache,
        private readonly ?Packages $packages = null,
        private readonly ?AnnouncementProvider $announcements = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?\StudioAstra\AstraCheckoutBundle\Routing\ShopUrls $urls = null,
    ) {
    }

    public function getGlobals(): array
    {
        return ['astra' => ['settings' => $this->settings, 'layout' => $this->settings->getLayout()]];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('astra_price', $this->money->format(...)),
            new TwigFilter('astra_decimal', $this->money->toDecimal(...)),
            new TwigFilter('astra_country', static fn (?string $code, ?string $locale = null): string => null === $code ? '' : (Countries::exists($code) ? Countries::getName($code, $locale) : $code)),
            new TwigFilter('astra_excerpt', static fn (?string $text, int $length = 160): string => mb_strlen($t = trim((string) preg_replace('/\s+/u', ' ', (string) $text))) > $length ? rtrim(mb_substr($t, 0, $length - 1)) . '…' : $t),
            new TwigFilter('astra_percent', static fn (int $basisPoints): string => rtrim(rtrim(number_format($basisPoints / 100, 2, ',', ''), '0'), ',') . ' %'),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('astra_categories', $this->categoryTree(...)),
            new TwigFunction('astra_announcements', fn (string $placement): array => $this->announcements?->forPlacement($placement, $this->requestStack?->getCurrentRequest()?->getLocale()) ?? []),
            new TwigFunction('astra_image', $this->image(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('astra_asset', $this->asset(...)),
            new TwigFunction('astra_home_path', fn (): string => $this->urls?->path('home') ?? '/'),
            // Links to shop pages, yours when configured in astra_checkout.pages: {{ astra_path('product', {slug: product.slug}) }}
            new TwigFunction('astra_path', fn (string $page, array $parameters = []): string => $this->urls->path($page, $parameters)),
            new TwigFunction('astra_url', fn (string $page, array $parameters = []): string => $this->urls->url($page, $parameters)),
            new TwigFunction('astra_head', static fn (Environment $env): string => $env->render('@AstraCheckout/integration/head.html.twig'), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('astra_body_end', static fn (Environment $env): string => $env->render('@AstraCheckout/integration/body_end.html.twig'), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('astra_json_ld', $this->jsonLd(...), ['is_safe' => ['html']]),
        ];
    }

    /** @return list<array{id: int, name: string, slug: string, children: list<array<string, mixed>>}> */
    public function categoryTree(): array
    {
        return $this->cache->get(self::CATEGORY_TREE_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->categories->getTree();
        });
    }

    /**
     * Product image with explicit dimensions (no layout shift) and lazy loading by default.
     * Uses responsive_image() (AVIF/WebP + srcset) when studioastra/image-optimizer-bundle is installed.
     *
     * @param array<string, mixed> $attributes alt, sizes, class, width, height, loading, fetchpriority…
     */
    public function image(Environment $env, ?string $path, array $attributes = []): string
    {
        $attributes += ['alt' => '', 'loading' => 'lazy', 'decoding' => 'async'];
        if (null === $path || '' === $path) {
            $label = htmlspecialchars((string) $attributes['alt'], \ENT_QUOTES);
            $class = htmlspecialchars((string) ($attributes['class'] ?? ''), \ENT_QUOTES);

            return sprintf('<div class="astra-image-placeholder %s" role="img" aria-label="%s"></div>', $class, $label);
        }

        $external = (bool) preg_match('#^(https?:)?//#', $path);
        if (!$external && null !== ($responsive = $env->getFunction('responsive_image'))) {
            $alt = (string) $attributes['alt'];
            $options = $attributes;
            unset($options['width'], $options['height']);
            if ('' === trim($alt)) {
                unset($options['alt']);
                $options['decorative'] = true;
            }

            return (string) ($responsive->getCallable())($path, $options);
        }

        unset($attributes['sizes']);
        $src = $external ? $path : ($this->packages?->getUrl($path) ?? '/' . ltrim($path, '/'));
        $html = '<img src="' . htmlspecialchars($src, \ENT_QUOTES) . '"';
        foreach ($attributes as $name => $value) {
            if (null === $value || false === $value) {
                continue;
            }
            $html .= ' ' . htmlspecialchars((string) $name, \ENT_QUOTES) . '="' . htmlspecialchars(true === $value ? (string) $name : (string) $value, \ENT_QUOTES) . '"';
        }

        return $html . '>';
    }

    /** URL of a bundle asset with a content hash, so it can be cached for a year (immutable). */
    public function asset(string $file): string
    {
        $path = 'bundles/astracheckout/' . ltrim($file, '/');
        $this->assetVersions[$file] ??= substr(hash_file('xxh128', \dirname(__DIR__, 2) . '/public/' . ltrim($file, '/')) ?: '0', 0, 10);
        $url = $this->packages?->getUrl($path) ?? '/' . $path;

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $this->assetVersions[$file];
    }

    /** @param array<string, mixed> $data */
    public function jsonLd(array $data): string
    {
        $json = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR);

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
