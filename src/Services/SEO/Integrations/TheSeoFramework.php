<?php

declare(strict_types=1);

namespace Fern\Core\Services\SEO\Integrations;

use Fern\Core\Services\SEO\SEOIntegration;

class TheSeoFramework implements SEOIntegration {
  /**
   * Get the helmet for The SEO Framework.
   */
  public static function getHelmet(): string {
    if (function_exists('tsf') && class_exists('\The_SEO_Framework\Front\Title')) {
      ob_start();
      $tsf = tsf();
      // title is not included in the meta tags
      echo '<title>' . html_entity_decode(\The_SEO_Framework\Front\Title::set_document_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</title>';
      $tsf->print_seo_meta_tags();

      return ob_get_clean() ?: '';
    }

    return '';
  }
}
