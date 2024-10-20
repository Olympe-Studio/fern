<?php

declare(strict_types=1);

namespace Fern\Core\Utils;

use Fern\Core\Factory\Singleton;
use Fern\Core\Wordpress\Events;

/**
 * Cache class for managing in-memory and persistent caches with expiration.
 *
 * This class implements a caching mechanism with two levels:
 * 1. In-memory cache for quick access
 * 2. Persistent cache that can be saved and retrieved across requests, with expiration
 *
 */
class Cache extends Singleton {
  /** @var array In-memory cache storage */
  protected array $cache;

  /** @var array Persistent cache storage */
  protected array $persistentCache;

  /** @var int Default expiration time in seconds (4 hours) */
  const DEFAULT_EXPIRATION = 14400; // 4 * 60 * 60

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
   * Initializes the persistent cache from WordPress object cache.
   */
  protected function init(): void {
    $persistent = wp_cache_get('persistent_cache', 'fern:core') ?: [];
    if (!empty($persistent)) {
      $this->persistentCache = $this->removeExpiredItems($persistent);
    }
  }

  /**
   * Static method to retrieve a value from the cache.
   *
   * @param string $key The key to retrieve.
   * @return mixed The cached value or null if not found or expired.
   */
  public static function get(string $key): mixed {
    return self::getInstance()->_get($key);
  }

  /**
   * Static method to set a value in the cache.
   *
   * @param string $key The key to set.
   * @param mixed $value The value to cache.
   * @param bool $persist Whether to store in persistent cache.
   * @param int $expiration Expiration time in seconds (default 4 hours).
   */
  public static function set(string $key, mixed $value, bool $persist = false, int $expiration = self::DEFAULT_EXPIRATION): void {
    self::getInstance()->_set($key, $value, $persist, $expiration);
  }

  /**
   * Retrieves a value from the cache.
   *
   * @param string $key The key to retrieve.
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
   * @param string $key The key to set.
   * @param mixed $value The value to cache.
   * @param bool $persist Whether to store in persistent cache.
   * @param int $expiration Expiration time in seconds (default 4 hours).
   */
  public function _set(string $key, mixed $value, bool $persist = false, int $expiration = self::DEFAULT_EXPIRATION): void {
    $this->cache[$key] = $value;

    if ($persist) {
      $this->persistentCache[$key] = [
        'value' => $value,
        'expires' => time() + $expiration
      ];
    }
  }

  /**
   * Checks if a cache item is expired.
   *
   * @param array $item The cache item to check.
   * @return bool True if expired, false otherwise.
   */
  protected function isExpired(array $item): bool {
    return isset($item['expires']) && $item['expires'] < time();
  }

  /**
   * Removes expired items from the given cache array.
   *
   * @param array $cache The cache array to clean.
   * @return array The cleaned cache array.
   */
  protected function removeExpiredItems(array $cache): array {
    return array_filter($cache, fn($item) => !$this->isExpired($item));
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
    wp_cache_delete('persistent_cache', 'fern:core');
  }

  /**
   * Saves the persistent cache to WordPress object cache.
   */
  public static function save(): void {
    $cache = self::getInstance();
    $cleanPersistentCache = $cache->removeExpiredItems($cache->getCaches()['persistent']);
    wp_cache_set('persistent_cache', $cleanPersistentCache, 'fern:core');
  }
}
