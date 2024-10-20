<?php
declare(strict_types=1);

namespace Fern\Core;

use Fern\Core\Factory\Singleton;
use Fern\Core\Utils\Autoloader;
use Fern\Core\Wordpress\Events;

class Fern extends Singleton {
  const VERSION = '0.1.0';
  /**
   * Gets Fern current version.
   */
  public static function getVersion(): string {
    return self::VERSION;
  }

  /**
   * Checks if the current environment is development
   *
   * @return bool
   */
  public static function isDev(): bool {
    return defined('WP_ENV') && WP_ENV === 'development';
  }

  /**
   * Checks if the current environment is not development
   *
   * @return bool
   */
  public static function isNotDev(): bool {
    return !self::isDev();
  }

  /**
   * Get the root path
   *
   * @return string
   */
  public static function getRoot(): string {
    return Config::get('root');
  }

  /**
   * Boot the application
   *
   * @return void
   */
  private static function boot(): void {
    Autoloader::load();

    if (defined('WP_CLI') && WP_CLI) {
      require_once __DIR__ . '/CLI/boot.php';
    }
  }

  /**
   * Defines fern configuration and boot the application
   *
   * @param array $config The configuration array
   *
   * @return void
   */
  public static function defineConfig(array $config): void {
    Events::trigger('fern:core:before_boot');

    Config::boot($config);
    self::boot();

    Events::trigger('fern:core:after_boot');
  }
}
