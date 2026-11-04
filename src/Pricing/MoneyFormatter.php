<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Pricing;

use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\HttpFoundation\RequestStack;

/** Formats minor-unit amounts ("1990" → "19,90 €") for the current locale. */
final class MoneyFormatter
{
    /** @var array<string, \NumberFormatter> */
    private array $formatters = [];

    public function __construct(
        private readonly ShopSettings $settings,
        private readonly RequestStack $requestStack,
        private readonly string $defaultLocale = 'fr',
    ) {
    }

    public function format(int $amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency ??= $this->settings->getCurrency();
        $locale ??= $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->defaultLocale;
        $formatter = $this->formatters[$locale] ??= new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $fraction = $this->fractionDigits($currency);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $fraction);

        $formatted = $formatter->formatCurrency($amount / 10 ** $fraction, $currency);

        return false === $formatted ? number_format($amount / 100, 2) . ' ' . $currency : $formatted;
    }

    /** "19.90" style decimal string, for JSON-LD / analytics. */
    public function toDecimal(int $amount, ?string $currency = null): string
    {
        $fraction = $this->fractionDigits($currency ?? $this->settings->getCurrency());

        return number_format($amount / 10 ** $fraction, $fraction, '.', '');
    }

    private function fractionDigits(string $currency): int
    {
        return \in_array(strtoupper($currency), ['JPY', 'KRW', 'CLP', 'ISK', 'VND', 'XAF', 'XOF'], true) ? 0 : 2;
    }
}
