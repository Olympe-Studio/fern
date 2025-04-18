<?php declare(strict_types=1);

namespace Fern\Core\Services\I18N;

use Fern\Core\Config;
use Fern\Core\Fern;
use RuntimeException;

class I18N {
  private const DEFAULT_DOMAIN = 'fern';

  private const DEFAULT_LANGUAGES_PATH = '/languages';

  /**
   * Boot the i18n configuration
   *
   * @throws RuntimeException If languages directory is not readable
   */
  public static function boot(): void {
    $config = Config::get('i18n', []);

    /** @phpstan-ignore-next-line */ // Better be cautious
    if (empty($config) || $config === null) {
      return;
    }

    $path = $config['languages_folder_path']
      ?? untrailingslashit(Fern::getRoot()) . self::DEFAULT_LANGUAGES_PATH;
    $domain = $config['domain'] ?? self::DEFAULT_DOMAIN;

    if (!is_dir($path) || !is_readable($path)) {
      wp_mkdir_p($path);
    }

    self::loadTextDomain($path, $domain);
  }

  /**
   * Loads translation files for the current locale
   *
   * @param string $path   The languages folder path
   * @param string $domain The text domain
   */
  public static function loadTextDomain(string $path, string $domain): void {
    $locale = determine_locale();
    $path = rtrim($path, '/\\');

    if (empty($locale)) {
      return;
    }

    // Try loading exact locale match first - e.g. en_US
    if (self::tryLoadMoFile($path, $domain, $locale)) {
      return;
    }

    // Try base locale if exact match fails - e.g. en_US becomes en
    $baseLocale = explode('_', $locale)[0];

    if ($baseLocale && self::tryLoadMoFile($path, $domain, $baseLocale)) {
      return;
    }

    // Try wildcard match as last resort - e.g. en_US becomes en_*
    self::tryLoadWildcardMoFile($path, $domain, $baseLocale);
  }

  /**
   * Attempts to load a specific .mo file
   */
  private static function tryLoadMoFile(string $path, string $domain, string $locale): bool {
    $filePath = "{$path}/{$domain}-{$locale}.mo";

    if (file_exists($filePath)) {
      load_textdomain($domain, $filePath);

      return true;
    }

    return false;
  }

  /**
   * Attempts to load a wildcard .mo file match
   */
  private static function tryLoadWildcardMoFile(string $path, string $domain, string $locale): void {
    $pattern = "{$path}/{$domain}-{$locale}_*.mo";
    $files = glob($pattern);

    if (!empty($files)) {
      load_textdomain($domain, $files[0]);
    }
  }
}
