<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AIOSEO Head test double
|--------------------------------------------------------------------------
|
| Defined lazily (required on demand) rather than at test-file load time so
| that AllInOneTest can exercise the "AIOSEO not installed" branch of
| AllInOne::getHelmet() while the class is still genuinely absent.
|
*/

namespace AIOSEO\Plugin\Common\Main;

if (!class_exists(Head::class, false)) {
  class Head {
    public static string $emit = '';

    public function output(): void {
      echo self::$emit;
    }

    public function wpHead(): void {}
  }
}
