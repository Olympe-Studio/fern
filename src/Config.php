<?php

declare(strict_types=1);

namespace Fern\Core;

use Fern\Core\Errors\FernConfigurationExceptions;
use Fern\Core\Factory\Singleton;
use Fern\Core\Utils\JSON;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
/**
 * @phpstan-type ConfigValue array<string, mixed>|mixed
 */
class Config extends Singleton {
  /**
   * @var array<string, ConfigValue>
   */
  protected array $config;

  /**
   * Constructor initializes the config array
   */
  protected function __construct() {
    $this->config = [];
  }

  /**
   * Get a configuration value by key
   *
   * @param string $key     The configuration key. Support dot notation like `seo.flags.sitemap`.
   * @param mixed  $default The default value if the key is not found
   *
   * @return ?mixed The configuration value or default
   */
  public static function get(string $key, mixed $default = null): mixed {
    $keys = explode('.', $key);
    $value = static::getInstance()->config;

    foreach ($keys as $subKey) {
      if (!is_array($value) || !array_key_exists($subKey, $value)) {
        return $default;
      }

      $value = $value[$subKey];
    }

    return $value;
  }

  /**
   * Checks if a configuration exists.
   *
   * @param string $key The configuration key. Support dot notation like `seo.flags.sitemap`.
   *
   * @return boolean
   */
  public static function has(string $key): bool {
    return static::get($key, null) !== null;
  }

  /**
   * Get all configuration values
   *
   * @return array<string, ConfigValue>
   */
  public static function all(): array {
    $instance = static::getInstance();

    return $instance->config;
  }

  /**
   * Show the config as an array
   *
   * @return array<string, ConfigValue>
   */
  public static function toArray(): array {
    return static::all();
  }

  /**
   * Show the config as json
   */
  public static function toJson(): string {
    return JSON::encode(self::toArray()) ?: '';
  }

  /**
   * Set the entire configuration array
   *
   * @param array<string, ConfigValue> $config
   */
  public function setConfig(array $config): void {
    $this->config = $config;
  }

  /**
   * Boot the configuration
   *
   * @param array<string, ConfigValue> $config
   */
  public static function boot(array $config): void {
    $instance = static::getInstance();

    if (!isset($config['root'])) {
      throw new FernConfigurationExceptions('Root path is required.');
    }

    /**
     * Allows to reconfigure fern.
     *
     * @return array
     */
    $config = Filters::apply('fern:core:config', $config);

    if (!is_array($config)) {
      throw new FernConfigurationExceptions('Config must be of type array, received: `' . gettype($config) . '`.');
    }

    $instance->setConfig($config);

    /**
     * Trigger after the config has been booted.
     *
     * @param Config $config The config instance
     */
    Events::trigger('fern:core:config:after_boot', $instance);
  }
}
