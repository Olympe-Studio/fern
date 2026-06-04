<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Services\Woo\Woocommerce;

function resetWoocommerceStatics(): void {
  $config = new ReflectionProperty(Woocommerce::class, 'config');
  $config->setValue(null, []);

  $strings = new ReflectionProperty(Woocommerce::class, 'strings');
  $strings->setValue(null, []);
}

function stubWooConditionals(array $overrides = []): void {
  $defaults = [
    'is_shop' => false,
    'is_product' => false,
    'is_product_category' => false,
    'is_cart' => false,
    'is_checkout' => false,
    'is_account_page' => false,
  ];

  foreach (array_merge($defaults, $overrides) as $fn => $value) {
    Functions\when($fn)->justReturn($value);
  }
}

function stubFullConfigEnvironment(array $overrides = []): void {
  Functions\when('WC')->justReturn(Mockery::mock('WooCommerce'));

  $funcs = array_merge([
    'get_woocommerce_currency' => 'USD',
    'get_woocommerce_currency_symbol' => '$',
    'wc_get_price_thousand_separator' => ',',
    'wc_get_price_decimal_separator' => '.',
    'wc_get_price_decimals' => 2,
    'wc_tax_enabled' => true,
    'wc_get_cart_url' => 'https://shop.test/cart',
    'wc_get_checkout_url' => 'https://shop.test/checkout',
    'wc_get_account_endpoint_url' => 'https://shop.test/account',
    'wc_get_page_id' => 10,
    'get_permalink' => 'https://shop.test/shop',
  ], $overrides);

  foreach ($funcs as $fn => $value) {
    Functions\when($fn)->justReturn($value);
  }

  $options = [
    'woocommerce_currency_pos' => 'left',
    'woocommerce_calc_taxes' => 'yes',
    'woocommerce_tax_display_shop' => 'incl',
    'woocommerce_tax_display_cart' => 'incl',
    'woocommerce_prices_include_tax' => 'yes',
    'woocommerce_terms_page_id' => 5,
    'woocommerce_store_address' => '1 Test St',
    'woocommerce_store_city' => 'Testville',
    'woocommerce_store_postcode' => '00000',
    'woocommerce_default_country' => 'US:CA',
    'woocommerce_weight_unit' => 'kg',
    'woocommerce_dimension_unit' => 'cm',
    'posts_per_page' => 12,
    'woocommerce_default_catalog_orderby' => 'menu_order',
    'woocommerce_enable_reviews' => 'yes',
    'woocommerce_manage_stock' => 'yes',
    'woocommerce_stock_format' => '',
    'woocommerce_notify_low_stock' => 'yes',
    'woocommerce_notify_no_stock' => 'yes',
    'woocommerce_notify_low_stock_amount' => 2,
    'woocommerce_enable_guest_checkout' => 'yes',
    'woocommerce_enable_checkout_login_reminder' => 'no',
    'woocommerce_enable_signup_and_login_from_checkout' => 'no',
    'woocommerce_enable_myaccount_registration' => 'no',
    'admin_email' => 'admin@shop.test',
    'woocommerce_email_from_name' => 'Shop',
    'woocommerce_email_from_address' => 'shop@shop.test',
    'woocommerce_downloads_require_login' => 'no',
    'woocommerce_downloads_grant_access_after_payment' => 'yes',
    'woocommerce_thumbnail_image_width' => 150,
    'woocommerce_thumbnail_image_height' => 150,
    'woocommerce_thumbnail_cropping' => '1',
    'woocommerce_single_image_width' => 600,
    'woocommerce_single_image_height' => 600,
  ];

  Functions\when('get_option')->alias(static fn (string $key) => $options[$key] ?? '');
}

beforeEach(function (): void {
  resetWoocommerceStatics();
});

describe('locate', function (): void {

  it('maps the shop conditional to the shop page', function (): void {
    Functions\when('WC')->justReturn(Mockery::mock('WooCommerce'));
    stubWooConditionals(['is_shop' => true]);

    expect(Woocommerce::locate())->toBe(['page' => 'shop', 'subPage' => null]);
  });

  it('maps simple conditionals to their page string', function (string $conditional, string $page): void {
    Functions\when('WC')->justReturn(Mockery::mock('WooCommerce'));
    stubWooConditionals([$conditional => true]);

    expect(Woocommerce::locate())->toBe(['page' => $page, 'subPage' => null]);
  })->with([
    'product' => ['is_product', 'product'],
    'product category' => ['is_product_category', 'product-category'],
    'cart' => ['is_cart', 'cart'],
  ]);

  it('returns the checkout endpoint as subPage when present', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('get_current_endpoint')->andReturn('order-pay');
    $wc = Mockery::mock('WooCommerce');
    $wc->query = $query;
    Functions\when('WC')->justReturn($wc);
    stubWooConditionals(['is_checkout' => true]);

    expect(Woocommerce::locate())->toBe(['page' => 'checkout', 'subPage' => 'order-pay']);
  });

  it('returns a null checkout subPage when the endpoint is empty', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('get_current_endpoint')->andReturn('');
    $wc = Mockery::mock('WooCommerce');
    $wc->query = $query;
    Functions\when('WC')->justReturn($wc);
    stubWooConditionals(['is_checkout' => true]);

    expect(Woocommerce::locate())->toBe(['page' => 'checkout', 'subPage' => null]);
  });

  it('returns the account endpoint as subPage when present', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('get_current_endpoint')->andReturn('orders');
    $wc = Mockery::mock('WooCommerce');
    $wc->query = $query;
    Functions\when('WC')->justReturn($wc);
    stubWooConditionals(['is_account_page' => true]);

    expect(Woocommerce::locate())->toBe(['page' => 'my-account', 'subPage' => 'orders']);
  });

  it('falls through to null page when no conditional matches', function (): void {
    Functions\when('WC')->justReturn(Mockery::mock('WooCommerce'));
    stubWooConditionals();

    expect(Woocommerce::locate())->toBe(['page' => null, 'subPage' => null]);
  });
});

describe('getConfig', function (): void {
  it('builds the config from the wc_* and get_option accessors', function (): void {
    stubFullConfigEnvironment();

    $config = Woocommerce::getConfig();

    expect($config)
      ->toHaveKey('currency', 'USD')
      ->toHaveKey('currency_symbol', '$')
      ->toHaveKey('thousand_separator', ',')
      ->toHaveKey('decimal_separator', '.')
      ->toHaveKey('price_decimals', 2)
      ->toHaveKey('tax_enabled', true)
      ->toHaveKey('cart_page_url', 'https://shop.test/cart')
      ->toHaveKey('checkout_page_url', 'https://shop.test/checkout')
      ->toHaveKey('account_page_url', 'https://shop.test/account')
      ->toHaveKey('shop_page_url', 'https://shop.test/shop');

    expect($config['image_sizes'])->toBe([
      'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => '1'],
      'single' => ['width' => 600, 'height' => 600],
    ]);
  });

  it('caches the resolved config so it is built only once', function (): void {
    stubFullConfigEnvironment();

    Filters\expectApplied('fern:woo:config')->once();

    Woocommerce::getConfig();
    Woocommerce::getConfig();
  });

  it('decodes HTML entities in the currency fields', function (): void {
    stubFullConfigEnvironment(['get_woocommerce_currency_symbol' => '&euro;']);

    expect(Woocommerce::getConfig()['currency_symbol'])->toBe('€');
  });

  it('falls back to an empty array when the config filter returns a non-array', function (): void {
    stubFullConfigEnvironment();
    Filters\expectApplied('fern:woo:config')->andReturn('not-an-array');

    expect(Woocommerce::getConfig())->toBe([]);
  });
});

describe('getTexts / getText / getSection', function (): void {
  it('returns the full strings tree with the expected sections', function (): void {
    $texts = Woocommerce::getTexts();

    expect($texts)
      ->toHaveKeys(['general', 'product', 'cart', 'checkout', 'shipping', 'errors', 'success', 'account', 'units', 'taxes'])
      ->and($texts['cart']['add_to_cart'])->toBe('Add to cart');
  });

  it('caches the strings so they are initialised only once', function (): void {
    Filters\expectApplied('fern:woo:texts')->once();

    Woocommerce::getTexts();
    Woocommerce::getTexts();
  });

  it('resolves a string via dot notation', function (): void {
    expect(Woocommerce::getText('cart.empty_cart'))->toBe('Your cart is currently empty.')
      ->and(Woocommerce::getText('errors.invalid_coupon'))->toBe('Invalid coupon.');
  });

  it('returns the default when a dot-notation key is missing', function (): void {
    expect(Woocommerce::getText('cart.does_not_exist', 'fallback'))->toBe('fallback')
      ->and(Woocommerce::getText('missing.path'))->toBeNull();
  });

  it('returns the default when the resolved value is not a string', function (): void {
    expect(Woocommerce::getText('cart', 'fallback'))->toBe('fallback');
  });

  it('returns a whole section as an array', function (): void {
    $cart = Woocommerce::getSection('cart');

    expect($cart)->toBeArray()
      ->and($cart['view_cart'])->toBe('View cart');
  });

  it('returns null for an unknown section', function (): void {
    expect(Woocommerce::getSection('nope'))->toBeNull();
  });

  it('falls back to an empty tree when the texts filter returns a non-array', function (): void {
    Filters\expectApplied('fern:woo:texts')->andReturn('not-an-array');

    expect(Woocommerce::getTexts())->toBe([]);
  });
});
