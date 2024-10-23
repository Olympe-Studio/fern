<?php declare(strict_types=1);

namespace Fern\Core\Utils;

use InvalidArgumentException;
use JsonException;

/**
 * JSON utility class with modern PHP features and improved error handling.
 */
final class JSON {
  /**
   * Default JSON encoding options for consistent output
   */
  private const DEFAULT_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

  /**
   * Default JSON decoding depth
   */
  private const DEFAULT_DEPTH = 512;

  /**
   * Valid flags for json_validate function
   */
  private const VALID_VALIDATE_FLAGS = 0 | JSON_INVALID_UTF8_IGNORE;

  /**
   * Validates a JSON string
   *
   * @param string      $json  JSON string to validate
   * @param int<1, max> $depth Maximum nesting depth*
   * @param int         $flags Only 0 or JSON_INVALID_UTF8_IGNORE are valid
   *
   * @return bool True if the JSON string is valid
   */
  public static function validate(
      string $json,
      int $depth = self::DEFAULT_DEPTH,
      int $flags = 0,
  ): bool {
    $validatedFlags = $flags & self::VALID_VALIDATE_FLAGS;

    if (function_exists('json_validate')) {
      /** @phpstan-ignore-next-line */
      return json_validate($json, $depth, $validatedFlags);
    }

    try {
      json_decode($json, true, $depth, $flags | JSON_THROW_ON_ERROR);

      return true;
    } catch (JsonException) {
      return false;
    }
  }

  /**
   * Encodes data into a JSON string
   *
   * @param mixed    $data  The data to be encoded
   * @param int|null $flags JSON encoding options
   *
   * @return string|false JSON encoded string or false if encoding fails
   *
   * @throws JsonException If encoding fails
   */
  public static function encode(
      mixed $data,
      ?int $flags = null,
  ): string|false {
    return json_encode(
        $data,
        $flags ?? self::DEFAULT_ENCODE_FLAGS,
    );
  }

  /**
   * Decodes a JSON string into PHP data
   *
   *
   * @param string      $json        JSON string to be decoded
   * @param bool        $associative When true, objects will be converted to associative arrays
   * @param int<1, max> $depth       Maximum nesting depth
   * @param int         $flags       Bitmask of JSON decode options
   *
   * @return mixed|null Returns the decoded value or null if invalid
   *
   * @throws JsonException When JSON_THROW_ON_ERROR is used and decoding fails
   */
  public static function decode(
      string $json,
      bool $associative = false,
      int $depth = self::DEFAULT_DEPTH,
      int $flags = 0,
  ): mixed {
    if (empty($json)) {
      throw new InvalidArgumentException('JSON string cannot be empty');
    }

    try {
      return json_decode(
          $json,
          $associative,
          $depth,
          $flags | JSON_THROW_ON_ERROR,
      );
    } catch (JsonException $e) {
      if ($flags & JSON_THROW_ON_ERROR) {
        throw $e;
      }

      return null;
    }
  }

  /**
   * Decodes a JSON string into an array
   *
   * @param string      $json  JSON string to be decoded
   * @param int<1, max> $depth Maximum nesting depth
   * @param int         $flags Bitmask of JSON decode options
   *
   * @return array<mixed> Decoded array
   *
   * @throws JsonException If decoding fails
   */
  public static function decodeToArray(
      string $json,
      int $depth = self::DEFAULT_DEPTH,
      int $flags = 0,
  ): array {
    $result = self::decode($json, true, $depth, $flags | JSON_THROW_ON_ERROR);

    if (!is_array($result)) {
      throw new JsonException('Decoded JSON is not an array');
    }

    return $result;
  }

  /**
   * Pretty prints a JSON string
   *
   * @param mixed $data The data to be encoded
   *
   * @return string Formatted JSON string
   *
   * @throws JsonException If encoding fails
   */
  public static function pretty(mixed $data): string|false {
    return self::encode(
        $data,
        self::DEFAULT_ENCODE_FLAGS | JSON_PRETTY_PRINT,
    );
  }
}
