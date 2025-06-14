<?php

// Stubs for WooCommerce-related symbols used during static analysis.
// These definitions are only loaded if WooCommerce is not present and
// should NEVER be included in production environments where WooCommerce
// is active. They exist solely to satisfy PHPStan.

// phpcs:ignoreFile

namespace {
  // ---------------------------------------------------------------------
  // Functions stubbed from WooCommerce / WordPress templates
  // ---------------------------------------------------------------------

  if (!function_exists('is_shop')) {
    function is_shop(): bool {
      return false;
    }
  }

  if (!function_exists('is_account_page')) {
    function is_account_page(): bool {
      return false;
    }
  }

  if (!function_exists('is_cart')) {
    function is_cart(): bool {
      return false;
    }
  }

  if (!function_exists('is_checkout')) {
    function is_checkout(): bool {
      return false;
    }
  }

  // ---------------------------------------------------------------------
  // WooCommerce class stubs
  // ---------------------------------------------------------------------

  if (!class_exists('WC_Order')) {
    class WC_Order {
      // Empty stub for static analysis only.
    }
  }

  if (!class_exists('WC_Order_Item_Shipping')) {
    class WC_Order_Item_Shipping {
      public function set_method_title(string $title = ''): void {
      }
      public function set_method_id(string $id = ''): void {
      }
      public function set_total(float|int $total = 0): void {
      }
    }
  }
}
