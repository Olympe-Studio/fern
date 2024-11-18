<?php

declare(strict_types=1);

namespace Fern\Core\Services\Woo;

use Exception;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Woo\Utils;
use Fern\Core\Utils\Types;
use Fern\Core\Wordpress\Filters;
use \WC_Cart;

trait WooCartActions {
  /**
   * Get initial cart and shop states
   */
  public function getInitialState(Request $request): Reply {
    try {
      return new Reply(200, [
        'success' => true,
        'cart' => $this->formatCartData(),
        'config' => [
          'currency' => get_woocommerce_currency(),
          'currency_symbol' => get_woocommerce_currency_symbol(),
          'currency_position' => get_option('woocommerce_currency_pos'),
          'thousand_separator' => \WC_get_price_thousand_separator(),
          'decimal_separator' => \WC_get_price_decimal_separator(),
          'price_decimals' => \WC_get_price_decimals(),
          'tax_enabled' => \WC_tax_enabled(),
          'calc_taxes' => get_option('woocommerce_calc_taxes'),
        ],
      ]);
    } catch (Exception $e) {
      return new Reply(400, [
        'success' => false,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Clear the entire cart
   */
  public function clearCart(Request $request): Reply {
    try {
      $this->getCart()->empty_cart();

      return new Reply(200, [
        'success' => true,
        'message' => 'Cart cleared',
        'cart' => $this->formatCartData(),
      ]);
    } catch (Exception $e) {
      return new Reply(400, [
        'success' => false,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Add a product to cart
   */
  public function addToCart(Request $request): Reply {
    $action = $request->getAction();
    $productId = (int) $action->get('product_id');
    $quantity = (int) ($action->get('quantity') ?? 1);
    $variationId = (int) ($action->get('variation_id') ?? 0);
    $variation = $action->get('variation') ?? [];
    $cartItemKey = $action->get('cart_item_key');

    try {
      $product = Types::getSafeWpValue(\WC_get_product($productId));

      if ($product === null) {
        throw new Exception('Product not found');
      }

      if ($cartItemKey) {
        $cartItem = Types::getSafeWpValue($this->getCart()->get_cart_item($cartItemKey));

        if ($cartItem === null) {
          throw new Exception('Cart item not found');
        }
        // Remove old item
        $this->getCart()->remove_cart_item($cartItemKey);
      }

      // Add the new/modified item
      $newKey = $this->getCart()->add_to_cart(
        $productId,
        $quantity,
        $variationId,
        $variation,
      );

      if (!$newKey) {
        throw new Exception(
          $product->is_type('variable')
            ? 'Failed to add variation to cart'
            : 'Failed to add product to cart',
        );
      }

      return new Reply(200, [
        'success' => true,
        'message' => $cartItemKey ? 'Cart item modified' : 'Item added to cart',
        'cart_item_key' => $newKey,
        'cart' => $this->formatCartData(),
      ]);
    } catch (Exception $e) {
      return new Reply(400, [
        'success' => false,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Apply a coupon to the cart
   */
  public function applyCoupon(Request $request): Reply {
    $action = $request->getAction();
    $coupon = $action->get('coupon');

    $appliedCoupons = $this->getCart()->get_applied_coupons();

    if (!is_array($appliedCoupons) || !in_array($coupon, $appliedCoupons)) {
      return new Reply(400, [
        'success' => false,
        'message' => Woocommerce::getText('errors.already_applied'),
      ]);
    }

    $success = $this->getCart()->apply_coupon($coupon);

    if (!$success) {
      return new Reply(400, [
        'success' => false,
        'message' => Woocommerce::getText('errors.invalid_coupon'),
      ]);
    }

    return new Reply(200, [
      'success' => true,
      'message' => Woocommerce::getText('success.coupon_applied'),
      'cart' => $this->formatCartData(),
    ]);
  }

  /**
   * Remove a coupon from the cart
   */
  public function removeCoupon(Request $request): Reply {
    $action = $request->getAction();
    $coupon = $action->get('coupon');
    $removed = $this->getCart()->remove_coupon($coupon);

    if (!$removed) {
      return new Reply(400, [
        'success' => false,
        'message' => Woocommerce::getText('errors.removing_coupon'),
      ]);
    }

    return new Reply(200, [
      'success' => true,
      'message' => Woocommerce::getText('success.coupon_removed'),
      'cart' => $this->formatCartData(),
    ]);
  }

  /**
   * Remove an item from cart
   */
  public function removeFromCart(Request $request): Reply {
    $action = $request->getAction();
    $cartItemKey = $action->get('cart_item_key');

    if (!$cartItemKey) {
      return new Reply(400, [
        'success' => false,
        'message' => 'Cart item key is required',
      ]);
    }

    try {
      $removed = $this->getCart()->remove_cart_item($cartItemKey);

      if (!$removed) {
        return new Reply(400, [
          'success' => false,
          'message' => 'Failed to remove item from cart',
        ]);
      }

      return new Reply(200, [
        'success' => true,
        'message' => 'Item removed from cart',
        'cart' => $this->formatCartData(),
      ]);
    } catch (Exception $e) {
      return new Reply(400, [
        'success' => false,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Update cart item quantity and/or variation
   *
   * @param Request $request
   * @return Reply
   */
  public function updateCartItem(Request $request): Reply {
    $action = $request->getAction();
    $cartItemKey = $action->get('cart_item_key');
    $quantity = (int) ($action->get('quantity') ?? 0);
    $variationId = (int) ($action->get('variation_id') ?? 0);
    $variation = $action->get('variation') ?? [];
    $productId = (int) $action->get('product_id');

    try {
      $cartItem = Types::getSafeWpValue($this->getCart()->get_cart_item($cartItemKey));

      if ($cartItem === null) {
        throw new Exception('Cart item not found');
      }

      // If only updating quantity, use existing method
      if (!$variationId && empty($variation)) {
        $this->updateCartItemQuantity($request);
        return new Reply(200, [
          'success' => true,
          'message' => 'Cart item updated',
          'cart_item_key' => $cartItemKey,
          'cart' => $this->formatCartData(),
        ]);
      }

      // If updating variation or both
      // First remove the old item
      $this->getCart()->remove_cart_item($cartItemKey);

      // Add the new variation
      $newKey = $this->getCart()->add_to_cart(
        $productId,
        $quantity,
        $variationId,
        $variation,
      );

      if (!$newKey) {
        throw new Exception('Failed to update cart item');
      }

      return new Reply(200, [
        'success' => true,
        'message' => 'Cart item updated',
        'cart_item_key' => $newKey,
        'cart' => $this->formatCartData(),
      ]);
    } catch (Exception $e) {
      return new Reply(400, [
        'success' => false,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Update cart item quantity
   */
  public function updateCartItemQuantity(Request $request): Reply {
    $action = $request->getAction();
    $cartItemKey = $action->get('cart_item_key');
    $quantity = (int) ($action->get('quantity') ?? 0);

    if (!$cartItemKey || $quantity < 0) {
      return new Reply(400, [
        'success' => false,
        'message' => 'Invalid cart item key or quantity',
      ]);
    }

    try {
      $updated = $this->getCart()->set_quantity($cartItemKey, $quantity);

      if (!$updated) {
        return new Reply(400, [
          'success' => false,
          'message' => 'Failed to update cart item quantity',
        ]);
      }

      return new Reply(200, [
        'success' => true,
        'message' => 'Cart item quantity updated',
        'cart' => $this->formatCartData(),
      ]);
    } catch (Exception $e) {
      return new Reply(400, [
        'success' => false,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get current cart contents
   */
  public function getCartContents(Request $request): Reply {
    return new Reply(200, [
      'success' => true,
      'cart' => $this->formatCartData(),
    ]);
  }

  /**
   * Get the WooCommerce cart instance
   *
   * @return \WC_Cart|never
   */
  private function getCart(): \WC_Cart {
    if (!class_exists('\WC_Cart')) {
      $reply = new Reply(400, [
        'success' => false,
        'error' => 'WooCommerce is not installed',
      ]);
      $reply->contentType('application/json');
      $reply->send();
    }

    return WC()->cart;
  }

  /**
   * Format cart data for the frontend with validation
   *
   * @return array<string, mixed>
   */
  protected function formatCartData(): array {
    try {
      $cart = $this->getCart();
      if (!$cart instanceof \WC_Cart) {
        return $this->getEmptyCartData();
      }

      $cart->calculate_shipping();
      $cart->calculate_fees();
      $cart->calculate_totals();

      return [
        'items' => $this->formatCartItems($cart),
        'subtotal' => Utils::formatPrice(Types::getSafeFloat($cart->get_subtotal())),
        'total' => Utils::formatPrice(Types::getSafeFloat($cart->get_total(''))),
        'item_count' => Types::getSafeInt($cart->get_cart_contents_count()),
        'tax_total' => Utils::formatPrice(Types::getSafeFloat($cart->get_total_tax())),
        'needs_shipping' => (bool) $cart->needs_shipping(),
        'shipping_total' => Utils::formatPrice(Types::getSafeFloat($cart->get_shipping_total())),
        'meta_data' => Filters::apply('fern:woo:cart_meta_data', [])
      ];
    } catch (\Exception $e) {
      error_log('Error formatting cart data: ' . $e->getMessage());
      return $this->getEmptyCartData();
    }
  }

  /**
   * Format cart items with validation
   *
   * @param \WC_Cart $cart
   * @return array
   */
  protected function formatCartItems(\WC_Cart $cart): array {
    $cartItems = $cart->get_cart();
    if (!is_array($cartItems)) {
      return [];
    }

    return array_map(function ($cart_item_key, $cart_item) {
      try {
        if (!isset($cart_item['data']) || !is_object($cart_item['data'])) {
          throw new \Exception('Invalid cart item data');
        }

        $product = $cart_item['data'];
        $productId = Types::getSafeInt($cart_item['product_id'] ?? 0);
        $parent_product = $parent_product = $productId ? Types::getSafeWpValue(\WC_get_product($productId)) : null;

        $regular_price = Types::getSafeFloat($product->get_regular_price());
        $sale_price = Types::getSafeFloat($product->get_sale_price());
        $sale_amount = $this->calculateSaleAmount($regular_price, $sale_price, $product->is_on_sale());

        $item = [
          'key' => (string) $cart_item_key,
          'product_id' => $productId,
          'variation_id' => Types::getSafeInt($cart_item['variation_id'] ?? 0),
          'id' => Types::getSafeInt($product->get_id()),
          'name' => Types::getSafeString($product->get_title()),
          'variation' => $this->validateVariationData($cart_item['variation'] ?? []),
          'short_description' => Types::getSafeString($product->get_short_description('edit')),
          'quantity' => Types::getSafeInt($cart_item['quantity'] ?? 1),
          'price' => [
            'regular_price' => Utils::formatPrice(Types::getSafeFloat($regular_price)),
            'sale_price' => $product->get_sale_price() ? Utils::formatPrice($sale_price) : null,
            'price' => Utils::formatPrice(Types::getSafeFloat($product->get_price())),
            'sale_amount' => $sale_amount,
            'is_on_sale' => (bool) $product->is_on_sale(),
            'currency' => Types::getSafeString(get_woocommerce_currency()),
          ],
          'subtotal' => Utils::formatPrice(Types::getSafeFloat($cart_item['line_subtotal'] ?? 0)),
          'total' => Utils::formatPrice(Types::getSafeFloat($cart_item['line_total'] ?? 0)),
          'image' => $this->getProductImage($product),
          'meta_data' => Filters::apply('fern:woo:cart_item_meta_data', [], $product),
        ];

        if ($parent_product !== null && $parent_product?->is_type('variable')) {
          $item['productData'] = $this->getVariableProductData($parent_product);
        }

        return $item;
      } catch (\Exception $e) {
        error_log('Error formatting cart item: ' . $e->getMessage());
        return null;
      }
    }, array_keys($cartItems), $cartItems);
  }

  /**
   * Get variable product data with validation
   *
   * @param \WC_Product_Variable $parent_product
   * @return array
   */
  protected function getVariableProductData(\WC_Product_Variable $parent_product): array {
    try {
      return [
        'variations' => $this->formatVariations($parent_product),
        'attributes' => $this->formatAttributes($parent_product)
      ];
    } catch (\Exception $e) {
      error_log('Error getting variable product data: ' . $e->getMessage());
      return ['variations' => [], 'attributes' => []];
    }
  }

  /**
   * Format variations with validation
   *
   * @param \WC_Product_Variable $parent_product
   * @return array
   */
  protected function formatVariations(\WC_Product_Variable $parent_product): array {
    $variations = $parent_product->get_available_variations();
    if (!is_array($variations)) {
      return [];
    }

    return array_map(function ($variation) {
      try {
        $regular_price = Types::getSafeFloat($variation['regular_price'] ?? 0);
        $sale_price = Types::getSafeFloat($variation['sale_price'] ?? 0);

        return [
          'id' => Types::getSafeInt($variation['variation_id'] ?? 0),
          'attributes' => $this->validateVariationData($variation['attributes'] ?? []),
          'price' => [
            'regular_price' => Utils::formatPrice(Types::getSafeFloat($regular_price)),
            'sale_price' => isset($variation['sale_price']) ? Utils::formatPrice($sale_price) : null,
            'price' => Utils::formatPrice(Types::getSafeFloat($variation['display_price'] ?? 0)),
            'sale_amount' => $this->calculateSaleAmount($regular_price, $sale_price),
            'is_on_sale' => isset($variation['sale_price']) && $variation['sale_price'] !== $variation['regular_price'],
          ],
          'min_quantity' => Types::getSafeInt($variation['min_qty'] ?? 1),
          'max_quantity' => Types::getSafeInt($variation['max_qty'] ?? -1),
          'is_in_stock' => (bool) ($variation['is_in_stock'] ?? false),
          'meta_data' => Filters::apply('fern:woo:cart_item_variation_meta_data', [], $variation),
        ];
      } catch (\Exception $e) {
        error_log('Error formatting variation: ' . $e->getMessage());
        return null;
      }
    }, $variations);
  }

  /**
   * Format attributes with validation
   *
   * @param \WC_Product_Variable $parent_product
   * @return array
   */
  protected function formatAttributes(\WC_Product_Variable $parent_product): array {
    $variation_attributes = Types::getSafeWpValue($parent_product->get_variation_attributes());
    if (!is_array($variation_attributes)) {
      return [];
    }

    return array_reduce(
      array_keys($variation_attributes),
      function ($acc, $attribute_name) use ($variation_attributes) {
        try {
          $taxonomy = Types::getSafeString(str_replace('pa_', '', $attribute_name));
          $options = $variation_attributes[$attribute_name] ?? [];

          if (!is_array($options)) {
            return $acc;
          }

          $acc[$taxonomy] = [
            'name' => Types::getSafeString(\WC_attribute_label($attribute_name)),
            'options' => array_map(
              function ($option) {
                return Types::getSafeString(strtolower($option));
              },
              $options
            )
          ];
          return $acc;
        } catch (\Exception $e) {
          error_log('Error formatting attribute: ' . $e->getMessage());
          return $acc;
        }
      },
      []
    );
  }

  /**
   * Get empty cart data structure
   *
   * @return array
   */
  protected function getEmptyCartData(): array {
    return [
      'items' => [],
      'subtotal' => Utils::formatPrice(0),
      'total' => Utils::formatPrice(0),
      'item_count' => 0,
      'tax_total' => Utils::formatPrice(0),
      'needs_shipping' => false,
      'shipping_total' => Utils::formatPrice(0),
    ];
  }

  /**
   * Validate and sanitize variation data
   *
   * @param array $variation
   * @return array
   */
  protected function validateVariationData(array $variation): array {
    return array_map(function ($value) {
      return Types::getSafeString($value);
    }, $variation);
  }

  /**
   * Calculate sale amount with validation
   *
   * @param float $regular_price
   * @param float $sale_price
   * @param bool $is_on_sale
   * @return int|null
   */
  protected function calculateSaleAmount(float $regular_price, float $sale_price, bool $is_on_sale = true): ?int {
    if ($is_on_sale && $regular_price > 0) {
      return (int) round((($regular_price - $sale_price) / $regular_price) * 100);
    }
    return null;
  }

  /**
   * Get product image with fallback
   *
   * @param \WC_Product $product
   * @return string|null
   */
  protected function getProductImage(\WC_Product $product): ?string {
    $image_id = $product->get_image_id();
    if (!$image_id) {
      return null;
    }
    return Types::getSafeUrl(wp_get_attachment_image_url($image_id, 'thumbnail'));
  }
}
