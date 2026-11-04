<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Order;

use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;

/**
 * "AS-261104-7KQ4M2": readable over the phone (no 0/O, 1/I/L), not guessable, not revealing the sales volume.
 */
class OrderNumberGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly ShopSettings $settings,
        private readonly OrderRepository $orders,
    ) {
    }

    public function generate(): string
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $suffix = '';
            for ($i = 0; $i < 6; ++$i) {
                $suffix .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $number = sprintf('%s-%s-%s', $this->settings->getOrderNumberPrefix(), date('ymd'), $suffix);
            if (null === $this->orders->findOneBy(['number' => $number])) {
                return $number;
            }
        }

        throw new \RuntimeException('Unable to generate a unique order number.');
    }
}
