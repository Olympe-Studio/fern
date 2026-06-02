<?php declare(strict_types=1);

namespace Fern\Core\Services\I18N;

use Fern\Core\Config;
use Fern\Core\Fern;
use Fern\Core\Utils\Types;
use Fern\Core\Wordpress\Events;

class I18N {
  private const DEFAULT_DOMAIN = 'fern';

  private const DEFAULT_LANGUAGES_PATH = '/languages';

  private static bool $hasLoadedTextDomain = false;

  private static string $storedLanguagesPath = '';

  private static string $storedDomain = '';

  /**
   * Boot the i18n configuration
   */
  public static function boot(): void {
    $config = Config::get('i18n', []);

    if (!is_array($config) || $config === []) {
      return;
    }

    $path = isset($config['languages_folder_path'])
      ? Types::getSafeString($config['languages_folder_path'])
      : untrailingslashit(Fern::getRoot()) . self::DEFAULT_LANGUAGES_PATH;
    $domain = isset($config['domain']) ? Types::getSafeString($config['domain']) : self::DEFAULT_DOMAIN;
    $path = rtrim($path, '/\\');

    if (!is_dir($path) || !is_readable($path)) {
      wp_mkdir_p($path);
    }

    self::$storedLanguagesPath = $path;
    self::$storedDomain = $domain;

    Events::on('pll_language_defined', [self::class, 'loadTextDomainLate'], 0);
    Events::on('wp', [self::class, 'loadTextDomainLate'], 20);
  }

  /**
   * Loads the text domain after Polylang (or core) has resolved the locale.
   */
  public static function loadTextDomainLate(): void {
    if (self::$hasLoadedTextDomain || self::$storedLanguagesPath === '' || self::$storedDomain === '') {
      return;
    }

    self::loadTextDomain(self::$storedLanguagesPath, self::$storedDomain);
    self::$hasLoadedTextDomain = true;
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

    if ($locale === '') {
      return;
    }

    if (self::tryLoadMoFile($path, $domain, $locale)) {
      return;
    }

    $baseLocale = explode('_', $locale)[0];

    if ($baseLocale !== '' && self::tryLoadMoFile($path, $domain, $baseLocale)) {
      return;
    }

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

    if ($files !== false && $files !== []) {
      load_textdomain($domain, $files[0]);
    }
  }
}
