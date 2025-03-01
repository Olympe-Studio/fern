<?php declare(strict_types=1);

namespace Fern\Core\Services\SEO;

interface SEOIntegration {
  /**
   * Get the helmet for the SEO integration.
   */
  public static function getHelmet(): string;
}
