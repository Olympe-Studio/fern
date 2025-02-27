<?php

declare(strict_types=1);

namespace Fern\Core\Utils;

use RuntimeException;
use WP_Error;

class Types {
  /**
   * Convert WordPress return values to either the real value or null
   * Handles WP_Error, false, empty strings, and other WordPress empty states
   *
   * @template T
   *
   * @param T $value The value to check
   *
   * @return (T is WP_Error ? null : (T is array<mixed> ? (array<mixed>|null) : (T|null)))
   */
  public static function getSafeWpValue(mixed $value): mixed {
    if ($value instanceof WP_Error) {
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
   */
  public static function getSafeFloat(float|int|string|null $value): float {
    return (float) ($value ?? 0);
  }

  /**
   * Safe integer conversion
   */
  public static function getSafeInt(float|int|string|null $value): int {
    return (int) ($value ?? 0);
  }

  /**
   * Safe string conversion
   */
  public static function getSafeString(mixed $value): string {
    return (string) ($value ?? '');
  }

  /**
   * Safe boolean conversion
   */
  public static function getSafeBool(mixed $value): bool {
    if (is_string($value)) {
      return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    return (bool) ($value ?? false);
  }

  /**
   * Safe array conversion
   *
   * @template T
   *
   * @param T|array<T>|null $value
   *
   * @return array<int, T>
   */
  public static function getSafeArray(mixed $value): array {
    if ($value === null) {
      return [];
    }

    return is_array($value) ? $value : [$value];
  }

  /**
   * Get safe email (returns empty string if invalid)
   */
  public static function getSafeEmail(mixed $value): string {
    $email = self::getSafeString($value);
    $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL);

    return is_string($validEmail) ? $email : '';
  }

  /**
   * Get safe URL (returns empty string if invalid)
   */
  public static function getSafeUrl(mixed $value): string {
    $url = self::getSafeString($value);
    $validUrl = filter_var($url, FILTER_VALIDATE_URL);

    return is_string($validUrl) ? $url : '';
  }

  /**
   * Get safe slug (URL friendly string)
   *
   * @throws RuntimeException If preg_replace returns null
   */
  public static function getSafeSlug(mixed $value): string {
    $string = self::getSafeString($value);

    $withoutSpecialChars = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);

    if ($withoutSpecialChars === null) {
      throw new RuntimeException('Failed to process string for slug');
    }

    $lowercased = strtolower(trim($withoutSpecialChars));

    $slug = preg_replace('/[\s-]+/', '-', $lowercased);

    if ($slug === null) {
      throw new RuntimeException('Failed to process string for slug');
    }

    return $slug;
  }
}