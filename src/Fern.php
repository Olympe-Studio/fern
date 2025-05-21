<?php

declare(strict_types=1);

namespace Fern\Core;

use Fern\Core\CLI\FernCLI;
use Fern\Core\Errors\FernConfigurationExceptions;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Gutenberg\Gutenberg;
use Fern\Core\Services\I18N\I18N;
use Fern\Core\Services\Mailer\Mailer;
use Fern\Core\Services\Router\Router;
use Fern\Core\Services\Wordpress\Images;
use Fern\Core\Services\Wordpress\Wordpress;
use Fern\Core\Utils\Autoloader;
use Fern\Core\Wordpress\Events;

/**
 * @phpstan-type ConfigValue array<string, mixed>|mixed
 */
class Fern extends Singleton {
  const VERSION = '0.1.0';

  /**
   * @var bool|null Cache for development environment status
   */
  private static ?bool $isDev = null;

  /**
   * Gets Fern current version.
   */
  public static function getVersion(): string {
    return self::VERSION;
  }

  /**
   * Checks if the current environment is development
   */
  public static function passed(): bool {
    return Router::passed();
  }

  /**
   * Get the context
   */
  public static function context(): array {
    return Context::get();
  }

  /**
   * Checks if the current environment is development
   */
  public static function isDev(): bool {
    if (self::$isDev !== null) {
      return self::$isDev;
    }

    self::$isDev = defined('WP_ENV') && \WP_ENV === 'development';
    return self::$isDev;
  }

  /**
   * Checks if the current environment is not development
   */
  public static function isNotDev(): bool {
    return !self::isDev();
  }

  /**
   * Get the root path
   */
  public static function getRoot(): string {
    return Config::get('root');
  }

  /**
   * Defines fern configuration and boot the application
   *
   * @param array<string, ConfigValue> $config
   */
  public static function defineConfig(array $config): void {
    /**
     * Trigger before Fern boot.
     *
     * Tipically used to define controllers from within a library
     */
    Events::trigger('fern:core:before_boot');
    Events::trigger('qm/start', 'fern:boot');

    Config::boot($config);
    self::boot();

    Events::trigger('qm/stop', 'fern:boot');
    /**
     * Trigger when Fern is booted.
     *
     * Tipically used to define extra services that needs Fern services.
     */
    Events::trigger('fern:core:after_boot');

    /** @phpstan-ignore-next-line */
    if (class_exists('\App\App') && method_exists('\App\App', 'boot')) {
      \App\App::boot();
    } else {
      throw new FernConfigurationExceptions('App class not found. You need to create an App.php class in the App namespace with a boot static method.');
    }
  }

  /**
   * Boot the application
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
      Gutenberg::boot();
      // Finally, boot the router last.
      Router::boot();
    }

    self::bootThemeSupport();
  }

  /**
   * Boot the theme support
   */
  private static function bootThemeSupport(): void {
    Events::on('after_setup_theme', static function (): void {
      $theme = Config::get('theme', []);
      $themeSupport = $theme['support'] ?? [];
      $menus = $theme['menus'] ?? [];

      foreach ($themeSupport as $feature => $value) {
        if ($value === true) {
          add_theme_support($feature);
          continue;
        }

        if ($value === false) {
          continue;
        }

        add_theme_support($feature, $value);
      }

      register_nav_menus($menus);
    });
  }
}
