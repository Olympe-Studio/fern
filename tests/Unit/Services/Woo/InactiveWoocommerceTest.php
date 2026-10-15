<?php declare(strict_types=1);

use Fern\Core\Services\Woo\Woocommerce;

/*
|--------------------------------------------------------------------------
| WooCommerce-inactive branches
|--------------------------------------------------------------------------
|
| These cover the `!function_exists('WC')` guards in Woocommerce::locate()
| and Woocommerce::getConfig(). Brain Monkey defines a global WC() the first
| time any test stubs it and PHP cannot undefine it, so this file MUST run
| before any WC stub in the process. Its name sorts ahead of UtilsTest,
| WooCartActionsTest and WoocommerceTest so the guards run WC-free. Do not
| stub WC here.
|
*/

describe('Woocommerce when WooCommerce is inactive', function (): void {
  it('locate() returns a null page and subPage', function (): void {
    expect(function_exists('WC'))->toBeFalse();

    expect(Woocommerce::locate())->toBe(['page' => null, 'subPage' => null]);
  });

  it('getConfig() returns an empty array', function (): void {
    expect(function_exists('WC'))->toBeFalse();

    expect(Woocommerce::getConfig())->toBe([]);
  });
});
