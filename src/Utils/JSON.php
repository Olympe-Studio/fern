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
   * Validates a JSON string
   *
   * @param string $json  JSON string to validate
   * @param int    $depth Maximum nesting depth
   * @param int    $flags Bitmask of JSON decode options
   *
   * @return bool True if the JSON string is valid
   */
  public static function validate(
      string $json,
      int $depth = self::DEFAULT_DEPTH,
      int $flags = 0,
  ): bool {
    if (function_exists('json_validate')) {
      return json_validate($json, $depth, $flags);
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
   * @return string JSON encoded string
   *
   * @throws JsonException If encoding fails
   */
  public static function encode(
      mixed $data,
      ?int $flags = null,
  ): string {
    return json_encode(
        $data,
        $flags ?? self::DEFAULT_ENCODE_FLAGS,
    );
  }

  /**
   * Decodes a JSON string into PHP data
   *
   * @template T of array|object
   *
   * @param string $json        JSON string to be decoded
   * @param bool   $associative When true, objects will be converted to associative arrays
   * @param int    $depth       Maximum nesting depth
   * @param int    $flags       Bitmask of JSON decode options
   *
   * @return T|null Returns the decoded value or null if invalid
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
   * @param string $json  JSON string to be decoded
   * @param int    $depth Maximum nesting depth
   * @param int    $flags Bitmask of JSON decode options
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
  public static function pretty(mixed $data): string {
    return self::encode(
        $data,
        self::DEFAULT_ENCODE_FLAGS | JSON_PRETTY_PRINT,
    );
  }
}
