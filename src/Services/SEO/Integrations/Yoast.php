<?php

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class Yoast implements SEOIntegration {
  /**
   * Get the helmet for Yoast.
   */
  public static function getHelmet(): string {
    if (function_exists('wpseo_head')) {
      ob_start();
      do_action('wpseo_head');
      remove_all_actions('wpseo_head');
      return ob_get_clean();
    }

    return '';
  }
}
