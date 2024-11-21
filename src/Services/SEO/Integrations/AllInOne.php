<?php declare(strict_types=1);

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class AllInOne implements SEOIntegration {
  /**
   * Get the helmet for All in One SEO.
   */
  public static function getHelmet(): string {
    if (class_exists('AIOSEO\Plugin\Common\Main\Head')) {
      ob_start();

      $aioseoHead = new \AIOSEO\Plugin\Common\Main\Head();
      $aioseoHead->output();
      $output = ob_get_clean();
      remove_action('wp_head', [$aioseoHead, 'wpHead'], 1);

      return $output ?: '';
    }

    return '';
  }
}
