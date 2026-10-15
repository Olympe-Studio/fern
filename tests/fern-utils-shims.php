<?php

declare(strict_types=1);

namespace Fern\Core\Utils;

/*
|--------------------------------------------------------------------------
| Fern\Core\Utils internal-function shims
|--------------------------------------------------------------------------
|
| A handful of failure branches in Fern\Core\Utils can only be reached when an
| internal PHP function returns its error value. Those functions are called
| unqualified from within the Fern\Core\Utils namespace, so intercepting them
| requires a namespaced definition. Brain Monkey can create one, but it leaves a
| stub behind that throws on every later real call, contaminating the suite.
|
| Instead we define permanent pass-through shims here: by default they delegate
| to the real global function, so behaviour is unchanged for every test. A test
| flips a per-call toggle (and clears it in afterEach) to force the error value.
|
| Defined in a real file with strict types so Pest's "toUseStrictTypes" arch rule
| resolves a valid path and passes.
|
*/

if (!function_exists('Fern\\Core\\Utils\\json_encode')) {
  /**
   * Pass-through shim for json_encode within this namespace.
   *
   * Returns false (mimicking an encode failure) when the toggle is set, so
   * JSON::encode consumers can exercise their "failed to encode" branch.
   */
  function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false {
    if (!empty($GLOBALS['__fern_force_json_encode_false'])) {
      return false;
    }

    return \json_encode($value, $flags, $depth);
  }
}

if (!function_exists('Fern\\Core\\Utils\\preg_replace')) {
  /**
   * Pass-through shim for preg_replace within this namespace.
   *
   * Returns null (mimicking a PCRE error) for the exact pattern named by the
   * toggle, so Types::getSafeSlug can exercise both of its null-guard branches.
   *
   * @param string|array<int, string> $pattern
   * @param string|array<int, string> $replacement
   * @param string|array<int, string> $subject
   */
  function preg_replace(string|array $pattern, string|array $replacement, string|array $subject, int $limit = -1, ?int &$count = null): string|array|null {
    $nullFor = $GLOBALS['__fern_preg_replace_null_for'] ?? null;

    if ($nullFor !== null && $pattern === $nullFor) {
      return null;
    }

    return \preg_replace($pattern, $replacement, $subject, $limit, $count);
  }
}
