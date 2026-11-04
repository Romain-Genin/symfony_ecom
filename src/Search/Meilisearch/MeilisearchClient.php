<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Search\Meilisearch;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Minimal Meilisearch REST client (no SDK needed, just symfony/http-client). */
final class MeilisearchClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $url,
        private readonly ?string $apiKey,
        private readonly float $timeout = 2.0,
    ) {
    }

    /**
     * @param array<mixed>|null $body
     *
     * @return array<mixed>
     *
     * @throws MeilisearchException
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $options = ['timeout' => $this->timeout, 'headers' => ['Accept' => 'application/json']];
        if (null !== $this->apiKey && '' !== $this->apiKey) {
            $options['auth_bearer'] = $this->apiKey;
        }
        if (null !== $body) {
            $options['json'] = $body;
        }

        try {
            $response = $this->http->request($method, rtrim($this->url, '/') . $path, $options);
            $status = $response->getStatusCode();
            $data = '' === $response->getContent(false) ? [] : $response->toArray(false);
        } catch (\Throwable $e) {
            throw new MeilisearchException('Meilisearch unreachable: ' . $e->getMessage(), 0, $e);
        }
        if ($status >= 400) {
            throw new MeilisearchException(sprintf('Meilisearch error %d on %s %s: %s', $status, $method, $path, $data['message'] ?? ''), $status, null, $data['code'] ?? null);
        }

        return $data;
    }
}
