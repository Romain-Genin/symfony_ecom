<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Enum;

enum PromotionType: string
{
    /** value = basis points (1000 = 10 %) */
    case Percentage = 'percentage';
    /** value = minor units (500 = 5,00 €), spread over the eligible lines */
    case FixedAmount = 'fixed_amount';
    /** shipping costs set to 0 */
    case FreeShipping = 'free_shipping';
}
