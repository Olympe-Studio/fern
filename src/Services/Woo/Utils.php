<?php declare(strict_types=1);

namespace Fern\Core\Services\Woo;

class Utils {
  /**
   * Format a price value with currency but without HTML
   *
   * @param int|float|string|null $price Price to format
   *
   * @return string|null Formatted price or null if invalid
   */
  public static function formatPrice(int|float|string|null $price): ?string {
    if ($price === null) {
      return null;
    }

    if (empty($price) || $price <= 0) {
      return null;
    }

    if (!function_exists('WC')) {
      return (string) $price;
    }

    $args = [
      'currency' => '',
      /** @phpstan-ignore-next-line */
      'decimal_separator' => wc_get_price_decimal_separator(),
      /** @phpstan-ignore-next-line */
      'thousand_separator' => wc_get_price_thousand_separator(),
      /** @phpstan-ignore-next-line */
      'decimals' => wc_get_price_decimals(),
      /** @phpstan-ignore-next-line */
      'price_format' => get_woocommerce_price_format(),
    ];

    $price = (float) $price;

    $negative = $price < 0;
    $price = $negative ? $price * -1 : $price;

    // Format the number
    $price = number_format(
        $price,
        $args['decimals'],
        $args['decimal_separator'],
        $args['thousand_separator'],
    );

    // Trim zeros if enabled
    if (apply_filters('woocommerce_price_trim_zeros', false) && $args['decimals'] > 0) {
      /** @phpstan-ignore-next-line */
      $price = wc_trim_zeros($price);
    }

    // Get currency symbol without HTML
    /** @phpstan-ignore-next-line */
    $currency = get_woocommerce_currency_symbol($args['currency']);

    // Format with currency symbol
    return html_entity_decode(($negative ? '-' : '') . sprintf(
        $args['price_format'],
        $currency,
        $price,
    ));
  }
}
