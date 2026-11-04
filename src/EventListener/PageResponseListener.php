<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\EventListener;

use StudioAstra\AstraCheckoutBundle\Http\PageResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(priority: 10)]
final class PageResponseListener
{
    public function __construct(private readonly PageResponse $page)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (null !== ($response = $this->page->getResponse())) {
            foreach ($event->getResponse()->headers->getCookies() as $cookie) {
                $response->headers->setCookie($cookie);
            }
            $event->setResponse($response);
        } elseif (null !== ($status = $this->page->getStatus()) && $event->getResponse()->isSuccessful()) {
            $event->getResponse()->setStatusCode($status);
        }
        $this->page->reset();
    }
}
