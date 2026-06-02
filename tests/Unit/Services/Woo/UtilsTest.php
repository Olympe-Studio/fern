<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Services\Woo\Utils;

function stubWooPriceFunctions(
  string $decimalSeparator = '.',
  string $thousandSeparator = ',',
  int $decimals = 2,
  string $format = '%1$s%2$s',
  string $symbol = '$',
): void {
  Functions\when('WC')->justReturn(Mockery::mock('WooCommerce'));
  Functions\when('wc_get_price_decimal_separator')->justReturn($decimalSeparator);
  Functions\when('wc_get_price_thousand_separator')->justReturn($thousandSeparator);
  Functions\when('wc_get_price_decimals')->justReturn($decimals);
  Functions\when('get_woocommerce_price_format')->justReturn($format);
  Functions\when('get_woocommerce_currency_symbol')->justReturn($symbol);
  Functions\when('wc_trim_zeros')->alias(static fn (string $price): string => preg_replace('/' . preg_quote('.', '/') . '0++$/', '', $price) ?? $price);
}

describe('formatPrice null and zero guards', function (): void {
  it('returns null for a null price', function (): void {
    expect(Utils::formatPrice(null))->toBeNull();
  });

  it('returns null for an empty string', function (): void {
    expect(Utils::formatPrice(''))->toBeNull();
  });

  it('returns null for a zero price', function (int|float $price): void {
    stubWooPriceFunctions();
    Filters\expectApplied('woocommerce_price_trim_zeros')->never();

    expect(Utils::formatPrice($price))->toBeNull();
  })->with([0, 0.0]);
});

describe('formatPrice negative values', function (): void {
  it('formats negative prices with a leading minus sign', function (): void {
    stubWooPriceFunctions('.', ',', 2, '%1$s%2$s', '$');
    Filters\expectApplied('woocommerce_price_trim_zeros')->andReturn(false);

    expect(Utils::formatPrice(-1234.5))->toBe('-$1,234.50');
  });
});

describe('formatPrice formatting math', function (): void {
  it('formats across decimal, separator and currency datasets', function (
    float $price,
    string $decimalSeparator,
    string $thousandSeparator,
    int $decimals,
    string $format,
    string $symbol,
    string $expected,
  ): void {
    stubWooPriceFunctions($decimalSeparator, $thousandSeparator, $decimals, $format, $symbol);
    Filters\expectApplied('woocommerce_price_trim_zeros')->andReturn(false);

    expect(Utils::formatPrice($price))->toBe($expected);
  })->with([
    'us dollars'        => [1234.5, '.', ',', 2, '%1$s%2$s', '$', '$1,234.50'],
    'euros suffix'      => [1234.5, ',', '.', 2, '%2$s%1$s', '€', '1.234,50€'],
    'zero decimals'     => [1234.56, '.', ',', 0, '%1$s%2$s', '$', '$1,235'],
    'three decimals'    => [9.1, '.', ' ', 3, '%1$s%2$s', '£', '£9.100'],
    'spaced thousands'  => [1000000.0, ',', ' ', 2, '%1$s%2$s', 'kr', 'kr1 000 000,00'],
  ]);

});

describe('formatPrice trim-zeros branch', function (): void {
  it('trims trailing zeros when the filter enables it and decimals are present', function (): void {
    stubWooPriceFunctions('.', ',', 2, '%1$s%2$s', '$');
    Filters\expectApplied('woocommerce_price_trim_zeros')->andReturn(true);

    expect(Utils::formatPrice(10))->toBe('$10');
  });

  it('does not trim zeros when decimals are zero even if the filter is enabled', function (): void {
    stubWooPriceFunctions('.', ',', 0, '%1$s%2$s', '$');
    Functions\expect('wc_trim_zeros')->never();
    Filters\expectApplied('woocommerce_price_trim_zeros')->andReturn(true);

    expect(Utils::formatPrice(10))->toBe('$10');
  });

  it('keeps trailing zeros when the trim filter is disabled', function (): void {
    stubWooPriceFunctions('.', ',', 2, '%1$s%2$s', '$');
    Functions\expect('wc_trim_zeros')->never();
    Filters\expectApplied('woocommerce_price_trim_zeros')->andReturn(false);

    expect(Utils::formatPrice(10))->toBe('$10.00');
  });
});

describe('formatPrice currency symbol decoding', function (): void {
  it('decodes HTML entities in the currency symbol', function (): void {
    stubWooPriceFunctions('.', ',', 2, '%1$s%2$s', '&pound;');
    Filters\expectApplied('woocommerce_price_trim_zeros')->andReturn(false);

    expect(Utils::formatPrice(5))->toBe('£5.00');
  });
});
