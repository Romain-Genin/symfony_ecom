<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Lets a block placed in YOUR template act on the HTTP response: a submitted checkout form
 * redirects to the next step (303), an invalid one answers 422, a missing order 404. Applied by
 * PageResponseListener once your page is rendered — your controller stays a plain render().
 */
final class PageResponse implements ResetInterface
{
    private ?Response $response = null;
    private ?int $status = null;

    public function redirect(string $url): void
    {
        $this->response ??= new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }

    public function replaceWith(Response $response): void
    {
        $this->response ??= $response;
    }

    public function setStatus(int $status): void
    {
        $this->status = max($this->status ?? 0, $status);
    }

    public function hasRedirect(): bool
    {
        return null !== $this->response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function reset(): void
    {
        $this->response = null;
        $this->status = null;
    }
}
