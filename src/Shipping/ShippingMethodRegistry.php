<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Shipping;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;

final class ShippingMethodRegistry
{
    /** @var array<string, ShippingMethodInterface>|null */
    private ?array $methods = null;

    /**
     * @param array<string, array<string, mixed>>    $configured
     * @param iterable<ShippingMethodInterface>      $custom
     */
    public function __construct(private readonly array $configured = [], private readonly iterable $custom = [])
    {
    }

    /** @return array<string, ShippingMethodInterface> */
    public function all(): array
    {
        if (null === $this->methods) {
            $this->methods = [];
            foreach ($this->configured as $code => $m) {
                $this->methods[$code] = new ConfiguredShippingMethod($code, $m['label'], $m['description'], $m['price'], $m['free_above'], $m['countries']);
            }
            foreach ($this->custom as $method) {
                $this->methods[$method->getCode()] = $method;
            }
        }

        return $this->methods;
    }

    public function get(string $code): ?ShippingMethodInterface
    {
        return $this->all()[$code] ?? null;
    }

    /** @return array<string, ShippingMethodInterface> */
    public function availableFor(Cart $cart): array
    {
        return array_filter($this->all(), static fn (ShippingMethodInterface $m): bool => $m->supports($cart));
    }

    /** Lowest "free above" threshold, for the cart incentive message. */
    public function getLowestFreeShippingThreshold(): ?int
    {
        $thresholds = array_filter(array_map(static fn (ShippingMethodInterface $m): ?int => $m->getFreeAbove(), $this->all()), static fn (?int $t): bool => null !== $t);

        return $thresholds ? min($thresholds) : null;
    }
}
