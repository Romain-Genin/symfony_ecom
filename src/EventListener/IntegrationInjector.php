<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\EventListener;

use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

/**
 * Pages containing shop blocks (<twig:Astra:… />), and shop pages rendered in YOUR layout (astra_checkout.layout) work without editing it: the shop
 * assets go before </head>, the mini-cart and the configuration before </body> — unless the
 * layout already calls {{ astra_head() }} / {{ astra_body_end() }}.
 */
#[AsEventListener(priority: -20)]
final class IntegrationInjector
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly Environment $twig,
        private readonly ?\RomainGenin\AstraCheckoutBundle\Twig\ComponentUsageTracker $blocks = null,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$this->settings->isAutoInjectEnabled() || !$event->isMainRequest() || $response instanceof StreamedResponse
            || (!str_starts_with((string) $request->attributes->get('_route'), 'astra_') && !$this->blocks?->isUsed())
            || !str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }
        $content = $response->getContent();
        if (false === $content || str_contains($content, 'id="astra-config"') || false === ($bodyEnd = strripos($content, '</body>'))) {
            return;
        }

        $content = substr_replace($content, $this->twig->render('@AstraCheckout/integration/body_end.html.twig'), $bodyEnd, 0);
        if (false !== ($headEnd = stripos($content, '</head>'))) {
            $content = substr_replace($content, $this->twig->render('@AstraCheckout/integration/head.html.twig'), $headEnd, 0);
        }
        $response->setContent($content);
    }
}
