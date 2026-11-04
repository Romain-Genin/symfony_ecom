<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Stateless CSRF protection for cart actions: browsers always send Sec-Fetch-Site / Origin on
 * cross-site POSTs, so checking them blocks forged requests without a session-bound token —
 * which keeps product pages (and their "add to cart" forms) cacheable by a reverse proxy.
 */
final class SameOriginGuard
{
    public function assert(Request $request): void
    {
        if (!$this->isSameOrigin($request)) {
            throw new AccessDeniedHttpException('Cross-origin request refused.');
        }
    }

    public function isSameOrigin(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if (null !== $site) {
            return \in_array($site, ['same-origin', 'none'], true);
        }

        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        if (null === $origin || 'null' === $origin) {
            // Neither header: not a browser cross-site request (curl, tests, very old clients).
            return null === $request->headers->get('Origin');
        }
        $parts = parse_url($origin);
        if (!isset($parts['host'])) {
            return false;
        }
        $port = $parts['port'] ?? ('https' === ($parts['scheme'] ?? 'http') ? 443 : 80);

        return strtolower($parts['host']) === strtolower($request->getHost()) && $port === (int) $request->getPort();
    }
}
