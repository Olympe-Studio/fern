<?php

declare(strict_types=1);

namespace Fern\Core;

use Fern\Core\CLI\FernCLI;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\I18N\I18N;
use Fern\Core\Services\Mailer\Mailer;
use Fern\Core\Services\Router\Router;
use Fern\Core\Services\Wordpress\Images;
use Fern\Core\Services\Wordpress\Wordpress;
use Fern\Core\Utils\Autoloader;
use Fern\Core\Wordpress\Events;
use Twig\Error\RuntimeError;

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
    I18N::boot();
    Autoloader::load();

    if (defined('WP_CLI') && constant('WP_CLI')) {
      FernCLI::boot();
    } else {
      Mailer::boot();
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
    Events::addHandlers('after_setup_theme', static function () {
      $theme = Config::get('theme', []);
      $themeSupport = $theme['support'] ?? [];
      $menus = $theme['menus'] ?? [];

      foreach ($themeSupport as $feature => $value) {
        if (is_bool($value) && $value) {
          add_theme_support($feature);
          continue;
        }

        if (is_bool($value) && !$value) {
          continue;
        }

        add_theme_support($feature, $value);
      }

      register_nav_menus($menus);
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
    Events::trigger('qm/start', 'fern:boot');

    Config::boot($config);
    self::boot();

    Events::trigger('qm/stop', 'fern:boot');
    Events::trigger('fern:core:after_boot');

    if (class_exists('\App\App') && method_exists('\App\App', 'boot')) {
      \App\App::boot();
    } else {
      throw new RuntimeError('App class not found. You need to create an App.php class in the App namespace with a boot static method.');
    }
  }
}
