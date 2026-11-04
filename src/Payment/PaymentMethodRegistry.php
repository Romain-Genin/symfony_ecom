<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Payment;

use StudioAstra\AstraCheckoutBundle\Entity\Cart;

final class PaymentMethodRegistry
{
    /** @var array<string, PaymentMethodInterface>|null */
    private ?array $methods = null;

    /**
     * @param array<string, array<string, mixed>> $offline
     * @param iterable<PaymentMethodInterface>    $custom
     */
    public function __construct(private readonly array $offline = [], private readonly iterable $custom = [])
    {
    }

    /** @return array<string, PaymentMethodInterface> */
    public function all(): array
    {
        if (null === $this->methods) {
            $this->methods = [];
            foreach ($this->offline as $code => $m) {
                if ($m['enabled']) {
                    $this->methods[$code] = new OfflinePaymentMethod($code, $m['label'], $m['description'], $m['instructions']);
                }
            }
            foreach ($this->custom as $method) {
                $this->methods[$method->getCode()] = $method;
            }
        }

        return $this->methods;
    }

    public function get(string $code): ?PaymentMethodInterface
    {
        return $this->all()[$code] ?? null;
    }

    /** @return array<string, PaymentMethodInterface> */
    public function availableFor(Cart $cart): array
    {
        return array_filter($this->all(), static fn (PaymentMethodInterface $m): bool => $m->supports($cart));
    }
}
