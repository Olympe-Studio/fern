<?php

declare(strict_types=1);

namespace Fern\Core\Services\Woo;

use Fern\Core\Wordpress\Filters;

class Woocommerce {
  /**
   * Store all strings statically
   */
  private static array $strings = [];

  public static array $config = [];

  /**
   * Locate current WooCommerce page and subpage
   *
   * @return array{page: string|null, subPage: string|null}
   */
  public static function locate(): array {
    if (!function_exists('WC')) {
      return ['page' => null, 'subPage' => null];
    }

    if (is_shop()) {
      return ['page' => 'shop', 'subPage' => null];
    }

    if (is_product()) {
      return ['page' => 'product', 'subPage' => null];
    }

    if (is_product_category()) {
      return ['page' => 'product-category', 'subPage' => null];
    }

    if (is_cart()) {
      return ['page' => 'cart', 'subPage' => null];
    }

    if (is_checkout()) {
      $endpoint = WC()->query->get_current_endpoint();

      return [
        'page' => 'checkout',
        'subPage' => $endpoint ?: null
      ];
    }

    if (is_account_page()) {
      $endpoint = WC()->query->get_current_endpoint();

      return [
        'page' => 'my-account',
        'subPage' => $endpoint ?: null
      ];
    }

    return ['page' => null, 'subPage' => null];
  }

  /**
   * Get the WooCommerce config
   *
   * @return array
   */
  public static function getConfig(): array {
    if (empty(self::$config)) {
      self::$config = Filters::apply('fern:woo:config', [
        // Currency and Price Settings
        'currency' => html_entity_decode(get_woocommerce_currency(), ENT_QUOTES, 'UTF-8'),
        'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
        'currency_position' => get_option('woocommerce_currency_pos'),
        'thousand_separator' => html_entity_decode(WC_get_price_thousand_separator(), ENT_QUOTES, 'UTF-8'),
        'decimal_separator' => html_entity_decode(WC_get_price_decimal_separator(), ENT_QUOTES, 'UTF-8'),
        'price_decimals' => WC_get_price_decimals(),

        // Tax Settings
        'tax_enabled' => WC_tax_enabled(),
        'calc_taxes' => get_option('woocommerce_calc_taxes'),
        'tax_display_shop' => get_option('woocommerce_tax_display_shop'),
        'tax_display_cart' => get_option('woocommerce_tax_display_cart'),
        'prices_include_tax' => get_option('woocommerce_prices_include_tax'),

        // Important Pages
        'cart_page_url' => wc_get_cart_url(),
        'checkout_page_url' => wc_get_checkout_url(),
        'account_page_url' => wc_get_account_endpoint_url('dashboard'),
        'shop_page_url' => get_permalink(wc_get_page_id('shop')),
        'terms_page_url' => get_permalink(get_option('woocommerce_terms_page_id')),

        // Store Information
        'store_address' => get_option('woocommerce_store_address'),
        'store_city' => get_option('woocommerce_store_city'),
        'store_postcode' => get_option('woocommerce_store_postcode'),
        'store_country' => get_option('woocommerce_default_country'),

        // Product Settings
        'weight_unit' => get_option('woocommerce_weight_unit'),
        'dimension_unit' => get_option('woocommerce_dimension_unit'),
        'products_per_page' => get_option('posts_per_page'),
        'catalog_orderby' => get_option('woocommerce_default_catalog_orderby'),
        'review_ratings_enabled' => get_option('woocommerce_enable_reviews'),

        // Inventory Settings
        'manage_stock' => get_option('woocommerce_manage_stock'),
        'stock_format' => get_option('woocommerce_stock_format'),
        'notify_low_stock' => get_option('woocommerce_notify_low_stock'),
        'notify_no_stock' => get_option('woocommerce_notify_no_stock'),
        'low_stock_amount' => get_option('woocommerce_notify_low_stock_amount'),

        // Checkout Settings
        'enable_guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
        'enable_checkout_login_reminder' => get_option('woocommerce_enable_checkout_login_reminder'),
        'enable_signup_and_login_from_checkout' => get_option('woocommerce_enable_signup_and_login_from_checkout'),
        'enable_myaccount_registration' => get_option('woocommerce_enable_myaccount_registration'),

        // Email Settings
        'admin_email' => get_option('admin_email'),
        'email_from_name' => get_option('woocommerce_email_from_name'),
        'email_from_address' => get_option('woocommerce_email_from_address'),

        // Digital Products
        'downloads_require_login' => get_option('woocommerce_downloads_require_login'),
        'downloads_grant_access_after_payment' => get_option('woocommerce_downloads_grant_access_after_payment'),

        // Image Sizes
        'image_sizes' => [
          'thumbnail' => [
            'width' => get_option('woocommerce_thumbnail_image_width'),
            'height' => get_option('woocommerce_thumbnail_image_height'),
            'crop' => get_option('woocommerce_thumbnail_cropping'),
          ],
          'single' => [
            'width' => get_option('woocommerce_single_image_width'),
            'height' => get_option('woocommerce_single_image_height'),
          ],
        ],
      ]);
    }

    return self::$config;
  }

  /**
   * Get all strings
   *
   * @return array
   */
  public static function getTexts() {
    if (empty(self::$strings)) {
      self::$strings = self::initStrings();
    }

    return self::$strings;
  }

  /**
   * Get text string using dot notation
   *
   * @param string $key     Dot notation key (e.g., 'cart.empty_cart')
   * @param string $default The default value if the key is not found
   *
   * @return string|null The text string or default value if not found
   */
  public static function getText(string $key, ?string $default = null): ?string {
    $keys = explode('.', $key);
    $value = self::getTexts();

    foreach ($keys as $subKey) {
      if (!is_array($value) || !array_key_exists($subKey, $value)) {
        return $default;
      }

      $value = $value[$subKey];
    }

    return $value;
  }

  /**
   * Get all strings for a specific section
   *
   * @param string $section Section name (e.g., 'cart', 'checkout')
   *
   * @return array|null Array of strings or null if section not found
   */
  public static function getSection($section) {
    $strs = self::getTexts();

    return isset($strs[$section]) ? $strs[$section] : null;
  }

  /**
   * Init the Woocommerce strings
   *
   * @return array
   */
  private static function initStrings() {
    return Filters::apply('fern:woo:texts', [
      'general' => [
        'shop' => __('Shop', 'woocommerce'),
        'account' => __('My account', 'woocommerce'),
        'orders' => __('Orders', 'woocommerce'),
        'downloads' => __('Downloads', 'woocommerce'),
        'addresses' => __('Addresses', 'woocommerce'),
        'logout' => __('Logout', 'woocommerce'),
        'login' => __('Login', 'woocommerce'),
        'register' => __('Register', 'woocommerce'),
        'remember_me' => __('Remember me', 'woocommerce'),
        'lost_password' => __('Lost your password?', 'woocommerce'),
        'save_changes' => __('Save changes', 'woocommerce'),
      ],
      'product' => [
        'out_of_stock' => __('Out of stock', 'woocommerce'),
        'in_stock' => __('In stock', 'woocommerce'),
        'add_to_cart' => __('Add to cart', 'woocommerce'),
        'buy_now' => __('Buy now', 'woocommerce'),
        'read_more' => __('Read more', 'woocommerce'),
        'sale' => __('Sale!', 'woocommerce'),
        'new' => __('New!', 'woocommerce'),
        'featured' => __('Featured', 'woocommerce'),
        'reviews' => __('Reviews', 'woocommerce'),
        'reviews_two' => __('Product Reviews', 'woocommerce'),
        'categories' => __('Categories', 'woocommerce'),
        'tags' => __('Tags', 'woocommerce'),
        'sku' => __('SKU', 'woocommerce'),
        'description' => __('Description', 'woocommerce'),
        'additional_infos' => __('Additional information', 'woocommerce'),
        'related_products' => __('Related products', 'woocommerce'),
        'attributes' => __('Product attributes', 'woocommerce'),
        'variations' => __('Product variations', 'woocommerce'),
        'choose_option' => __('Choose an option', 'woocommerce'),
        'clear_selection' => __('Clear selection', 'woocommerce'),
        'price' => __('Price', 'woocommerce'),
      ],
      'cart' => [
        'add_to_cart' => __('Add to cart', 'woocommerce'),
        'view_cart' => __('View cart', 'woocommerce'),
        'cart' => __('Cart', 'woocommerce'),
        'page_title' => __('Shopping Cart', 'woocommerce'),
        'empty_cart' => __('Your cart is currently empty.', 'woocommerce'),
        'return_to_shop' => __('Return to shop', 'woocommerce'),
        'close_cart' => __('Close', 'woocommerce'),
        'update_cart' => __('Update cart', 'woocommerce'),
        'cart_totals' => __('Cart totals', 'woocommerce'),
        'proceed_checkout' => __('Proceed to checkout', 'woocommerce'),
        'coupon_code' => __('Coupon code', 'woocommerce'),
        'apply_coupon' => __('Apply coupon', 'woocommerce'),
        'remove_item' => __('Remove this item', 'woocommerce'),
        'restore_item' => __('Restore item', 'woocommerce'),
        'product' => __('Product', 'woocommerce'),
        'price' => __('Price', 'woocommerce'),
        'quantity' => __('Quantity', 'woocommerce'),
        'subtotal' => __('Subtotal', 'woocommerce'),
        'cart_updated' => __('Cart updated.', 'woocommerce'),
        'cart_empty' => __('Cart is empty.', 'woocommerce'),
        'item_removed' => __('Item removed.', 'woocommerce'),
        'item_restored' => __('Item restored.', 'woocommerce'),
        'coupon_applied' => __('Coupon code applied successfully.', 'woocommerce'),
        'coupon_removed' => __('Coupon removed successfully.', 'woocommerce'),
        'total' => __('Total', 'woocommerce'),
        'subtotal' => __('Subtotal', 'woocommerce'),
        'shipping_total' => __('Shipping', 'woocommerce'),
        'tax_total' => __('Tax', 'woocommerce'),
      ],
      'checkout' => [
        'page_title' => __('Checkout', 'woocommerce'),
        'returning_customer' => __('Returning customer?', 'woocommerce'),
        'click_to_login' => __('Click here to login', 'woocommerce'),
        'billing_details' => __('Billing details', 'woocommerce'),
        'shipping_details' => __('Shipping details', 'woocommerce'),
        'different_shipping' => __('Ship to a different address?', 'woocommerce'),
        'order_notes' => __('Order notes', 'woocommerce'),
        'optional' => __('(optional)', 'woocommerce'),
        'place_order' => __('Place order', 'woocommerce'),
        'payment_method' => __('Payment method', 'woocommerce'),
        'no_payment_methods' => __('Sorry, it seems that there are no available payment methods.', 'woocommerce'),
        'please_fill' => __('Please fill in your details above to see available payment methods.', 'woocommerce'),
        'order_review' => __('Your order', 'woocommerce'),
        'create_account' => __('Create an account?', 'woocommerce'),
        'order_received' => __('Thank you. Your order has been received.', 'woocommerce'),
      ],
      'shipping' => [
        'shipping' => __('Shipping', 'woocommerce'),
        'calculate_shipping' => __('Calculate shipping', 'woocommerce'),
        'update_totals' => __('Update totals', 'woocommerce'),
        'shipping_address' => __('Shipping address', 'woocommerce'),
        'shipping_method' => __('Shipping method', 'woocommerce'),
        'no_shipping' => __('No shipping options were found.', 'woocommerce'),
        'free_shipping' => __('Free shipping', 'woocommerce'),
        'flat_rate' => __('Flat rate', 'woocommerce'),
        'local_pickup' => __('Local pickup', 'woocommerce'),
        'shipping_cost' => __('Shipping cost', 'woocommerce'),
        'shipping_address_2' => __('Apartment, suite, unit, etc. (optional)', 'woocommerce'),
      ],
      'errors' => [
        'required_field' => __('This is a required field.', 'woocommerce'),
        'login_required' => __('Only logged in customers who have purchased this product may leave a review.', 'woocommerce'),
        'verified_owners' => __('Reviews can only be left by "verified owners"', 'woocommerce'),
        'invalid_email' => __('Please provide a valid email address.', 'woocommerce'),
        'invalid_phone' => __('Please enter a valid phone number.', 'woocommerce'),
        'invalid_postcode' => __('Please enter a valid postcode/ZIP.', 'woocommerce'),
        'invalid_card' => __('Please enter a valid card number.', 'woocommerce'),
        'invalid_cvv' => __('Please enter a valid security code.', 'woocommerce'),
        'invalid_expiry' => __('Please enter a valid expiry date.', 'woocommerce'),
        'invalid_coupon' => __('Coupon is not valid.', 'woocommerce'),
        'expired_coupon' => __('This coupon has expired.', 'woocommerce'),
        'removing_coupon' => __('Sorry there was a problem removing this coupon.', 'woocommerce'),
        'already_applied' => __('Coupon code already applied.', 'woocommerce'),
        'minimum_amount' => __('Minimum order amount not met.', 'woocommerce'),
        'maximum_amount' => __('Maximum order amount exceeded.', 'woocommerce'),
        'excluded_items' => __('Some items in your cart are excluded from this coupon.', 'woocommerce'),
        'out_of_stock_item' => __('Sorry, this product is out of stock.', 'woocommerce'),
        'not_purchasable' => __('Sorry, this product cannot be purchased.', 'woocommerce'),
        'session_expired' => __('Sorry, your session has expired.', 'woocommerce'),
        'minimum_quantity' => __('Minimum quantity not met.', 'woocommerce'),
        'maximum_quantity' => __('Maximum quantity exceeded.', 'woocommerce'),
        'invalid_order' => __('Invalid order.', 'woocommerce'),
        'invalid_coupon' => __('Invalid coupon.', 'woocommerce'),
      ],
      'success' => [
        'order_received' => __('Thank you. Your order has been received.', 'woocommerce'),
        'order_processed' => __('Order has been processed successfully.', 'woocommerce'),
        'payment_successful' => __('Payment completed successfully.', 'woocommerce'),
        'account_created' => __('Account created successfully.', 'woocommerce'),
        'password_reset' => __('Password reset successfully.', 'woocommerce'),
        'address_saved' => __('Address saved successfully.', 'woocommerce'),
      ],
      'account' => [
        'dashboard' => __('Dashboard', 'woocommerce'),
        'orders' => __('Orders', 'woocommerce'),
        'downloads' => __('Downloads', 'woocommerce'),
        'addresses' => __('Addresses', 'woocommerce'),
        'payment_methods' => __('Payment methods', 'woocommerce'),
        'account_details' => __('Account details', 'woocommerce'),
        'no_orders' => __('No order has been made yet.', 'woocommerce'),
        'no_downloads' => __('No downloads available yet.', 'woocommerce'),
        'no_addresses' => __('No addresses saved yet.', 'woocommerce'),
      ],
      'units' => [
        'weight' => __('Weight', 'woocommerce'),
        'dimensions' => __('Dimensions', 'woocommerce'),
        'kg' => __('kg', 'woocommerce'),
        'g' => __('g', 'woocommerce'),
        'lbs' => __('lbs', 'woocommerce'),
        'oz' => __('oz', 'woocommerce'),
        'm' => __('m', 'woocommerce'),
        'cm' => __('cm', 'woocommerce'),
        'mm' => __('mm', 'woocommerce'),
        'in' => __('in', 'woocommerce'),
        'yd' => __('yd', 'woocommerce'),
      ],
      'taxes' => [
        'incl' => __('Including tax', 'woocommerce'),
        'excl' => __('Excluding tax', 'woocommerce'),
      ],
    ]);
  }
}
