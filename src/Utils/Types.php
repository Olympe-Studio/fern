<?php declare(strict_types=1);

namespace Fern\Core\Utils;

class Types {
  /**
   * Convert WordPress return values to either the real value or null
   * Handles WP_Error, false, empty strings, and other WordPress empty states
   *
   * @param mixed $value The value to check
   *
   * @return mixed|null Returns null for empty/error states, real value otherwise
   */
  public static function getSafeWpValue($value): mixed {
    if (is_wp_error($value)) {
      return null;
    }

    if (is_array($value)) {
      return empty($value) ? null : $value;
    }

    if (
      $value === false
      || $value === ''
      || $value === null
      || (is_string($value) && in_array(strtolower($value), ['null', 'false', 'undefined'], true))
    ) {
      return null;
    }

    return $value;
  }

  /**
   * Safe float conversion
   *
   * @param mixed $value
   */
  public static function getSafeFloat($value): float {
    return (float) ($value ?? 0);
  }

  /**
   * Safe integer conversion
   *
   * @param mixed $value
   */
  public static function getSafeInt($value): int {
    return (int) ($value ?? 0);
  }

  /**
   * Safe string conversion
   *
   * @param mixed $value
   */
  public static function getSafeString($value): string {
    return (string) ($value ?? '');
  }

  /**
   * Safe boolean conversion
   *
   * @param mixed $value
   */
  public static function getSafeBool($value): bool {
    if (is_string($value)) {
      return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    return (bool) ($value ?? false);
  }

  /**
   * Safe array conversion
   *
   * @param mixed $value
   */
  public static function getSafeArray($value): array {
    if (is_null($value)) {
      return [];
    }

    return is_array($value) ? $value : [$value];
  }

  /**
   * Get safe email (returns empty string if invalid)
   *
   * @param mixed $value
   */
  public static function getSafeEmail($value): string {
    $email = self::getSafeString($value);

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
  }

  /**
   * Get safe URL (returns empty string if invalid)
   *
   * @param mixed $value
   */
  public static function getSafeUrl($value): string {
    $url = self::getSafeString($value);

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
  }

  /**
   * Get safe slug (URL friendly string)
   *
   * @param mixed $value
   */
  public static function getSafeSlug($value): string {
    $string = self::getSafeString($value);
    $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
    $string = strtolower(trim($string));

    return preg_replace('/[\s-]+/', '-', $string);
  }
}
