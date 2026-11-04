<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

/** Remembers that a shop block was rendered, so its assets are injected in the page. */
#[AsEventListener(event: PreRenderEvent::class)]
final class ComponentUsageTracker implements ResetInterface
{
    private bool $used = false;

    public function __invoke(PreRenderEvent $event): void
    {
        if (str_starts_with($event->getMetadata()->getName(), 'Astra:')) {
            $this->used = true;
        }
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function reset(): void
    {
        $this->used = false;
    }
}
