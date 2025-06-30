<?php

declare(strict_types=1);

namespace Fern\Core\Services\Woo;

use \WC_Cart;
use Exception;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Utils\Types;
use Fern\Core\Wordpress\Filters;
use WC_Product;
use WC_Product_Variable;

/**
 * Helper actions for Woo cart manipulation.
 *
 * @phpstan-ignore-next-line trait.unused This trait may be consumed dynamically in application code.
 */
trait WooCartActions {
  /**
   * Get the ecommerce content
   */
  public function getEcommerceContent(Request $_): Reply {
    return new Reply(200, [
      'success' => true,
      'texts' => Woocommerce::getTexts(),
    ]);
  }

  /**
   * Get initial cart and shop states
   */
  public function getInitialState(Request $_): Reply {
    try {
      $this->calculateCartTotals();

      return new Reply(200, [
        'success' => true,
        'cart' => $this->formatCartData(),
        'config' => Woocommerce::getConfig(),
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
  public function clearCart(Request $_): Reply {
    try {
      $this->resetCart();

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
      $product = Types::getSafeWpValue(wc_get_product($productId));
      $cart = $this->getCart();

      if ($product === null) {
        throw new Exception('Product not found');
      }

      $oldIndex = false;
      $oldPosition = null;
      if ($cartItemKey) {
        $cartItem = Types::getSafeWpValue($cart->get_cart_item($cartItemKey));

        if ($cartItem === null) {
          throw new Exception('Cart item not found');
        }

        $oldIndex = array_search($cartItemKey, array_keys($cart->get_cart()), true);
        $oldPosition = $cartItem['position'] ?? null;
        $cart->remove_cart_item($cartItemKey);
      }

      // Make sure all existing items already have a valid position so that the
      // next position we compute for the new item is actually greater than
      // every other one.
      $this->ensureCartPositions($cart);

      // Add the new/modified item
      $newKey = $cart->add_to_cart(
        $productId,
        $quantity,
        $variationId,
        $variation,
      );

      if ($newKey) {
        // Store the insertion order for stable sorting
        if ($cartItemKey && $oldPosition !== null) {
          // Preserve position when modifying existing item
          $cart->cart_contents[$newKey]['position'] = $oldPosition;
        } else {
          // Assign new position for new items
          $cart->cart_contents[$newKey]['position'] = $this->getNextCartPosition($cart);
        }
      }

      if ($cartItemKey && $newKey && $oldIndex !== false) {
        $this->repositionCartItem($cart, $newKey, $oldIndex);
      }

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
   * Add multiple products to cart in batch
   *
   * @param Request $request Contains array of items with product_id, quantity, variation_id, variation
   * @return Reply Response with success status and batch operation results
   */
  public function batchAddToCart(Request $request): Reply {
    $action = $request->getAction();
    $items = $action->get('items') ?? [];

    if (!is_array($items) || empty($items)) {
      return new Reply(400, [
        'success' => false,
        'message' => 'Items array is required and cannot be empty',
      ]);
    }

    try {
      $results = $this->processBatchItems($items);
      $successCount = count(array_filter($results, fn($item) => $item['success']));
      $totalCount = count($results);

      return new Reply(200, [
        'success' => $successCount > 0,
        'message' => $this->getBatchMessage($successCount, $totalCount),
        'results' => $results,
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

    if (!is_array($appliedCoupons) || !in_array($coupon, $appliedCoupons, true)) {
      return new Reply(400, [
        'success' => false,
        'message' => Woocommerce::getText('errors.already_applied'),
      ]);
    }

    $success = $this->getCart()->apply_coupon($coupon);
    $this->calculateCartTotals();

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
    $this->calculateCartTotals();

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
      $this->calculateCartTotals();

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
   */
  public function updateCartItem(Request $request): Reply {
    $action = $request->getAction();
    $cartItemKey = $action->get('cart_item_key');
    $quantity = (int) ($action->get('quantity') ?? 0);
    $variationId = (int) ($action->get('variation_id') ?? 0);
    $productId = (int) $action->get('product_id');
    $variation = $action->get('variation');

    try {
      // Ensure the cart item exists before attempting any WooCommerce mutation to avoid PHP warnings
      $cart = $this->getCart();
      $existingCartItem = Types::getSafeWpValue($cart->get_cart_item($cartItemKey));

      if ($existingCartItem === null) {
        // Invalid key – reset the cart to avoid inconsistent state
        $this->resetCart();

        return new Reply(200, [
          'success' => true,
          'message' => 'Cart has been reset',
          'cart' => $this->formatCartData(),
        ]);
      }

      if ($variationId === 0 && empty($variation)) {
        $this->updateCartItemQuantity($request);

        return new Reply(200, [
          'success' => true,
          'message' => 'Cart item updated',
          'cart_item_key' => $cartItemKey,
          'cart' => $this->formatCartData(),
        ]);
      }

      $oldIndex = array_search($cartItemKey, array_keys($cart->get_cart()), true);

      // Preserve the position value from the existing item
      $oldPosition = $existingCartItem['position'] ?? null;

      $cart->remove_cart_item($cartItemKey);

      $newKey = $cart->add_to_cart(
        $productId,
        $quantity,
        $variationId,
        $variation,
      );

      if ($newKey) {
        // Restore the original position if it existed
        if ($oldPosition !== null) {
          $cart->cart_contents[$newKey]['position'] = $oldPosition;
        } else {
          // If no position existed, assign based on the old index
          $cart->cart_contents[$newKey]['position'] = 0.001 + ($oldIndex * 0.001);
        }
      }

      if ($newKey && $oldIndex !== false) {
        $this->repositionCartItem($cart, $newKey, $oldIndex);
      }

      $this->calculateCartTotals();

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
      // Ensure the cart item exists before attempting any WooCommerce mutation to avoid PHP warnings
      $cart = $this->getCart();
      $existingCartItem = Types::getSafeWpValue($cart->get_cart_item($cartItemKey));

      if ($existingCartItem === null) {
        // Invalid key – reset the cart to avoid inconsistent state
        $this->resetCart();

        return new Reply(200, [
          'success' => true,
          'message' => 'Cart has been reset',
          'cart' => $this->formatCartData(),
        ]);
      }

      // Preserve original order by memorising the index before the update
      $oldIndex = array_search($cartItemKey, array_keys($cart->get_cart()), true);

      $updated = $cart->set_quantity($cartItemKey, $quantity);

      // If WooCommerce internally moved the line we restore it at its previous position
      if ($updated && $oldIndex !== false) {
        $this->repositionCartItem($cart, $cartItemKey, $oldIndex);
      }

      $this->calculateCartTotals();

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
   * Reinsert a cart item at a specific index
   *
   * @param WC_Cart $cart       The WooCommerce cart instance
   * @param string  $itemKey    The cart item key to move
   * @param int     $targetIndex The position where the item must be placed
   */
  private function repositionCartItem(WC_Cart $cart, string $itemKey, int $targetIndex): void {
    $contents = $cart->get_cart();

    if (!isset($contents[$itemKey])) {
      return;
    }

    $keys = array_keys($contents);
    $count = count($keys);

    // If target index is out of current bounds, no reposition needed (item already at the end)
    if ($targetIndex >= $count) {
      return;
    }

    // If already in the right spot, nothing to do.
    if ($keys[$targetIndex] === $itemKey) {
      return;
    }

    $item = $contents[$itemKey];
    unset($contents[$itemKey]);

    $prefix = array_slice($contents, 0, $targetIndex, true);
    $suffix = array_slice($contents, $targetIndex, null, true);

    $cart->cart_contents = $prefix + [$itemKey => $item] + $suffix;
  }

  /**
   * Get current cart contents
   */
  public function getCartContents(Request $request): Reply {
    $this->calculateCartTotals();

    return new Reply(200, [
      'success' => true,
      'cart' => $this->formatCartData(),
    ]);
  }

  /**
   * Format cart data for the frontend with validation
   *
   * @return array<string, mixed>
   */
  protected function formatCartData(): array {
    try {
      $cart = $this->getCart();

      if (!$cart instanceof WC_Cart) {
        return $this->getEmptyCartData();
      }

      // Items will be sorted later in formatCartItems

      return [
        'items' => $this->formatCartItems($cart),
        'subtotal' => Utils::formatPrice(Types::getSafeFloat($cart->get_subtotal())),
        'total' => Utils::formatPrice(Types::getSafeFloat($cart->get_total(''))),
        'item_count' => Types::getSafeInt($cart->get_cart_contents_count()),
        'tax_total' => Utils::formatPrice(Types::getSafeFloat($cart->get_total_tax())),
        'needs_shipping' => (bool) $cart->needs_shipping(),
        'shipping_total' => Utils::formatPrice(Types::getSafeFloat($cart->get_shipping_total())),
        'meta_data' => Filters::apply('fern:woo:cart_meta_data', []),
      ];
    } catch (Exception $e) {
      error_log('Error formatting cart data: ' . $e->getMessage());

      return $this->getEmptyCartData();
    }
  }

  /**
   * Format cart items with validation
   */
  protected function formatCartItems(WC_Cart $cart): array {
    $cartItems = $cart->get_cart();
    $formattedItems = [];

    if (!is_array($cartItems)) {
      return [];
    }

    // Sort by position so smaller values (older items) come first.
    uasort($cartItems, static fn(array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

    foreach ($cartItems as $cart_item_key => $cart_item) {
      try {
        if (!isset($cart_item['data']) || !is_object($cart_item['data'])) {
          throw new Exception('Invalid cart item data');
        }

        $product = $cart_item['data'];
        $productId = Types::getSafeInt($cart_item['product_id'] ?? 0);
        $variationId = Types::getSafeInt($cart_item['variation_id'] ?? 0);
        $parent_product = $productId ? Types::getSafeWpValue(wc_get_product($productId)) : null;

        $regular_price = Types::getSafeFloat($product->get_regular_price());
        $sale_price = Types::getSafeFloat($product->get_sale_price());
        $sale_amount = $this->calculateSaleAmount($regular_price, $sale_price, $product->is_on_sale());

        $item = [
          'key' => (string) $cart_item_key,
          'product_id' => $productId,
          'variation_id' => $variationId,
          'id' => Types::getSafeInt($product->get_id()),
          'name' => Types::getSafeString($product->get_title()),
          'variation' => $this->validateVariationData($cart_item['variation'] ?? []),
          'short_description' => Types::getSafeString($product->get_short_description('edit')),
          'quantity' => Types::getSafeInt($cart_item['quantity'] ?? 1),
          'sku' => Types::getSafeString($product->get_sku() ?: ''),
          'position' => $cart_item['position'],
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
          'true_total' => Utils::formatPrice(Types::getSafeFloat($cart_item['line_total'] + $cart_item['line_tax'] ?? 0)),
          'image' => $this->getProductImage($product),
          'meta_data' => Filters::apply('fern:woo:cart_item_meta_data', [], $product, $cart_item),
        ];

        if ($parent_product !== null && $parent_product?->is_type('variable')) {
          $item['productData'] = $this->getVariableProductData($parent_product);
        }

        $formattedItems[] = $item;
      } catch (Exception $e) {
        error_log('Error formatting cart item: ' . $e->getMessage());
        continue;
      }
    }

    return $formattedItems;
  }

  /**
   * Get variable product data with validation
   */
  protected function getVariableProductData(WC_Product_Variable $parent_product): array {
    try {
      return [
        'variations' => $this->formatVariations($parent_product),
        'attributes' => $this->formatAttributes($parent_product),
      ];
    } catch (Exception $e) {
      error_log('Error getting variable product data: ' . $e->getMessage());

      return ['variations' => [], 'attributes' => []];
    }
  }

  /**
   * Format variations with validation
   */
  protected function formatVariations(WC_Product_Variable $parent_product): array {
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
          'sku' => Types::getSafeString($variation['sku'] ?? ''),
          'is_in_stock' => (bool) ($variation['is_in_stock'] ?? false),
          'meta_data' => Filters::apply('fern:woo:cart_item_variation_meta_data', [], $variation),
        ];
      } catch (Exception $e) {
        error_log('Error formatting variation: ' . $e->getMessage());

        return null;
      }
    }, $variations);
  }

  /**
   * Format attributes with validation
   */
  protected function formatAttributes(WC_Product_Variable $parent_product): array {
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
            'name' => Types::getSafeString(WC_attribute_label($attribute_name)),
            'options' => array_map(
              fn($option) => Types::getSafeString(strtolower($option)),
              $options,
            ),
          ];

          return $acc;
        } catch (Exception $e) {
          error_log('Error formatting attribute: ' . $e->getMessage());

          return $acc;
        }
      },
      [],
    );
  }

  /**
   * Get empty cart data structure
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
   */
  protected function validateVariationData(array $variation): array {
    return array_map(fn($value) => Types::getSafeString($value), $variation);
  }

  /**
   * Calculate sale amount with validation
   */
  protected function calculateSaleAmount(float $regular_price, float $sale_price, bool $is_on_sale = true): ?int {
    if ($is_on_sale && $regular_price > 0) {
      return (int) round((($regular_price - $sale_price) / $regular_price) * 100);
    }

    return null;
  }

  /**
   * Get product image with fallback
   */
  protected function getProductImage(WC_Product $product): ?string {
    $image_id = $product->get_image_id();

    if (!$image_id) {
      return null;
    }

    return Types::getSafeUrl(wp_get_attachment_image_url($image_id, 'thumbnail'));
  }

  /**
   * Calculate cart totals
   */
  private function calculateCartTotals(): void {
    $cart = $this->getCart();

    // Ensure all cart lines have a valid position before totals are calculated or data formatted.
    $this->ensureCartPositions($cart);

    $cart->calculate_shipping();
    $cart->calculate_fees();

    // Hook to determine if taxes should be calculated
    $shouldCalculateTaxes = Filters::apply('fern:woo:should_calculate_taxes', true);

    $this->controlTaxCalculation($shouldCalculateTaxes, function () use ($cart) {
      $cart->calculate_totals();
    });
  }

  /**
   * Control tax calculation based on shouldCalculate parameter
   *
   * @param bool $shouldCalculate Whether taxes should be calculated
   * @param callable $callback Function to execute with tax calculation setting
   */
  private function controlTaxCalculation(bool $shouldCalculate, callable $callback): void {
    if (!$shouldCalculate) {
      add_filter('woocommerce_calc_taxes', '__return_false', 100);
    }

    $callback();

    if (!$shouldCalculate) {
      // Remove our temporary filter after calculation is done
      remove_filter('woocommerce_calc_taxes', '__return_false', 100);
    }
  }

  /**
   * Get the WooCommerce cart instance
   *
   * @throws Exception When WooCommerce is not installed
   */
  private function getCart(): WC_Cart {
    if (!class_exists('\WC_Cart')) {
      throw new Exception('WooCommerce is not installed');
    }

    if (!function_exists('WC')) {
      throw new Exception('WooCommerce function WC() not found');
    }

    $wc = WC();

    if (!$wc || !$wc->cart instanceof WC_Cart) {
      throw new Exception('Invalid WooCommerce cart instance');
    }

    return $wc->cart;
  }

  /**
   * Process batch items for cart addition
   *
   * @param array $items Array of items to add to cart
   * @return array Results of batch processing
   */
  private function processBatchItems(array $items): array {
    $results = [];
    $cart = $this->getCart();

    foreach ($items as $index => $item) {
      try {
        $result = $this->processSingleBatchItem($item, $cart);
        $results[] = array_merge(['index' => $index], $result);
      } catch (Exception $e) {
        $results[] = [
          'index' => $index,
          'success' => false,
          'message' => $e->getMessage(),
          'cart_item_key' => null,
        ];
      }
    }

    return $results;
  }

  /**
   * Process a single item in batch operation
   *
   * @param array $item Item data with product_id, quantity, etc.
   * @param WC_Cart $cart WooCommerce cart instance
   * @return array Processing result for the item
   */
  private function processSingleBatchItem(array $item, WC_Cart $cart): array {
    $productId = (int) ($item['product_id'] ?? 0);
    $quantity = (int) ($item['quantity'] ?? 1);
    $variationId = (int) ($item['variation_id'] ?? 0);
    $variation = $item['variation'] ?? [];

    if ($productId <= 0) {
      throw new Exception('Invalid product ID');
    }

    $product = Types::getSafeWpValue(wc_get_product($productId));

    if ($product === null) {
      throw new Exception('Product not found');
    }

    $newKey = $cart->add_to_cart($productId, $quantity, $variationId, $variation);

    if ($newKey) {
      $cart->cart_contents[$newKey]['position'] = $this->getNextCartPosition($cart);
    }

    if (!$newKey) {
      throw new Exception(
        $product->is_type('variable')
          ? 'Failed to add variation to cart'
          : 'Failed to add product to cart'
      );
    }

    return [
      'success' => true,
      'message' => 'Item added to cart',
      'cart_item_key' => $newKey,
    ];
  }

  /**
   * Generate batch operation message
   *
   * @param int $successCount Number of successful additions
   * @param int $totalCount Total number of items processed
   * @return string Batch operation message
   */
  private function getBatchMessage(int $successCount, int $totalCount): string {
    if ($successCount === 0) {
      return 'No items were added to cart';
    }

    if ($successCount === $totalCount) {
      return "All {$totalCount} items added to cart successfully";
    }

    return "{$successCount} of {$totalCount} items added to cart";
  }

  /**
   * Empty the cart and recalculate totals
   */
  private function resetCart(): void {
    $cart = $this->getCart();

    $cart->empty_cart();
    $this->calculateCartTotals();
  }

  /**
   * Get the next position value for a new cart item
   * Ensures new items always appear at the end of the cart
   *
   * @param WC_Cart $cart The WooCommerce cart instance
   * @return float The position value for the new item
   */
  private function getNextCartPosition(WC_Cart $cart): float {
    $cartItems = $cart->get_cart();

    if (empty($cartItems)) {
      return 1.0;
    }

    $maxPosition = 0.0;

    foreach ($cartItems as $item) {
      if (isset($item['position'])) {
        $maxPosition = max($maxPosition, (float) $item['position']);
      }
    }

    // If no items have positions yet, start from 1
    if ($maxPosition === 0.0) {
      return 1.0;
    }

    // Add 1 to ensure the new item comes after the highest positioned item
    return $maxPosition + 1.0;
  }

  private function ensureCartPositions(WC_Cart $cart): void {
    // Guarantee every cart item owns a numeric position so the front-end can sort reliably.
    $next = $this->getNextCartPosition($cart);
    foreach ($cart->get_cart() as $key => $item) {
      if (!isset($item['position'])) {
        $cart->cart_contents[$key]['position'] = $next;
        $next += 1.0;
      }
    }

    // Persist modifications so next requests keep the value.
    if (method_exists($cart, 'set_session')) {
      $cart->set_session();
    }
  }
}
