<?php

declare(strict_types=1);

namespace Fern\Core\Utils;

use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Autoloader class
 */
class Autoloader extends Singleton {
  /** @var string The path to the includes.php file */
  public string $includesPath;

  /** 
   * @var array<string, array<string>> Caches file paths to avoid repeated directory scans
   */
  private static array $filePathCache = [];

  /**
   * Formats a path with trailing slash
   *
   * @param string $path Path to format
   *
   * @return string Path with trailing slash
   */
  private static function addTrailingSlash(string $path): string {
    return rtrim($path, '/\\') . '/';
  }

  public function __construct() {
    $this->includesPath = self::addTrailingSlash(Fern::getRoot()) . 'includes.php';
  }

  /**
   * Get the includes path
   */
  public static function getIncludesPath(): string {
    $instance = self::getInstance();

    return $instance->includesPath;
  }

  /**
   * Load a specific controller on-demand
   * 
   * @param string $controllerClass The fully qualified controller class name
   * 
   * @return bool True if controller was loaded successfully
   */
  public static function loadController(string $controllerClass): bool {

    if (class_exists($controllerClass, false)) {
      return true; // Already loaded
    }

    // Try to get controller info from ControllerResolver registry
    $resolver = \Fern\Core\Services\Controller\ControllerResolver::getInstance();
    $registry = $resolver->getControllerRegistry();

    if (isset($registry[$controllerClass])) {
      $filePath = $registry[$controllerClass]['file_path'];
      if (file_exists($filePath)) {
        require_once $filePath;

        $loaded = class_exists($controllerClass, false);
        return $loaded;
      }
    }

    // Fallback: Try to find controller file by convention
    $root = Fern::getRoot();
    $className = basename(str_replace('\\', '/', $controllerClass));
    $controllerPath = self::addTrailingSlash($root) . 'App/Controllers/' . $className . '.php';

    if (file_exists($controllerPath)) {
      require_once $controllerPath;

      $loaded = class_exists($controllerClass, false);
      return $loaded;
    }

    return false;
  }

  /**
   * Boot the application
   */
  public static function load(): void {

    $exists = file_exists(self::getIncludesPath());

    // Always load includes.php (contains only underscore files in both dev and prod)
    if (!Fern::isDev() && $exists) {
      require_once self::getIncludesPath();
      return;
    }

    // Get only underscore files (no controllers in any environment)
    $underscoreFiles = self::getUnderscoreFiles();

    // Create includes.php with only underscore files
    if (Fern::isDev() || !$exists) {
      self::createIncludesFile($underscoreFiles);

      require_once self::getIncludesPath();
    }
  }

  /**
   * Get files recursively
   *
   * @param string        $dir    The directory to search in
   * @param callable|null $filter The filter to apply to the files
   *
   * @return array<string>
   */
  private static function getFilesRecursively(string $dir, ?callable $filter = null): array {
    // Return cached results if available
    $cacheKey = $dir . '_' . ($filter ? md5(is_object($filter) ? spl_object_hash($filter) : serialize($filter)) : 'no_filter');
    if (isset(self::$filePathCache[$cacheKey])) {
      return self::$filePathCache[$cacheKey];
    }

    $result = [];

    if (!is_dir($dir) || !is_readable($dir)) {
      return $result;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $fileInfo) {
      // Skip directories, only process files
      if ($fileInfo->isDir()) {
        continue;
      }

      if ($filter === null || $filter($fileInfo)) {
        $result[] = $fileInfo->getPathname();
      }
    }

    sort($result);

    // Cache the results
    self::$filePathCache[$cacheKey] = $result;

    return $result;
  }

  /**
   * Create includes.php file
   *
   * @param array<string> $files The files to include
   */
  private static function createIncludesFile(array $files): void {
    $timestamp = date('Y-m-d H:i:s');
    $env = Fern::isDev() ? 'development' : 'production';
    $fileCount = count($files);

    $content = <<<PHP
<?php
/**
 * Fern Framework Auto-Generated Includes
 *
 * This file is auto-generated. DO NOT MODIFY.
 *
 * Generated: {$timestamp}
 * Environment: {$env}
 * Files: {$fileCount} (underscore files only)
 */

declare(strict_types=1);

PHP;
    $content .= PHP_EOL;
    foreach ($files as $file) {
      $root = Fern::getRoot();
      $relativePath = str_replace($root, '', $file);
      $content .= "require_once __DIR__ . '{$relativePath}';" . PHP_EOL;
    }
    file_put_contents(self::getIncludesPath(), $content);
  }

  /**
   * Get files starting with an underscore
   *
   * In Fern, files starting with an underscore are considered procedural files
   * and are autoloaded.
   *
   * @return array<string>
   */
  private static function getUnderscoreFiles(): array {
    $root = Fern::getRoot();
    $appPath = self::addTrailingSlash($root) . 'App';

    return self::getFilesRecursively($appPath, fn($fileInfo) => $fileInfo->isFile()
      && $fileInfo->getExtension() === 'php'
      && str_starts_with($fileInfo->getFilename(), '_'));
  }
}
