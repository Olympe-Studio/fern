<?php

declare(strict_types=1);

namespace Fern\Core\Services\Woo;

use Exception;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Woo\Utils;
use WC_Cart;

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
          'thousand_separator' => wc_get_price_thousand_separator(),
          'decimal_separator' => wc_get_price_decimal_separator(),
          'price_decimals' => wc_get_price_decimals(),
          'tax_enabled' => wc_tax_enabled(),
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
      $product = wc_get_product($productId);

      if (!$product) {
        throw new Exception('Product not found');
      }

      if ($cartItemKey) {
        $cartItem = $this->getCart()->get_cart_item($cartItemKey);

        if (!$cartItem) {
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

    if (in_array($coupon, $appliedCoupons)) {
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
      $cartItem = $this->getCart()->get_cart_item($cartItemKey);

      if (!$cartItem) {
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
   * @return WC_Cart|never
   */
  private function getCart(): WC_Cart {
    if (!class_exists('WC_Cart')) {
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
   * Format cart data for the frontend
   *
   * @return array<string, mixed>
   */
  protected function formatCartData(): array {
    $cart = $this->getCart();
    $cart->calculate_shipping();
    $cart->calculate_fees();

    $cart->calculate_totals();

    return [
      'items' => array_map(function ($cart_item_key, $cart_item) {
        $product = $cart_item['data'];
        $parent_product = wc_get_product($cart_item['product_id']);

        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();
        $sale_amount = $product->is_on_sale() && $regular_price > 0
          ? round((($regular_price - $sale_price) / $regular_price) * 100)
          : null;

        $item = [
          'key' => $cart_item_key,
          'product_id' => $cart_item['product_id'],
          'variation_id' => $cart_item['variation_id'],
          'id' => $product->get_id(),
          'name' => $product->get_title(),
          'variation' => $cart_item['variation'],
          'short_description' => $product->get_short_description('edit'),
          'quantity' => $cart_item['quantity'],
          'price' => [
            'regular_price' => Utils::formatPrice($regular_price),
            'sale_price' => $product->get_sale_price() ? Utils::formatPrice($sale_price) : null,
            'price' => Utils::formatPrice($product->get_price()),
            'sale_amount' => $sale_amount,
            'is_on_sale' => $product->is_on_sale(),
            'currency' => get_woocommerce_currency(),
          ],
          'subtotal' => Utils::formatPrice($cart_item['line_subtotal']),
          'total' => Utils::formatPrice($cart_item['line_total']),
          'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
        ];

        if ($parent_product && $parent_product->is_type('variable')) {
          /** @var WC_Product_Variable $parent_product */
          $item['productData'] = [
            'variations' => array_map(
              fn($variation) => [
                'id' => $variation['variation_id'],
                'attributes' => $variation['attributes'],
                'price' => [
                  'regular_price' => Utils::formatPrice($variation['regular_price']),
                  'sale_price' => isset($variation['sale_price']) ? Utils::formatPrice($variation['sale_price']) : null,
                  'price' => Utils::formatPrice($variation['display_price']),
                  'sale_amount' => isset($variation['sale_price']) && $variation['regular_price'] > 0
                    ? round((($variation['regular_price'] - $variation['sale_price']) / $variation['regular_price']) * 100)
                    : null,
                  'is_on_sale' => isset($variation['sale_price']) && $variation['sale_price'] !== $variation['regular_price'],
                ],
                'min_quantity' => $variation['min_qty'],
                'max_quantity' => $variation['max_qty'],
                'is_in_stock' => $variation['is_in_stock']
              ],
              $parent_product->get_available_variations()
            ),
            'attributes' => array_reduce(
              array_keys($parent_product->get_variation_attributes()),
              function ($acc, $attribute_name) use ($parent_product) {
                $taxonomy = str_replace('pa_', '', $attribute_name);
                $acc[$taxonomy] = [
                  'name' => wc_attribute_label($attribute_name),
                  'options' => array_map('strtolower', $parent_product->get_variation_attributes()[$attribute_name])
                ];
                return $acc;
              },
              []
            )
          ];
        }

        return $item;
      }, array_keys($cart->get_cart()), $cart->get_cart()),
      'subtotal' => Utils::formatPrice($cart->get_subtotal()),
      'total' => Utils::formatPrice($cart->get_total('')),
      'item_count' => $cart->get_cart_contents_count(),
      'tax_total' => Utils::formatPrice($cart->get_total_tax()),
      'needs_shipping' => $cart->needs_shipping(),
      'shipping_total' => Utils::formatPrice($cart->get_shipping_total()),
    ];
  }
}
