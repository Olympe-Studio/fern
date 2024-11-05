<?php

declare(strict_types=1);

namespace Fern\Core\Services\Woo;

class Woocommerce {
  /**
   * Store all strings statically
   */
  private static array $strings = [];

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
   * @param string $key      Dot notation key (e.g., 'cart.empty_cart')
   * @param string $default  The default value if the key is not found
   *
   * @return string|null The text string or default value if not found
   */
  public static function getText(string $key, string $default = null): ?string {
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
    return [
      'general' => [
        'shop'               => __('Shop', 'woocommerce'),
        'account'            => __('My account', 'woocommerce'),
        'orders'             => __('Orders', 'woocommerce'),
        'downloads'          => __('Downloads', 'woocommerce'),
        'addresses'          => __('Addresses', 'woocommerce'),
        'logout'             => __('Logout', 'woocommerce'),
        'login'              => __('Login', 'woocommerce'),
        'register'           => __('Register', 'woocommerce'),
        'remember_me'        => __('Remember me', 'woocommerce'),
        'lost_password'      => __('Lost your password?', 'woocommerce'),
        'save_changes'       => __('Save changes', 'woocommerce'),
      ],
      'product' => [
        'out_of_stock'       => __('Out of stock', 'woocommerce'),
        'in_stock'           => __('In stock', 'woocommerce'),
        'add_to_cart'        => __('Add to cart', 'woocommerce'),
        'read_more'          => __('Read more', 'woocommerce'),
        'sale'               => __('Sale!', 'woocommerce'),
        'new'                => __('New!', 'woocommerce'),
        'featured'           => __('Featured', 'woocommerce'),
        'reviews'            => __('Reviews', 'woocommerce'),
        'reviews_two'        => __('Product Reviews', 'woocommerce'),
        'categories'         => __('Categories', 'woocommerce'),
        'tags'               => __('Tags', 'woocommerce'),
        'sku'                => __('SKU', 'woocommerce'),
        'description'        => __('Description', 'woocommerce'),
        'additional_infos'   => __('Additional information', 'woocommerce'),
        'related_products'   => __('Related products', 'woocommerce'),
        'attributes'         => __('Product attributes', 'woocommerce'),
        'variations'         => __('Product variations', 'woocommerce'),
        'choose_option'      => __('Choose an option', 'woocommerce'),
        'clear_selection'    => __('Clear selection', 'woocommerce'),
        'price'              => __('Price', 'woocommerce'),
      ],
      'cart' => [
        'view_cart'          => __('View cart', 'woocommerce'),
        'cart'               => __('Cart', 'woocommerce'),
        'page_title'         => __('Shopping Cart', 'woocommerce'),
        'empty_cart'         => __('Your cart is currently empty.', 'woocommerce'),
        'return_to_shop'     => __('Return to shop', 'woocommerce'),
        'update_cart'        => __('Update cart', 'woocommerce'),
        'cart_totals'        => __('Cart totals', 'woocommerce'),
        'proceed_checkout'   => __('Proceed to checkout', 'woocommerce'),
        'coupon_code'        => __('Coupon code', 'woocommerce'),
        'apply_coupon'       => __('Apply coupon', 'woocommerce'),
        'remove_item'        => __('Remove this item', 'woocommerce'),
        'restore_item'       => __('Restore item', 'woocommerce'),
        'product'            => __('Product', 'woocommerce'),
        'price'              => __('Price', 'woocommerce'),
        'quantity'           => __('Quantity', 'woocommerce'),
        'subtotal'           => __('Subtotal', 'woocommerce'),
        'cart_updated'       => __('Cart updated.', 'woocommerce'),
        'cart_empty'         => __('Cart is empty.', 'woocommerce'),
        'item_removed'       => __('Item removed.', 'woocommerce'),
        'item_restored'      => __('Item restored.', 'woocommerce'),
        'coupon_applied'     => __('Coupon code applied successfully.', 'woocommerce'),
        'coupon_removed'     => __('Coupon removed successfully.', 'woocommerce'),
      ],
      'checkout' => [
        'page_title'         => __('Checkout', 'woocommerce'),
        'returning_customer' => __('Returning customer?', 'woocommerce'),
        'click_to_login'     => __('Click here to login', 'woocommerce'),
        'billing_details'    => __('Billing details', 'woocommerce'),
        'shipping_details'   => __('Shipping details', 'woocommerce'),
        'different_shipping' => __('Ship to a different address?', 'woocommerce'),
        'order_notes'        => __('Order notes', 'woocommerce'),
        'optional'           => __('(optional)', 'woocommerce'),
        'place_order'        => __('Place order', 'woocommerce'),
        'payment_method'     => __('Payment method', 'woocommerce'),
        'no_payment_methods' => __('Sorry, it seems that there are no available payment methods.', 'woocommerce'),
        'please_fill'        => __('Please fill in your details above to see available payment methods.', 'woocommerce'),
        'order_review'       => __('Your order', 'woocommerce'),
        'create_account'     => __('Create an account?', 'woocommerce'),
        'order_received'     => __('Thank you. Your order has been received.', 'woocommerce'),
      ],
      'shipping' => [
        'shipping'           => __('Shipping', 'woocommerce'),
        'calculate_shipping' => __('Calculate shipping', 'woocommerce'),
        'update_totals'      => __('Update totals', 'woocommerce'),
        'shipping_address'   => __('Shipping address', 'woocommerce'),
        'shipping_method'    => __('Shipping method', 'woocommerce'),
        'no_shipping'        => __('No shipping options were found.', 'woocommerce'),
        'free_shipping'      => __('Free shipping', 'woocommerce'),
        'flat_rate'          => __('Flat rate', 'woocommerce'),
        'local_pickup'       => __('Local pickup', 'woocommerce'),
        'shipping_cost'      => __('Shipping cost', 'woocommerce'),
        'shipping_address_2' => __('Apartment, suite, unit, etc. (optional)', 'woocommerce'),
      ],
      'errors' => [
        'required_field'     => __('This is a required field.', 'woocommerce'),
        'login_required'     => __('Only logged in customers who have purchased this product may leave a review.', 'woocommerce'),
        'verified_owners'    => __('Reviews can only be left by "verified owners"', 'woocommerce'),
        'invalid_email'      => __('Please provide a valid email address.', 'woocommerce'),
        'invalid_phone'      => __('Please enter a valid phone number.', 'woocommerce'),
        'invalid_postcode'   => __('Please enter a valid postcode/ZIP.', 'woocommerce'),
        'invalid_card'       => __('Please enter a valid card number.', 'woocommerce'),
        'invalid_cvv'        => __('Please enter a valid security code.', 'woocommerce'),
        'invalid_expiry'     => __('Please enter a valid expiry date.', 'woocommerce'),
        'invalid_coupon'     => __('Coupon is not valid.', 'woocommerce'),
        'expired_coupon'     => __('This coupon has expired.', 'woocommerce'),
        'removing_coupon'    => __('Sorry there was a problem removing this coupon.', 'woocommerce'),
        'already_applied'    => __('Coupon code already applied.', 'woocommerce'),
        'minimum_amount'     => __('Minimum order amount not met.', 'woocommerce'),
        'maximum_amount'     => __('Maximum order amount exceeded.', 'woocommerce'),
        'excluded_items'     => __('Some items in your cart are excluded from this coupon.', 'woocommerce'),
        'out_of_stock_item'  => __('Sorry, this product is out of stock.', 'woocommerce'),
        'not_purchasable'    => __('Sorry, this product cannot be purchased.', 'woocommerce'),
        'session_expired'    => __('Sorry, your session has expired.', 'woocommerce'),
        'minimum_quantity'   => __('Minimum quantity not met.', 'woocommerce'),
        'maximum_quantity'   => __('Maximum quantity exceeded.', 'woocommerce'),
        'invalid_order'      => __('Invalid order.', 'woocommerce'),
        'invalid_coupon'     => __('Invalid coupon.', 'woocommerce'),
      ],
      'success' => [
        'order_received'     => __('Thank you. Your order has been received.', 'woocommerce'),
        'order_processed'    => __('Order has been processed successfully.', 'woocommerce'),
        'payment_successful' => __('Payment completed successfully.', 'woocommerce'),
        'account_created'    => __('Account created successfully.', 'woocommerce'),
        'password_reset'     => __('Password reset successfully.', 'woocommerce'),
        'address_saved'      => __('Address saved successfully.', 'woocommerce'),
      ],
      'account' => [
        'dashboard'          => __('Dashboard', 'woocommerce'),
        'orders'             => __('Orders', 'woocommerce'),
        'downloads'          => __('Downloads', 'woocommerce'),
        'addresses'          => __('Addresses', 'woocommerce'),
        'payment_methods'    => __('Payment methods', 'woocommerce'),
        'account_details'    => __('Account details', 'woocommerce'),
        'no_orders'          => __('No order has been made yet.', 'woocommerce'),
        'no_downloads'       => __('No downloads available yet.', 'woocommerce'),
        'no_addresses'       => __('No addresses saved yet.', 'woocommerce'),
      ],
      'units' => [
        'weight' => __('Weight', 'woocommerce'),
        'dimensions' => __('Dimensions', 'woocommerce'),
        'kg'         => __('kg', 'woocommerce'),
        'g'          => __('g', 'woocommerce'),
        'lbs'        => __('lbs', 'woocommerce'),
        'oz'         => __('oz', 'woocommerce'),
        'm'          => __('m', 'woocommerce'),
        'cm'         => __('cm', 'woocommerce'),
        'mm'         => __('mm', 'woocommerce'),
        'in'         => __('in', 'woocommerce'),
        'yd'         => __('yd', 'woocommerce'),
      ],
    ];;
  }
}
