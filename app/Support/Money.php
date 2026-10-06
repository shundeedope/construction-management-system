<?php

namespace App\Support;

use App\Services\MoneyFormatter;
use InvalidArgumentException;
use Stringable;

/**
 * Money is always integer cents. Every arithmetic operation stays in the integer domain,
 * so no float rounding drift can reach the database.
 */
final readonly class Money implements Stringable
{
    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse user input such as `1234.56`, `$1,234.56`, or `-500` into exact cents. Currency
     * symbols and thousands separators are stripped; the decimal separator must be a period.
     * Fractions below a cent are rounded half away from zero.
     */
    public static function fromDecimal(string|int|float $amount): self
    {
        $normalized = self::normalizeDecimalInput($amount);

        if (! preg_match('/^(?<sign>-?)(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException("Unable to parse [{$amount}] as a money amount.");
        }

        $sign = $matches['sign'] === '-' ? -1 : 1;
        $whole = (int) $matches['whole'];
        $fraction = $matches['fraction'] ?? '';

        // Scale the fraction to hundredths, keeping the remaining digits to round with.
        $hundredths = (int) substr(str_pad($fraction, 2, '0'), 0, 2);
        $remainder = substr($fraction, 2);

        $cents = $whole * 100 + $hundredths;

        if ($remainder !== '' && (int) $remainder[0] >= 5) {
            $cents++;
        }

        return new self($sign * $cents);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function negate(): self
    {
        return new self(-$this->cents);
    }

    public function times(int $multiplier): self
    {
        return new self($this->cents * $multiplier);
    }

    /**
     * Apply a rate stored as basis points, where 1% = 100 and 10% = 1000.
     * This is the single rounding rule used for retention and every other percentage of money.
     */
    public function basisPoints(int $basisPoints): self
    {
        return new self(self::divideRoundingHalfAwayFromZero($this->cents * $basisPoints, 10_000));
    }

    /**
     * This amount as a percentage of another, or null when the base is zero.
     */
    public function percentageOf(self $base): ?float
    {
        if ($base->cents === 0) {
            return null;
        }

        return round($this->cents / $base->cents * 100, 2);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function greaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function lessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    /**
     * @param  iterable<int, self>  $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += $amount->cents;
        }

        return new self($total);
    }

    /**
     * The plain decimal representation, e.g. `-1234.56`. Never localized.
     */
    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $absolute = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    /**
     * The display representation using the configured company currency, e.g. `$500,000.00`.
     */
    public function format(): string
    {
        return app(MoneyFormatter::class)->format($this);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Integer division rounding halves away from zero, without touching floats.
     */
    public static function divideRoundingHalfAwayFromZero(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Cannot divide a money amount by zero.');
        }

        $sign = ($numerator < 0) === ($denominator < 0) ? 1 : -1;
        $absoluteNumerator = abs($numerator);
        $absoluteDenominator = abs($denominator);

        return $sign * intdiv(2 * $absoluteNumerator + $absoluteDenominator, 2 * $absoluteDenominator);
    }

    private static function normalizeDecimalInput(string|int|float $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('Unable to parse a non-finite float as a money amount.');
            }

            // Enough precision to preserve sub-cent digits for the rounding step above.
            return rtrim(rtrim(number_format($amount, 6, '.', ''), '0'), '.') ?: '0';
        }

        $normalized = trim($amount);
        $normalized = preg_replace('/[\s\x{00A0}$€£,]/u', '', $normalized) ?? '';

        if ($normalized === '') {
            throw new InvalidArgumentException('Unable to parse an empty value as a money amount.');
        }

        return $normalized;
    }
}
