<?php

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class Squirrly implements SEOIntegration {
  public static function getHelmet(): string {
    return '<!-- Squirrly is not supported because it doesn\'t provide a PHP API -->';
  }
}
