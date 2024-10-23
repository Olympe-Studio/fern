<?php declare(strict_types=1);

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class Jetpack implements SEOIntegration {
  /**
   * Get the helmet for Jetpack.
   */
  public static function getHelmet(): string {
    return '<!-- Jetpack is not supported because it doesn\'t provide a PHP API -->';
  }
}
