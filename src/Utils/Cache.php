<?php

declare(strict_types=1);

namespace Fern\Core\Utils;

use Closure;
use Exception;
use Fern\Core\Factory\Singleton;
use Fern\Core\Wordpress\Events;
use ReflectionFunction;
use RuntimeException;
use Throwable;

/**
 * Cache class for managing in-memory and persistent caches with expiration.
 *
 * This class implements a caching mechanism with two levels:
 * 1. In-memory cache for quick access
 * 2. Persistent cache that can be saved and retrieved across requests, with expiration
 */
class Cache extends Singleton {
  /** @var string The option name for storing persistent cache */
  const PERSISTENT_CACHE_OPTION = 'fern:core:persistent_cache';

  /** @var int Default expiration time in seconds (4 hours) */
  const DEFAULT_EXPIRATION = 14400; // 4 * 60 * 60

  /** @var array In-memory cache storage */
  protected array $cache;

  /** @var array Persistent cache storage */
  protected array $persistentCache;

  /** @var bool Track if persistent cache has been modified */
  protected bool $isDirty = false;

  /**
   * Constructor for the Cache class.
   * Initializes cache arrays and loads persistent cache.
   */
  public function __construct() {
    $this->cache = [];
    $this->persistentCache = [];
    $this->init();

    Events::addHandlers('shutdown', [$this, 'save']);
  }

  /**
   * Returns true if the persistent cache has been modified.
   */
  public function isDirty(): bool {
    return $this->isDirty;
  }

  /**
   * Resets the dirty state of the persistent cache.
   */
  public function setDirtyState(bool $isDirty): void {
    $this->isDirty = $isDirty;
  }

  /**
   * Static method to retrieve a value from the cache.
   *
   * @param string $key The key to retrieve.
   *
   * @return mixed The cached value or null if not found or expired.
   */
  public static function get(string $key): mixed {
    return self::getInstance()->_get($key);
  }

  /**
   * Static method to set a value in the cache.
   *
   * @param string $key        The key to set.
   * @param mixed  $value      The value to cache.
   * @param bool   $persist    Whether to store in persistent cache.
   * @param int    $expiration Expiration time in seconds (default 4 hours).
   */
  public static function set(string $key, mixed $value, bool $persist = false, int $expiration = self::DEFAULT_EXPIRATION): void {
    self::getInstance()->_set($key, $value, $persist, $expiration);
  }

  /**
   * Retrieves a value from the cache.
   *
   * @param string $key The key to retrieve.
   *
   * @return mixed The cached value or null if not found or expired.
   */
  public function _get(string $key): mixed {
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }

    if (isset($this->persistentCache[$key])) {
      $item = $this->persistentCache[$key];

      if ($this->isExpired($item)) {
        unset($this->persistentCache[$key]);

        return null;
      }

      return $item['value'];
    }

    return null;
  }

  /**
   * Sets a value in the cache.
   *
   * @param string $key        The key to set.
   * @param mixed  $value      The value to cache.
   * @param bool   $persist    Whether to store in persistent cache.
   * @param int    $expiration Expiration time in seconds (default 4 hours).
   */
  public function _set(string $key, mixed $value, bool $persist = false, int $expiration = self::DEFAULT_EXPIRATION): void {
    $this->cache[$key] = $value;

    if ($persist) {
      $this->persistentCache[$key] = [
        'value' => $value,
        'expires' => time() + $expiration,
      ];

      $this->isDirty = true;
    }
  }

  /**
   * Returns both in-memory and persistent caches.
   *
   * @return array An array containing both cache arrays.
   */
  public function getCaches(): array {
    return [
      'inmemory' => $this->cache,
      'persistent' => $this->persistentCache,
    ];
  }

  /**
   * Memoizes a callback's result based on its dependencies.
   * Similar to React's useMemo, it will only recompute the value if dependencies change.
   *
   * @param callable $callback     The callback function to memoize
   * @param array    $dependencies Array of values that determine if cache should be invalidated
   * @param int      $expiration   Cache expiration time in seconds
   * @param bool     $persist      Whether to persist the cache across requests
   *
   * @return mixed The memoized result
   *
   * @throws \InvalidArgumentException If dependencies are not serializable
   * @throws RuntimeException          If callback execution fails
   */
  public static function useMemo(
      callable $callback,
      array $dependencies = [],
      int $expiration = self::DEFAULT_EXPIRATION,
      bool $persist = false,
  ): mixed {
    $instance = self::getInstance();

    return $instance->_useMemo($callback, $dependencies, $expiration, $persist);
  }

  /**
   * Flushes both in-memory and persistent caches.
   */
  public function _flush(): void {
    $this->cache = [];
    $this->persistentCache = [];
  }

  /**
   * Static method to flush all caches, including WordPress object cache.
   */
  public static function flush(): void {
    $cache = self::getInstance();
    $cache->_flush();
    $cache->setDirtyState(true);
    delete_option(self::PERSISTENT_CACHE_OPTION);
  }

  /**
   * Saves the persistent cache to WordPress object cache.
   */
  public static function save(): void {
    $cache = self::getInstance();
    $isDirty = $cache->isDirty();

    if (!$isDirty) {
      return;
    }

    $persistentCache = $cache->getCaches()['persistent'];

    // Maybe we flushed the cache?
    if (empty($persistentCache)) {
      delete_option(self::PERSISTENT_CACHE_OPTION);

      return;
    }

    $cleanPersistentCache = $cache->removeExpiredItems($persistentCache);

    if (count($cleanPersistentCache) !== count($persistentCache)) {
      $isDirty = true;
    }

    if ($isDirty) {
      update_option(self::PERSISTENT_CACHE_OPTION, $cleanPersistentCache, true);
    }

    if (empty($cleanPersistentCache)) {
      delete_option(self::PERSISTENT_CACHE_OPTION);
    }

    $cache->setDirtyState(false);
  }

  /**
   * Initializes the persistent cache from WordPress object cache.
   */
  protected function init(): void {
    $persistent = get_option(self::PERSISTENT_CACHE_OPTION, []);

    if (!empty($persistent)) {
      $this->persistentCache = $this->removeExpiredItems($persistent);

      // If items were removed due to expiration, mark as dirty
      if (count($persistent) !== count($this->persistentCache)) {
        $this->isDirty = true;
      }
    }
  }

  /**
   * Checks if a cache item is expired.
   *
   * @param array $item The cache item to check.
   *
   * @return bool True if expired, false otherwise.
   */
  protected function isExpired(array $item): bool {
    return isset($item['expires']) && $item['expires'] < time();
  }

  /**
   * Removes expired items from the given cache array.
   *
   * @param array $cache The cache array to clean.
   *
   * @return array The cleaned cache array.
   */
  protected function removeExpiredItems(array $cache): array {
    return array_filter($cache, fn($item) => !$this->isExpired($item));
  }

  /**
   * Internal implementation of useMemo
   *
   * @param callable $callback     The callback function to memoize
   * @param array    $dependencies Array of values that determine if cache should be invalidated
   * @param int      $expiration   Cache expiration time in seconds
   * @param bool     $persist      Whether to persist the cache across requests
   */
  protected function _useMemo(
      callable $callback,
      array $dependencies,
      int $expiration,
      bool $persist,
  ): mixed {
    $key = $this->generateMemoKey($callback, $dependencies);
    $cache = $this;

    return function (...$args) use ($cache, $key, $callback, $persist, $expiration) {
      $cached = $cache->_get($key);

      if ($cached !== null) {
        return $cached;
      }

      try {
        $result = $callback(...$args);
        $this->_set($key, $result, $persist, $expiration);

        return $result;
      } catch (Throwable $e) {
        throw new RuntimeException(
            'Failed to execute memoized callback: ' . $e->getMessage(),
            0,
            $e,
        );
      }
    };
  }

  /**
   * Generates a unique cache key for the callback and its dependencies
   *
   * @throws InvalidArgumentException
   */
  protected function generateMemoKey(callable $callback, array $dependencies): string {
    try {
      if ($callback instanceof Closure) {
        $reflection = new ReflectionFunction($callback);
        $fileName = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if ($fileName && $startLine && $endLine) {
          $file = file($fileName);
          $code = implode('', array_slice($file, $startLine - 1, $endLine - $startLine + 1));
        } else {
          $code = spl_object_hash($callback);
        }

        // Include file name and lines to differentiate identical functions in different files
        $callbackKey = $fileName . ':' . $startLine . ':' . $code;
      } elseif (is_array($callback)) {
        // Handle array callbacks (e.g., [$object, 'method'])
        if (is_object($callback[0])) {
          $callbackKey = spl_object_hash($callback[0]) . '::' . $callback[1];
        } else {
          $callbackKey = $callback[0] . '::' . $callback[1];
        }
      } elseif (is_string($callback) && is_callable($callback)) {
        // Handle string callbacks (e.g., 'functionName')
        $callbackKey = $callback;
      } else {
        throw new \InvalidArgumentException('Unsupported callback type');
      }

      // Use xxh32 if available, fallback to crc32 for dependencies
      try {
        if (function_exists('hash')) {
          $depsKey = hash('xxh32', serialize($dependencies));
        } else {
          $depsKey = (string)crc32(serialize($dependencies));
        }
      } catch (Exception $e) {
        throw new \InvalidArgumentException(
            'Dependencies must be serializable: ' . $e->getMessage(),
        );
      }

      if (function_exists('hash')) {
        return 'memo_' . hash('xxh32', $callbackKey . '_' . $depsKey);
      }

      return 'memo_' . (string)crc32($callbackKey . '_' . $depsKey);
    } catch (Exception $e) {
      throw new \InvalidArgumentException(
          'Failed to generate memo key: ' . $e->getMessage(),
      );
    }
  }
}
