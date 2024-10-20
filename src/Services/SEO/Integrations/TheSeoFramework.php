<?php

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class TheSeoFramework implements SEOIntegration {
  /**
   * Get the helmet for The SEO Framework.
   */
  public static function getHelmet(): string {
    if (function_exists('tsf')) {
      ob_start();
      tsf()->print_seo_meta_tags();
      return ob_get_clean();
    }

    return '';
  }
}
