<?php

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class SeoPress implements SEOIntegration {
  public static function getHelmet(): string {
    return '<!-- SEOPress is not supported because it doesn\'t provide a PHP API -->';
  }
}
