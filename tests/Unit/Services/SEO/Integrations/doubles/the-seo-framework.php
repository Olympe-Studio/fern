<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The SEO Framework test doubles
|--------------------------------------------------------------------------
|
| Defined lazily (required on demand) rather than at test-file load time so
| that TheSeoFrameworkTest can exercise the "The SEO Framework not installed"
| branch of TheSeoFramework::getHelmet() while the tsf() function and the
| Title class are still genuinely absent.
|
*/

namespace The_SEO_Framework\Front {
  if (!class_exists(Title::class, false)) {
    class Title {
      public static string $title = '';

      public static function set_document_title(): string {
        return self::$title;
      }
    }
  }
}

namespace {
  if (!function_exists('tsf')) {
    function tsf(): ?object {
      return $GLOBALS['__tsf_instance'] ?? null;
    }
  }
}
