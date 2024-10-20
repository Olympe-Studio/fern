<?php
declare(strict_types=1);

namespace Fern\Core;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Router\Router;
use Fern\Core\Services\Wordpress\Images;
use Fern\Core\Services\Wordpress\Wordpress;
use Fern\Core\Utils\Autoloader;
use Fern\Core\Wordpress\Events;
use FernCLI;

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
      FernCLI::boot();
    } else {
      // Boot all services
      Wordpress::boot();
      Images::boot();

      // Finally, boot the router last.
      Router::boot();
    }

    self::bootThemeSupport();
  }

  /**
   * Boot the theme support
   *
   * @return void
   */
  private static function bootThemeSupport(): void {
    Events::trigger('after_theme_support', static function() {
      $themeSupport = Config::get('theme_support', []);

      foreach ($themeSupport as $feature => $value) {
        if (is_bool($value) && $value) {
          add_theme_support($feature);
          continue;
        }

        add_theme_support($feature, $value);
        }
    });
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
