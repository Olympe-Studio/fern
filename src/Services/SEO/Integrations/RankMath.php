<?php

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class RankMath implements SEOIntegration {
  /**
   * Get the helmet for Rank Math.
   */
  public static function getHelmet(): string {
    if (function_exists('rank_math_head')) {
      ob_start();
      do_action('rank_math/head');
      remove_all_actions('rank_math/head');
      return ob_get_clean();
    }

    return '';
  }
}
