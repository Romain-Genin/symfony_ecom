<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Http;

use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalog pages carry no personal data (the cart badge and the account name are loaded by
 * Alpine from /cart/summary), so they get public Cache-Control headers and an ETag:
 * Varnish / Symfony HttpCache / a CDN serve them without hitting PHP, and browsers revalidate
 * with a 304.
 */
final class CatalogCacheHelper
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly ?ShopContentVersion $contentVersion = null,
    ) {
    }

    private function etag(string $fingerprint): string
    {
        return null === $this->contentVersion ? $fingerprint : hash('xxh128', $fingerprint . '|' . $this->contentVersion->get());
    }

    /** Returns a 304 response when the client copy is fresh, null otherwise. */
    public function notModified(Request $request, string $fingerprint, ?\DateTimeInterface $lastModified = null): ?Response
    {
        if (!$this->settings->isHttpCacheEnabled()) {
            return null;
        }
        $response = $this->prepare(new Response(), $fingerprint, $lastModified);

        return $response->isNotModified($request) ? $response : null;
    }

    public function prepare(Response $response, string $fingerprint, ?\DateTimeInterface $lastModified = null): Response
    {
        if (!$this->settings->isHttpCacheEnabled()) {
            return $response;
        }
        $response->setEtag($this->etag($fingerprint), true);
        if (null !== $lastModified) {
            $response->setLastModified(\DateTimeImmutable::createFromInterface($lastModified));
        }
        $response->setPublic();
        $response->setMaxAge($this->settings->getHttpCacheMaxAge());
        $response->setSharedMaxAge($this->settings->getHttpCacheSharedMaxAge());
        $response->headers->addCacheControlDirective('stale-while-revalidate', '60');
        $response->setVary(['Accept-Language'], false);

        return $response;
    }
}
