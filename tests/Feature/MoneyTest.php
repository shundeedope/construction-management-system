<?php

use App\Enums\CompanySettingType;
use App\Services\CompanySettings;
use App\Support\Money;
use Database\Seeders\CompanySettingsSeeder;

beforeEach(function () {
    $this->seed(CompanySettingsSeeder::class);
});

it('parses user decimal input into exact cents', function (string|int|float $input, int $cents) {
    expect(Money::fromDecimal($input)->cents)->toBe($cents);
})->with([
    'zero' => ['0', 0],
    'whole dollars' => ['500', 50_000],
    'cents' => ['1234.56', 123_456],
    'single decimal' => ['10.5', 1_050],
    'negative' => ['-25.40', -2_540],
    'currency symbol and separators' => ['$1,234.56', 123_456],
    'large amount' => ['9999999.99', 999_999_999],
    'integer input' => [500, 50_000],
    'float input' => [1234.56, 123_456],
    'sub-cent rounds half away from zero' => ['0.005', 1],
    'sub-cent rounds down' => ['0.004', 0],
    'negative sub-cent rounds away from zero' => ['-0.005', -1],
]);

it('rejects input that is not a money amount', function (mixed $input) {
    expect(fn () => Money::fromDecimal($input))->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [''],
    'letters' => ['abc'],
    'partial' => ['12.34.56'],
    'expression' => ['10 - 5'],
]);

it('formats cents using the configured company currency', function () {
    expect(Money::fromCents(50_000_000)->format())->toBe('$500,000.00')
        ->and(Money::fromCents(0)->format())->toBe('$0.00')
        ->and(Money::fromCents(5)->format())->toBe('$0.05')
        ->and(Money::fromCents(-123_456)->format())->toBe('-$1,234.56')
        ->and(Money::fromCents(100)->format())->toBe('$1.00');
});

it('follows the currency configured in company settings', function () {
    app(CompanySettings::class)->set('currency', 'EUR', CompanySettingType::String);

    expect(Money::fromCents(50_000_000)->format())->toBe('€500,000.00');
});

it('keeps arithmetic in the integer domain', function () {
    $a = Money::fromDecimal('0.1');
    $b = Money::fromDecimal('0.2');

    expect($a->plus($b)->cents)->toBe(30)
        ->and($a->plus($b)->toDecimal())->toBe('0.30')
        ->and(Money::fromCents(1_000)->minus(Money::fromCents(2_500))->cents)->toBe(-1_500)
        ->and(Money::sum([Money::fromCents(1), Money::fromCents(2), Money::fromCents(3)])->cents)->toBe(6)
        ->and(Money::fromCents(-500)->negate()->cents)->toBe(500);
});

it('applies retention as basis points with deterministic rounding', function (int $cents, int $basisPoints, int $expected) {
    expect(Money::fromCents($cents)->basisPoints($basisPoints)->cents)->toBe($expected);
})->with([
    '10% of $500,000.00' => [50_000_000, 1_000, 5_000_000],
    '5% of $1,234.56' => [123_456, 500, 6_173],      // 6172.8 rounds up
    '1% of $0.05' => [5, 100, 0],                    // 0.05 rounds down
    'half rounds away from zero' => [1_000, 5, 1],   // exactly 0.5
    'negative half rounds away from zero' => [-1_000, 5, -1],
    'zero rate' => [123_456, 0, 0],
    'full rate' => [123_456, 10_000, 123_456],
]);

it('computes retention on the total, not per line', function () {
    // Each line's 10% retention has a fractional cent. Rounding every line first loses a cent
    // against rounding the total, so the total is the authoritative base for retention and
    // callers must not sum per-line retention instead.
    $lines = [Money::fromCents(33_333), Money::fromCents(33_333), Money::fromCents(33_334)];

    $perLine = Money::sum(array_map(fn (Money $line): Money => $line->basisPoints(1_000), $lines));
    $onTotal = Money::sum($lines)->basisPoints(1_000);

    expect($onTotal->cents)->toBe(10_000)
        ->and($perLine->cents)->toBe(9_999)
        ->and($onTotal->cents - $perLine->cents)->toBe(1);
});

it('is deterministic: the same input always rounds the same way', function () {
    $results = array_map(
        fn (): int => Money::fromDecimal('1234.565')->basisPoints(333)->cents,
        range(1, 50),
    );

    expect(array_unique($results))->toHaveCount(1);
});

it('reports a percentage of a base amount and survives a zero base', function () {
    expect(Money::fromCents(32_000_000)->percentageOf(Money::fromCents(51_500_000)))->toBe(62.14)
        ->and(Money::fromCents(100)->percentageOf(Money::zero()))->toBeNull();
});

it('exposes sign helpers used by financial validation', function () {
    expect(Money::fromCents(-1)->isNegative())->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromCents(1)->isPositive())->toBeTrue()
        ->and(Money::fromCents(500)->greaterThan(Money::fromCents(499)))->toBeTrue()
        ->and(Money::fromCents(500)->equals(Money::fromCents(500)))->toBeTrue();
});
