<?php

namespace Fern\Core\Services\Woo;

use Fern\Core\Wordpress\Filters;

class Order {

  /**
   * Create order from cart
   * Use this method only if you want to avoid using woocommerce default checkout process.
   * This is not recommended but can be useful in some cases.
   *
   * @return \WC_Order
   */
  public static function createFromCart(): \WC_Order {
    $cart = WC()->cart;
    $cart->calculate_shipping();
    $cart->calculate_totals();

    if ($cart->is_empty()) {
      throw new \Exception('Cart is empty');
    }

    // Create order
    $order = wc_create_order();
    foreach ($cart->get_cart() as $_ => $cartItem) {
      $product = $cartItem['data'];
      $quantity = $cartItem['quantity'];

      $order->add_product(
        $product,
        $quantity,
        [
          'variation' => $cartItem['variation'],
          'totals'    => [
            'subtotal'     => $cartItem['line_subtotal'],
            'subtotal_tax' => $cartItem['line_subtotal_tax'],
            'total'        => $cartItem['line_total'],
            'tax'          => $cartItem['line_tax'],
          ],
        ],
      );
    }

    $shipping = WC()->shipping();
    $packages = $shipping->get_packages();
    $chosen_methods = WC()->session->get('chosen_shipping_methods');

    if (!empty($chosen_methods)) {
      foreach ($packages as $package_key => $package) {
        if (isset($package['rates'][$chosen_methods[$package_key]])) {
          $rate = $package['rates'][$chosen_methods[$package_key]];
          $item = new \WC_Order_Item_Shipping();
          $item->set_method_title($rate->label);
          $item->set_method_id($rate->id);
          $item->set_total($rate->cost);

          $order->add_item($item);
        }
      }
    }

    /**
     * Allow plugins to modify the order to add extra data before proceeding to payment
     */
    $order = Filters::apply('fern:woo:order:created', $order);

    $order->calculate_totals();
    $order->save();

    return $order;
  }
}
