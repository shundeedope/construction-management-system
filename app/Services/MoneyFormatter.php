<?php

namespace App\Services;

use App\Support\Money;

/**
 * Formats integer cents for display using the currency configured in company settings.
 * Formatting is deliberately intl-free so output is identical in every environment.
 */
class MoneyFormatter
{
    /** @var array<string, string> */
    private const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'CA$',
        'AUD' => 'A$',
    ];

    public function __construct(private CompanySettings $settings) {}

    public function format(Money $money): string
    {
        $currency = $this->settings->currency();
        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';

        $sign = $money->isNegative() ? '-' : '';
        $absolute = abs($money->cents);

        $formatted = number_format(intdiv($absolute, 100), 0, '.', ',');

        return sprintf('%s%s%s.%02d', $sign, $symbol, $formatted, $absolute % 100);
    }

    public function formatCents(int $cents): string
    {
        return $this->format(Money::fromCents($cents));
    }
}
