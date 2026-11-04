<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\EventListener;

use StudioAstra\AstraCheckoutBundle\Cart\CartContext;
use StudioAstra\AstraCheckoutBundle\Cart\CartManager;
use StudioAstra\AstraCheckoutBundle\Customer\CustomerInfo;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** What the visitor put in the cart before logging in is never lost. */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class MergeCartOnLoginListener
{
    public function __construct(private readonly CartContext $context, private readonly CartManager $manager, private readonly CustomerInfo $customers)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($this->customers->supports($user) && $event->getRequest()->hasSession()) {
            $this->context->mergeAnonymousCartInto($user, $this->manager);
        }
    }
}
