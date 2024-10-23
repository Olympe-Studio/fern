<?php declare(strict_types=1);

namespace Fern\Core\Services\SEO;

use Fern\Core\Factory\Singleton;

class Helmet extends Singleton {
  const MAP = [
    'yoast' => 'Fern\Core\Services\SEO\Integrations\Yoast',
    'all-in-one' => 'Fern\Core\Services\SEO\Integrations\AllInOne',
    'rank-math' => 'Fern\Core\Services\SEO\Integrations\RankMath',
    'the-seo-framework' => 'Fern\Core\Services\SEO\Integrations\TheSeoFramework',

    /**
     *  Not supported because their author doesn't provide a PHP API
     *  that allows us to extract the head without duplicating / altering wp_head.
     */
    'seopress' => 'Fern\Core\Services\SEO\Integrations\SeoPress',
    'squirrly' => 'Fern\Core\Services\SEO\Integrations\Squirrly',
    'jetpack' => 'Fern\Core\Services\SEO\Integrations\Jetpack',
  ];

  /**
   * Get the current SEO service.
   *
   * @return string|null
   */
  public static function getCurrent() {
    $plugin = self::resolvePlugin();

    if (!$plugin) {
      return null;
    }

    if (!isset(self::MAP[$plugin])) {
      return null;
    }

    return self::MAP[$plugin]::getHelmet();
  }

  /**
   * Resolve the current SEO plugin.
   *
   * @return string|false
   */
  protected static function resolvePlugin() {
    /**
     * Ordered by popularity.
     */

    // Yoast SEO
    if (defined('WPSEO_VERSION')) {
      return 'yoast';
    }

    // Rank Math SEO
    if (defined('RANK_MATH_VERSION')) {
      return 'rank-math';
    }

    // All in One SEO
    if (defined('AIOSEO_VERSION')) {
      return 'all-in-one';
    }

    // The SEO Framework
    if (defined('THE_SEO_FRAMEWORK_VERSION')) {
      return 'the-seo-framework';
    }

    // SEOPress
    if (defined('SEOPRESS_VERSION')) {
      return 'seopress';
    }

    // Squirrly SEO
    if (defined('SQUIRRLY_SEO_VERSION')) {
      return 'squirrly';
    }

    // Jetpack SEO tools (common but often not primary SEO solution)
    if (class_exists('Jetpack') && Jetpack::is_module_active('seo-tools')) {
      return 'jetpack';
    }

    // No known SEO plugin detected
    return false;
  }
}
