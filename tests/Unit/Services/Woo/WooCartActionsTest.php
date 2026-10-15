<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Woo\WooCartActions;

/**
 * Concrete host exposing the WooCartActions trait so its protected and private
 * members can be exercised through reflection.
 */
class CartActionsHost {
  use WooCartActions;
}

/**
 * Request double that bypasses the heavy Singleton constructor and returns a
 * caller-controlled Action. Request extends Singleton (final __wakeup) so it
 * cannot be mocked by Mockery; subclassing with a no-op constructor is the
 * reliable way to inject a controlled action.
 */
class WooFakeRequest extends Request {
  public function __construct(private Action $stubAction) {}

  public function getAction(): Action {
    return $this->stubAction;
  }
}

/**
 * Minimal WooCommerce coupon/discount doubles so validateCoupon() can exercise
 * its real branches without a live WooCommerce.
 */
if (!class_exists('WC_Coupon')) {
  class WC_Coupon {
    public function __construct(public string $code = '') {}
  }
}

if (!class_exists('WC_Discounts')) {
  /**
   * WC_Discounts::$nextResult drives is_coupon_valid(): a bool, a WP_Error, or a
   * Throwable (which is rethrown to reach validateCoupon()'s catch path).
   */
  class WC_Discounts {
    /** @var mixed */
    public static $nextResult = true;

    public function __construct(mixed $cart) {}

    public function is_coupon_valid(mixed $coupon): mixed {
      if (self::$nextResult instanceof Throwable) {
        throw self::$nextResult;
      }

      return self::$nextResult;
    }
  }
}

/**
 * Interface exposing set_session so a Mockery cart can report it through
 * method_exists() (Mockery only defines real methods for declared members).
 */
interface WooCartWithSession {
  public function set_session(): void;
}

function cartHost(): CartActionsHost {
  return new CartActionsHost();
}

/**
 * @param array<string, mixed> $args
 */
function wooMakeRequest(array $args): Request {
  $action = Mockery::mock(Action::class);
  $action->shouldReceive('get')->andReturnUsing(static fn (string $key) => $args[$key] ?? null);

  return new WooFakeRequest($action);
}

/**
 * Stubs the WC() accessor so getCart() resolves the provided cart mock.
 */
function stubWcWithCart(Mockery\MockInterface $cart): Mockery\MockInterface {
  $wc = Mockery::mock('WooCommerce');
  $wc->cart = $cart;
  $wc->session = Mockery::mock();
  Functions\when('WC')->justReturn($wc);

  return $cart;
}

function makeCart(): Mockery\MockInterface {
  return Mockery::mock('WC_Cart');
}

/**
 * Invokes a protected/private trait method through reflection.
 */
function invokeHost(CartActionsHost $host, string $method, mixed ...$args): mixed {
  $ref = new ReflectionMethod($host, $method);

  return $ref->invoke($host, ...$args);
}

function priceFuncStubs(): void {
  Functions\when('wc_get_price_decimal_separator')->justReturn('.');
  Functions\when('wc_get_price_thousand_separator')->justReturn(',');
  Functions\when('wc_get_price_decimals')->justReturn(2);
  Functions\when('get_woocommerce_price_format')->justReturn('%1$s%2$s');
  Functions\when('get_woocommerce_currency_symbol')->justReturn('$');
}

/**
 * Builds an empty cart mock wired for calculateCartTotals() and formatCartData()
 * so cart-mutation flows can format their response without a real WooCommerce.
 */
function emptyCalculableCart(): Mockery\MockInterface {
  priceFuncStubs();

  $cart = makeCart();
  $cart->cart_contents = [];
  $cart->shouldReceive('get_cart')->andReturn([]);
  $cart->shouldReceive('calculate_shipping');
  $cart->shouldReceive('calculate_fees');
  $cart->shouldReceive('calculate_totals');
  $cart->shouldReceive('get_subtotal')->andReturn(0.0);
  $cart->shouldReceive('get_total')->andReturn(0.0);
  $cart->shouldReceive('get_cart_contents_count')->andReturn(0);
  $cart->shouldReceive('get_total_tax')->andReturn(0.0);
  $cart->shouldReceive('needs_shipping')->andReturn(false);
  $cart->shouldReceive('get_shipping_total')->andReturn(0.0);

  return $cart;
}

/**
 * Like emptyCalculableCart() but pre-loaded with the given contents so
 * position and reposition bookkeeping has real keys to work with.
 *
 * @param array<string, mixed> $contents
 */
function calculableCartWith(array $contents): Mockery\MockInterface {
  priceFuncStubs();

  $cart = makeCart();
  $cart->cart_contents = $contents;
  $cart->shouldReceive('get_cart')->andReturn($contents);
  $cart->shouldReceive('calculate_shipping');
  $cart->shouldReceive('calculate_fees');
  $cart->shouldReceive('calculate_totals');
  $cart->shouldReceive('get_subtotal')->andReturn(0.0);
  $cart->shouldReceive('get_total')->andReturn(0.0);
  $cart->shouldReceive('get_cart_contents_count')->andReturn(0);
  $cart->shouldReceive('get_total_tax')->andReturn(0.0);
  $cart->shouldReceive('needs_shipping')->andReturn(false);
  $cart->shouldReceive('get_shipping_total')->andReturn(0.0);

  return $cart;
}

afterEach(function (): void {
  (new ReflectionProperty(\Fern\Core\Services\Woo\Woocommerce::class, 'config'))->setValue(null, []);
  WC_Discounts::$nextResult = true;
});

describe('calculateSaleAmount', function (): void {
  it('computes the discount percentage when on sale', function (float $regular, float $sale, int $expected): void {
    expect(invokeHost(cartHost(), 'calculateSaleAmount', $regular, $sale, true))->toBe($expected);
  })->with([
    'half off' => [100.0, 50.0, 50],
    'rounded' => [99.0, 66.0, 33],
    'tiny' => [10.0, 9.5, 5],
  ]);

  it('returns null when not on sale', function (): void {
    expect(invokeHost(cartHost(), 'calculateSaleAmount', 100.0, 50.0, false))->toBeNull();
  });

  it('returns null when the regular price is zero or negative', function (): void {
    expect(invokeHost(cartHost(), 'calculateSaleAmount', 0.0, 0.0, true))->toBeNull();
  });
});

describe('getBatchMessage', function (): void {
  it('reports the right message per success ratio', function (int $success, int $total, string $expected): void {
    expect(invokeHost(cartHost(), 'getBatchMessage', $success, $total))->toBe($expected);
  })->with([
    'none' => [0, 3, 'No items were added to cart'],
    'all' => [3, 3, 'All 3 items added to cart successfully'],
    'partial' => [2, 3, '2 of 3 items added to cart'],
  ]);
});

describe('validateVariationData', function (): void {
  it('coerces every value to a safe string', function (): void {
    $result = invokeHost(cartHost(), 'validateVariationData', [
      'color' => 'red',
      'size' => 42,
      'flag' => true,
    ]);

    expect($result)->toBe(['color' => 'red', 'size' => '42', 'flag' => '1']);
  });

  it('returns an empty array for empty input', function (): void {
    expect(invokeHost(cartHost(), 'validateVariationData', []))->toBe([]);
  });
});

describe('getEmptyCartData', function (): void {
  it('returns a zeroed cart structure', function (): void {
    $data = invokeHost(cartHost(), 'getEmptyCartData');

    expect($data)
      ->toHaveKeys(['items', 'subtotal', 'total', 'item_count', 'tax_total', 'tax_total_numeric', 'needs_shipping', 'shipping_total', 'shipping_total_numeric'])
      ->and($data['items'])->toBe([])
      ->and($data['item_count'])->toBe(0)
      ->and($data['needs_shipping'])->toBeFalse()
      ->and($data['subtotal'])->toBeNull();
  });
});

describe('getNextCartPosition', function (): void {
  it('returns 1.0 for an empty cart', function (): void {
    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([]);

    expect(invokeHost(cartHost(), 'getNextCartPosition', $cart))->toBe(1.0);
  });

  it('returns 1.0 when no item declares a position', function (): void {
    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn(['a' => [], 'b' => []]);

    expect(invokeHost(cartHost(), 'getNextCartPosition', $cart))->toBe(1.0);
  });

  it('returns one past the highest position', function (): void {
    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([
      'a' => ['position' => 2.0],
      'b' => ['position' => 5.0],
    ]);

    expect(invokeHost(cartHost(), 'getNextCartPosition', $cart))->toBe(6.0);
  });
});

describe('ensureCartPositions', function (): void {
  it('assigns sequential positions to items lacking one', function (): void {
    $cart = makeCart();
    $cart->cart_contents = [];
    $cart->shouldReceive('get_cart')->andReturn(['a' => [], 'b' => []]);

    invokeHost(cartHost(), 'ensureCartPositions', $cart);

    expect($cart->cart_contents['a']['position'])->toBe(1.0)
      ->and($cart->cart_contents['b']['position'])->toBe(2.0);
  });

  it('leaves existing positions untouched', function (): void {
    $cart = makeCart();
    $cart->cart_contents = [];
    $cart->shouldReceive('get_cart')->andReturn([
      'a' => ['position' => 3.0],
      'b' => ['position' => 4.0],
    ]);

    invokeHost(cartHost(), 'ensureCartPositions', $cart);

    expect($cart->cart_contents)->toBe([]);
  });

  it('persists positions through set_session when the cart supports it', function (): void {
    $cart = Mockery::mock('WC_Cart, ' . WooCartWithSession::class);
    $cart->cart_contents = [];
    $cart->shouldReceive('get_cart')->andReturn(['a' => []]);
    $cart->shouldReceive('set_session')->once();

    invokeHost(cartHost(), 'ensureCartPositions', $cart);

    expect($cart->cart_contents['a']['position'])->toBe(1.0);
  });
});

describe('repositionCartItem', function (): void {
  it('moves an item to the target index', function (): void {
    $cart = makeCart();
    $contents = [
      'a' => ['position' => 1.0],
      'b' => ['position' => 2.0],
      'c' => ['position' => 3.0],
    ];
    $cart->cart_contents = $contents;
    $cart->shouldReceive('get_cart')->andReturn($contents);

    invokeHost(cartHost(), 'repositionCartItem', $cart, 'c', 0);

    expect(array_keys($cart->cart_contents))->toBe(['c', 'a', 'b']);
  });

  it('does nothing when the item is missing', function (): void {
    $cart = makeCart();
    $cart->cart_contents = ['a' => []];
    $cart->shouldReceive('get_cart')->andReturn(['a' => []]);

    invokeHost(cartHost(), 'repositionCartItem', $cart, 'missing', 0);

    expect(array_keys($cart->cart_contents))->toBe(['a']);
  });

  it('does nothing when the target index is out of bounds', function (): void {
    $cart = makeCart();
    $contents = ['a' => [], 'b' => []];
    $cart->cart_contents = $contents;
    $cart->shouldReceive('get_cart')->andReturn($contents);

    invokeHost(cartHost(), 'repositionCartItem', $cart, 'a', 5);

    expect(array_keys($cart->cart_contents))->toBe(['a', 'b']);
  });

  it('does nothing when the item is already at the target index', function (): void {
    $cart = makeCart();
    $contents = ['a' => [], 'b' => []];
    $cart->cart_contents = $contents;
    $cart->shouldReceive('get_cart')->andReturn($contents);

    invokeHost(cartHost(), 'repositionCartItem', $cart, 'a', 0);

    expect(array_keys($cart->cart_contents))->toBe(['a', 'b']);
  });
});

describe('getProductImage', function (): void {
  it('returns null when the product has no image id', function (): void {
    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('get_image_id')->andReturn(0);

    expect(invokeHost(cartHost(), 'getProductImage', $product))->toBeNull();
  });

  it('resolves the thumbnail url through wp_get_attachment_image_url', function (): void {
    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('get_image_id')->andReturn(99);
    Functions\when('wp_get_attachment_image_url')->justReturn('https://shop.test/img.jpg');

    expect(invokeHost(cartHost(), 'getProductImage', $product))->toBe('https://shop.test/img.jpg');
  });
});

describe('getCart', function (): void {
  it('returns the cart from the WC() container', function (): void {
    $cart = makeCart();
    stubWcWithCart($cart);

    expect(invokeHost(cartHost(), 'getCart'))->toBe($cart);
  });

  it('throws when the WC() cart is not a WC_Cart instance', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);

    expect(fn () => invokeHost(cartHost(), 'getCart'))
      ->toThrow(Exception::class, 'Invalid WooCommerce cart instance');
  });
});

describe('getEcommerceContent', function (): void {
  it('replies with the localized texts', function (): void {
    $reply = cartHost()->getEcommerceContent(wooMakeRequest([]));

    $body = $reply->getBody();

    expect($reply)->toBeInstanceOf(Reply::class)
      ->and($body['success'])->toBeTrue()
      ->and($body['texts'])->toHaveKey('cart');
  });
});

describe('getInitialState', function (): void {
  it('replies with cart and config on success', function (): void {
    stubWcWithCart(emptyCalculableCart());
    (new ReflectionProperty(\Fern\Core\Services\Woo\Woocommerce::class, 'config'))
      ->setValue(null, ['currency' => 'USD']);

    $reply = cartHost()->getInitialState(wooMakeRequest([]));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['cart']['items'])->toBe([])
      ->and($body['cart']['item_count'])->toBe(0)
      ->and($body['config'])->toBe(['currency' => 'USD']);
  });

  it('replies 400 when the cart cannot be resolved', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);

    $reply = cartHost()->getInitialState(wooMakeRequest([]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Invalid WooCommerce cart instance');
  });
});

describe('clearCart', function (): void {
  it('empties the cart and returns the formatted result', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('empty_cart')->once();
    stubWcWithCart($cart);

    $reply = cartHost()->clearCart(wooMakeRequest([]));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Cart cleared');
  });

  it('replies 400 when resetting the cart throws', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);

    $reply = cartHost()->clearCart(wooMakeRequest([]));

    expect($reply->getBody()['success'])->toBeFalse();
  });
});

describe('getCartContents', function (): void {
  it('recalculates and returns the cart data', function (): void {
    stubWcWithCart(emptyCalculableCart());

    $reply = cartHost()->getCartContents(wooMakeRequest([]));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['cart']['item_count'])->toBe(0);
  });
});

describe('addToCart', function (): void {
  it('adds a new product and assigns a position', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('add_to_cart')->once()->andReturn('newkey');
    stubWcWithCart($cart);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('is_type')->andReturn(false);
    Functions\when('wc_get_product')->justReturn($product);

    $reply = cartHost()->addToCart(wooMakeRequest(['product_id' => 42, 'quantity' => 2]));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['message'])->toBe('Item added to cart')
      ->and($body['cart_item_key'])->toBe('newkey')
      ->and($cart->cart_contents['newkey']['position'])->toBe(1.0);
  });

  it('replies 400 when the product does not exist', function (): void {
    stubWcWithCart(emptyCalculableCart());
    Functions\when('wc_get_product')->justReturn(false);

    $reply = cartHost()->addToCart(wooMakeRequest(['product_id' => 999]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Product not found');
  });

  it('replies 400 with a variable-product message when add_to_cart fails', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('add_to_cart')->andReturn(false);
    stubWcWithCart($cart);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('is_type')->with('variable')->andReturn(true);
    Functions\when('wc_get_product')->justReturn($product);

    $reply = cartHost()->addToCart(wooMakeRequest(['product_id' => 42]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Failed to add variation to cart');
  });

  it('replies 400 when the referenced cart item is missing', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('gone')->andReturn(false);
    stubWcWithCart($cart);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('is_type')->andReturn(false);
    Functions\when('wc_get_product')->justReturn($product);

    $reply = cartHost()->addToCart(wooMakeRequest([
      'product_id' => 42,
      'cart_item_key' => 'gone',
    ]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Cart item not found');
  });

  it('modifies an existing line and preserves its position', function (): void {
    $cart = calculableCartWith(['abc' => ['position' => 2.0]]);
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 2.0]);
    $cart->shouldReceive('remove_cart_item')->with('abc');
    $cart->shouldReceive('add_to_cart')->andReturn('newkey');
    stubWcWithCart($cart);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('is_type')->andReturn(false);
    Functions\when('wc_get_product')->justReturn($product);

    $reply = cartHost()->addToCart(wooMakeRequest([
      'product_id' => 42,
      'quantity' => 1,
      'cart_item_key' => 'abc',
    ]));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['message'])->toBe('Cart item modified')
      ->and($body['cart_item_key'])->toBe('newkey')
      ->and($cart->cart_contents['newkey']['position'])->toBe(2.0);
  });
});

describe('batchAddToCart', function (): void {
  it('rejects an empty items array', function (): void {
    $reply = cartHost()->batchAddToCart(wooMakeRequest(['items' => []]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Items array is required and cannot be empty');
  });

  it('processes a mix of valid and invalid items', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('add_to_cart')->andReturn('k1');
    stubWcWithCart($cart);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('is_type')->andReturn(false);
    Functions\when('wc_get_product')->justReturn($product);

    $reply = cartHost()->batchAddToCart(wooMakeRequest(['items' => [
      ['product_id' => 1, 'quantity' => 1],
      ['product_id' => 0],
    ]]));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['results'])->toHaveCount(2)
      ->and($body['results'][0]['success'])->toBeTrue()
      ->and($body['results'][1]['success'])->toBeFalse()
      ->and($body['results'][1]['message'])->toBe('Invalid product ID')
      ->and($body['message'])->toBe('1 of 2 items added to cart');
  });

  it('replies 400 when the cart cannot be resolved', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);

    $reply = cartHost()->batchAddToCart(wooMakeRequest(['items' => [['product_id' => 1]]]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Invalid WooCommerce cart instance');
  });

  it('reports product-not-found for a missing product', function (): void {
    stubWcWithCart(emptyCalculableCart());
    Functions\when('wc_get_product')->justReturn(false);

    $reply = cartHost()->batchAddToCart(wooMakeRequest(['items' => [['product_id' => 7]]]));
    $body = $reply->getBody();

    expect($body['success'])->toBeFalse()
      ->and($body['results'][0]['success'])->toBeFalse()
      ->and($body['results'][0]['message'])->toBe('Product not found');
  });

  it('reports an add failure for a variable product', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('add_to_cart')->andReturn(false);
    stubWcWithCart($cart);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('is_type')->with('variable')->andReturn(true);
    Functions\when('wc_get_product')->justReturn($product);

    $reply = cartHost()->batchAddToCart(wooMakeRequest(['items' => [['product_id' => 8]]]));
    $body = $reply->getBody();

    expect($body['results'][0]['success'])->toBeFalse()
      ->and($body['results'][0]['message'])->toBe('Failed to add variation to cart');
  });
});

describe('removeFromCart', function (): void {
  it('rejects a missing cart item key', function (): void {
    $reply = cartHost()->removeFromCart(wooMakeRequest([]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Cart item key is required');
  });

  it('removes the item on success', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('remove_cart_item')->with('abc')->andReturn(true);
    stubWcWithCart($cart);

    $reply = cartHost()->removeFromCart(wooMakeRequest(['cart_item_key' => 'abc']));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Item removed from cart');
  });

  it('replies 400 when removal fails', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('remove_cart_item')->andReturn(false);
    stubWcWithCart($cart);

    $reply = cartHost()->removeFromCart(wooMakeRequest(['cart_item_key' => 'abc']));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Failed to remove item from cart');
  });

  it('replies 400 when the cart cannot be resolved', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);

    $reply = cartHost()->removeFromCart(wooMakeRequest(['cart_item_key' => 'abc']));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Invalid WooCommerce cart instance');
  });
});

describe('updateCartItemQuantity', function (): void {
  it('rejects an invalid key or negative quantity', function (): void {
    $reply = cartHost()->updateCartItemQuantity(wooMakeRequest(['cart_item_key' => '', 'quantity' => -1]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Invalid cart item key or quantity');
  });

  it('resets the cart when the item key is unknown', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('ghost')->andReturn(false);
    $cart->shouldReceive('empty_cart');
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItemQuantity(wooMakeRequest(['cart_item_key' => 'ghost', 'quantity' => 2]));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Cart has been reset');
  });

  it('updates the quantity on success', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 1.0]);
    $cart->shouldReceive('set_quantity')->with('abc', 3)->andReturn(true);
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItemQuantity(wooMakeRequest(['cart_item_key' => 'abc', 'quantity' => 3]));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Cart item quantity updated');
  });

  it('replies 400 when the quantity update fails', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 1.0]);
    $cart->shouldReceive('set_quantity')->andReturn(false);
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItemQuantity(wooMakeRequest(['cart_item_key' => 'abc', 'quantity' => 3]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Failed to update cart item quantity');
  });

  it('repositions the line when its previous index is known', function (): void {
    $cart = calculableCartWith(['abc' => ['position' => 1.0]]);
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 1.0]);
    $cart->shouldReceive('set_quantity')->with('abc', 3)->andReturn(true);
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItemQuantity(wooMakeRequest([
      'cart_item_key' => 'abc',
      'quantity' => 3,
    ]));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Cart item quantity updated');
  });

  it('replies 400 when the cart cannot be resolved', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);

    $reply = cartHost()->updateCartItemQuantity(wooMakeRequest([
      'cart_item_key' => 'abc',
      'quantity' => 3,
    ]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Invalid WooCommerce cart instance');
  });
});

describe('updateCartItem', function (): void {
  it('resets the cart when the item key is unknown', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('ghost')->andReturn(false);
    $cart->shouldReceive('empty_cart');
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItem(wooMakeRequest(['cart_item_key' => 'ghost']));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Cart has been reset');
  });

  it('delegates to a quantity-only update when no variation is supplied', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 1.0]);
    $cart->shouldReceive('set_quantity')->andReturn(true);
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItem(wooMakeRequest([
      'cart_item_key' => 'abc',
      'quantity' => 2,
      'variation_id' => 0,
    ]));

    expect($reply->getBody()['success'])->toBeTrue()
      ->and($reply->getBody()['message'])->toBe('Cart item updated')
      ->and($reply->getBody()['cart_item_key'])->toBe('abc');
  });

  it('replaces the line when a variation is supplied', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 2.0]);
    $cart->shouldReceive('remove_cart_item')->with('abc');
    $cart->shouldReceive('add_to_cart')->andReturn('newkey');
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItem(wooMakeRequest([
      'cart_item_key' => 'abc',
      'quantity' => 1,
      'variation_id' => 7,
      'product_id' => 42,
      'variation' => ['pa_color' => 'red'],
    ]));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['cart_item_key'])->toBe('newkey')
      ->and($cart->cart_contents['newkey']['position'])->toBe(2.0);
  });

  it('assigns an index-based position when the line had none', function (): void {
    $cart = calculableCartWith(['abc' => ['quantity' => 1]]);
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['quantity' => 1]);
    $cart->shouldReceive('remove_cart_item')->with('abc');
    $cart->shouldReceive('add_to_cart')->andReturn('newkey');
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItem(wooMakeRequest([
      'cart_item_key' => 'abc',
      'quantity' => 1,
      'variation_id' => 7,
      'product_id' => 42,
      'variation' => ['pa_color' => 'red'],
    ]));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['cart_item_key'])->toBe('newkey')
      ->and($cart->cart_contents['newkey']['position'])->toBe(0.001);
  });

  it('replies 400 when the replacement add fails', function (): void {
    $cart = calculableCartWith(['abc' => ['position' => 2.0]]);
    $cart->shouldReceive('get_cart_item')->with('abc')->andReturn(['position' => 2.0]);
    $cart->shouldReceive('remove_cart_item')->with('abc');
    $cart->shouldReceive('add_to_cart')->andReturn(false);
    stubWcWithCart($cart);

    $reply = cartHost()->updateCartItem(wooMakeRequest([
      'cart_item_key' => 'abc',
      'quantity' => 1,
      'variation_id' => 7,
      'product_id' => 42,
      'variation' => ['pa_color' => 'red'],
    ]));

    expect($reply->getBody()['success'])->toBeFalse()
      ->and($reply->getBody()['message'])->toBe('Failed to update cart item');
  });
});

describe('applyCoupon', function (): void {
  beforeEach(function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
  });

  it('applies a new coupon and recalculates totals', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_applied_coupons')->andReturn([]);
    $cart->shouldReceive('apply_coupon')->with('save10')->andReturn(true);
    stubWcWithCart($cart);

    $reply = cartHost()->applyCoupon(wooMakeRequest(['coupon' => 'save10']));

    expect($reply->getBody()['success'])->toBeTrue();
  });

  it('rejects a coupon that is already applied without re-applying it', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_applied_coupons')->andReturn(['save10']);
    stubWcWithCart($cart);

    $reply = cartHost()->applyCoupon(wooMakeRequest(['coupon' => 'save10']));

    expect($reply->toArray()['status'])->toBe(400)
      ->and($reply->getBody()['success'])->toBeFalse();
  });

  it('replies 400 when applying the coupon fails', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('get_applied_coupons')->andReturn([]);
    $cart->shouldReceive('apply_coupon')->andReturn(false);
    stubWcWithCart($cart);

    $reply = cartHost()->applyCoupon(wooMakeRequest(['coupon' => 'save10']));

    expect($reply->getBody()['success'])->toBeFalse();
  });
});

describe('removeCoupon', function (): void {
  beforeEach(function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
  });

  it('removes a coupon on success', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('remove_coupon')->with('save10')->andReturn(true);
    stubWcWithCart($cart);

    $reply = cartHost()->removeCoupon(wooMakeRequest(['coupon' => 'save10']));

    expect($reply->getBody()['success'])->toBeTrue();
  });

  it('replies 400 when removal fails', function (): void {
    $cart = emptyCalculableCart();
    $cart->shouldReceive('remove_coupon')->andReturn(false);
    stubWcWithCart($cart);

    $reply = cartHost()->removeCoupon(wooMakeRequest(['coupon' => 'save10']));

    expect($reply->getBody()['success'])->toBeFalse();
  });
});

describe('validateCoupon', function (): void {
  beforeEach(function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
  });

  it('returns invalid for an empty coupon code', function (): void {
    Functions\when('sanitize_text_field')->returnArg();

    $reply = cartHost()->validateCoupon(wooMakeRequest(['coupon' => '']));
    $body = $reply->getBody();

    expect($body['success'])->toBeFalse()
      ->and($body['valid'])->toBeFalse();
  });

  it('returns valid for a coupon that passes discount validation', function (): void {
    Functions\when('sanitize_text_field')->returnArg();
    WC_Discounts::$nextResult = true;
    stubWcWithCart(makeCart());

    $reply = cartHost()->validateCoupon(wooMakeRequest(['coupon' => 'SAVE10']));
    $body = $reply->getBody();

    expect($body['success'])->toBeTrue()
      ->and($body['valid'])->toBeTrue();
  });

  it('returns the WP_Error message when the coupon is invalid', function (): void {
    Functions\when('sanitize_text_field')->returnArg();
    WC_Discounts::$nextResult = new WP_Error('invalid_coupon', 'Coupon expired');
    stubWcWithCart(makeCart());

    $reply = cartHost()->validateCoupon(wooMakeRequest(['coupon' => 'EXPIRED']));
    $body = $reply->getBody();

    expect($body['success'])->toBeFalse()
      ->and($body['valid'])->toBeFalse()
      ->and($body['message'])->toBe('Coupon expired');
  });

  it('returns invalid when discount validation throws', function (): void {
    Functions\when('sanitize_text_field')->returnArg();
    WC_Discounts::$nextResult = new Exception('discount boom');
    stubWcWithCart(makeCart());

    $reply = cartHost()->validateCoupon(wooMakeRequest(['coupon' => 'BOOM']));
    $body = $reply->getBody();

    expect($body['success'])->toBeFalse()
      ->and($body['valid'])->toBeFalse()
      ->and($body['message'])->toBe('discount boom');
  });
});

describe('formatCartData', function (): void {
  it('returns empty cart data when the cart cannot be resolved', function (): void {
    $wc = Mockery::mock('WooCommerce');
    $wc->cart = null;
    Functions\when('WC')->justReturn($wc);
    priceFuncStubs();

    $result = invokeHost(cartHost(), 'formatCartData');

    expect($result['items'])->toBe([])
      ->and($result['item_count'])->toBe(0);
  });

  it('formats numeric totals from the cart', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
    priceFuncStubs();

    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([]);
    $cart->shouldReceive('get_subtotal')->andReturn(50.0);
    $cart->shouldReceive('get_total')->andReturn(60.0);
    $cart->shouldReceive('get_cart_contents_count')->andReturn(3);
    $cart->shouldReceive('get_total_tax')->andReturn(5.0);
    $cart->shouldReceive('needs_shipping')->andReturn(true);
    $cart->shouldReceive('get_shipping_total')->andReturn(10.0);
    stubWcWithCart($cart);

    $result = invokeHost(cartHost(), 'formatCartData');

    expect($result['item_count'])->toBe(3)
      ->and($result['tax_total_numeric'])->toBe(5.0)
      ->and($result['needs_shipping'])->toBeTrue()
      ->and($result['shipping_total_numeric'])->toBe(10.0)
      ->and($result['subtotal'])->toBe('$50.00');
  });
});

describe('formatCartItems', function (): void {
  it('returns an empty list for an empty cart', function (): void {
    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([]);

    expect(invokeHost(cartHost(), 'formatCartItems', $cart))->toBe([]);
  });

  it('skips cart items whose data is invalid', function (): void {
    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([
      'bad' => ['position' => 1.0],
    ]);

    expect(invokeHost(cartHost(), 'formatCartItems', $cart))->toBe([]);
  });

  it('formats a simple cart line', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
    Functions\when('get_woocommerce_currency')->justReturn('USD');
    Functions\when('wc_get_product')->justReturn(null);
    priceFuncStubs();

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('get_regular_price')->andReturn(100.0);
    $product->shouldReceive('get_sale_price')->andReturn(80.0);
    $product->shouldReceive('is_on_sale')->andReturn(true);
    $product->shouldReceive('get_id')->andReturn(42);
    $product->shouldReceive('get_title')->andReturn('Widget');
    $product->shouldReceive('get_short_description')->andReturn('A widget');
    $product->shouldReceive('get_sku')->andReturn('SKU1');
    $product->shouldReceive('get_price')->andReturn(80.0);
    $product->shouldReceive('get_image_id')->andReturn(0);

    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([
      'k1' => [
        'data' => $product,
        'product_id' => 42,
        'variation_id' => 0,
        'quantity' => 2,
        'position' => 1.0,
        'line_subtotal' => 200.0,
        'line_total' => 160.0,
        'line_tax' => 16.0,
        'variation' => [],
      ],
    ]);

    $items = invokeHost(cartHost(), 'formatCartItems', $cart);

    expect($items)->toHaveCount(1)
      ->and($items[0]['name'])->toBe('Widget')
      ->and($items[0]['quantity'])->toBe(2)
      ->and($items[0]['price']['sale_amount'])->toBe(20)
      ->and($items[0]['price']['is_on_sale'])->toBeTrue()
      ->and($items[0]['subtotal'])->toBe('$200.00');
  });

  it('returns an empty list when get_cart is not an array', function (): void {
    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn(null);

    expect(invokeHost(cartHost(), 'formatCartItems', $cart))->toBe([]);
  });

  it('includes productData for a variable parent product', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
    Functions\when('get_woocommerce_currency')->justReturn('USD');
    priceFuncStubs();

    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('is_type')->with('variable')->andReturn(true);
    $parent->shouldReceive('get_available_variations')->andReturn([]);
    $parent->shouldReceive('get_variation_attributes')->andReturn([]);
    Functions\when('wc_get_product')->justReturn($parent);

    $product = Mockery::mock('WC_Product');
    $product->shouldReceive('get_regular_price')->andReturn(100.0);
    $product->shouldReceive('get_sale_price')->andReturn(0.0);
    $product->shouldReceive('is_on_sale')->andReturn(false);
    $product->shouldReceive('get_id')->andReturn(7);
    $product->shouldReceive('get_title')->andReturn('Variant');
    $product->shouldReceive('get_short_description')->andReturn('');
    $product->shouldReceive('get_sku')->andReturn('SKU7');
    $product->shouldReceive('get_price')->andReturn(100.0);
    $product->shouldReceive('get_image_id')->andReturn(0);

    $cart = makeCart();
    $cart->shouldReceive('get_cart')->andReturn([
      'k1' => [
        'data' => $product,
        'product_id' => 42,
        'variation_id' => 7,
        'quantity' => 1,
        'position' => 1.0,
        'line_subtotal' => 100.0,
        'line_total' => 100.0,
        'line_tax' => 0.0,
        'variation' => [],
      ],
    ]);

    $items = invokeHost(cartHost(), 'formatCartItems', $cart);

    expect($items)->toHaveCount(1)
      ->and($items[0])->toHaveKey('productData')
      ->and($items[0]['productData'])->toBe(['variations' => [], 'attributes' => []]);
  });
});

describe('formatAttributes', function (): void {
  it('reduces variation attributes into a keyed structure', function (): void {
    Functions\when('WC_attribute_label')->alias(static fn (string $name): string => ucfirst(str_replace('pa_', '', $name)));

    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_variation_attributes')->andReturn([
      'pa_color' => ['Red', 'Blue'],
    ]);

    $result = invokeHost(cartHost(), 'formatAttributes', $parent);

    expect($result)->toHaveKey('color')
      ->and($result['color']['name'])->toBe('Color')
      ->and($result['color']['options'])->toBe(['red', 'blue']);
  });

  it('returns an empty array when there are no variation attributes', function (): void {
    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_variation_attributes')->andReturn([]);

    expect(invokeHost(cartHost(), 'formatAttributes', $parent))->toBe([]);
  });

  it('skips an attribute whose options are not an array', function (): void {
    Functions\when('WC_attribute_label')->justReturn('Color');

    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_variation_attributes')->andReturn([
      'pa_color' => 'not-an-array',
    ]);

    expect(invokeHost(cartHost(), 'formatAttributes', $parent))->toBe([]);
  });

  it('skips an attribute when the label lookup throws', function (): void {
    Functions\when('WC_attribute_label')->alias(static function (): string {
      throw new Exception('label boom');
    });

    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_variation_attributes')->andReturn([
      'pa_color' => ['Red', 'Blue'],
    ]);

    expect(invokeHost(cartHost(), 'formatAttributes', $parent))->toBe([]);
  });
});

describe('formatVariations', function (): void {
  it('formats available variations', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value = null) => $value);
    priceFuncStubs();

    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_available_variations')->andReturn([
      [
        'variation_id' => 7,
        'attributes' => ['attribute_pa_color' => 'red'],
        'regular_price' => 100.0,
        'sale_price' => 80.0,
        'display_price' => 80.0,
        'min_qty' => 1,
        'max_qty' => 5,
        'sku' => 'V7',
        'is_in_stock' => true,
      ],
    ]);

    $result = invokeHost(cartHost(), 'formatVariations', $parent);

    expect($result)->toHaveCount(1)
      ->and($result[0]['id'])->toBe(7)
      ->and($result[0]['price']['is_on_sale'])->toBeTrue()
      ->and($result[0]['price']['sale_amount'])->toBe(20)
      ->and($result[0]['is_in_stock'])->toBeTrue();
  });

  it('returns an empty array when no variations are available', function (): void {
    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_available_variations')->andReturn(false);

    expect(invokeHost(cartHost(), 'formatVariations', $parent))->toBe([]);
  });

  it('returns null for a variation whose formatting throws', function (): void {
    Functions\when('apply_filters')->alias(static function (string $hook, mixed $value = null) {
      if ($hook === 'fern:woo:cart_item_variation_meta_data') {
        throw new Exception('variation meta boom');
      }

      return $value;
    });
    priceFuncStubs();

    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_available_variations')->andReturn([
      ['variation_id' => 7, 'regular_price' => 100.0, 'sale_price' => 80.0, 'display_price' => 80.0],
    ]);

    expect(invokeHost(cartHost(), 'formatVariations', $parent))->toBe([null]);
  });
});

describe('getVariableProductData', function (): void {
  it('returns empty structures when the parent product throws', function (): void {
    $parent = Mockery::mock('WC_Product_Variable');
    $parent->shouldReceive('get_available_variations')->andThrow(new Exception('boom'));
    $parent->shouldReceive('get_variation_attributes')->andThrow(new Exception('boom'));

    $result = invokeHost(cartHost(), 'getVariableProductData', $parent);

    expect($result)->toBe(['variations' => [], 'attributes' => []]);
  });
});

describe('controlTaxCalculation', function (): void {
  it('toggles the tax filter around the callback when calculation is disabled', function (): void {
    $ran = false;
    invokeHost(cartHost(), 'controlTaxCalculation', false, function () use (&$ran): void {
      $ran = true;
    });

    expect($ran)->toBeTrue();
  });
});
