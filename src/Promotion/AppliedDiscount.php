<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Promotion;

final class AppliedDiscount
{
    public function __construct(
        public readonly int $promotionId,
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $code,
        public readonly int $amount,
        public readonly bool $freeShipping = false,
    ) {
    }

    /** @return array{id: int, name: string, label: string, code: ?string, amount: int, freeShipping: bool} */
    public function toArray(): array
    {
        return ['id' => $this->promotionId, 'name' => $this->name, 'label' => $this->label, 'code' => $this->code, 'amount' => $this->amount, 'freeShipping' => $this->freeShipping];
    }
}
