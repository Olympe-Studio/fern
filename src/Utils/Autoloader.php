<?php declare(strict_types=1);

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

  public function __construct() {
    $this->includesPath = trailingslashit(Fern::getRoot()) . 'includes.php';
  }

  /**
   * Get the includes path
   */
  public static function getIncludesPath(): string {
    $instance = self::getInstance();

    return $instance->includesPath;
  }

  /**
   * Boot the application
   */
  public static function load(): void {
    $exists = file_exists(self::getIncludesPath());

    // Include the includes.php file
    if (!Fern::isDev() && $exists) {
      require_once self::getIncludesPath();

      return;
    }

    $controllers = self::getControllers();
    $underscoreFiles = self::getUnderscoreFiles();

    $allFiles = array_merge($controllers, $underscoreFiles);

    // Create the includes.php file
    if (Fern::isDev() || !$exists) {
      self::createIncludesFile($allFiles);

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

    return $result;
  }

  /**
   * Create includes.php file
   *
   * @param array<string> $files The files to include
   */
  private static function createIncludesFile(array $files): void {
    $content = <<<PHP
<?php
// This file is auto-generated.
// Do not modify this file directly.
PHP . PHP_EOL;

    foreach ($files as $file) {
      $root = Fern::getRoot();
      $relativePath = str_replace($root, '', $file);
      $content .= "require_once __DIR__ . '{$relativePath}';" . PHP_EOL;
    }

    file_put_contents(self::getIncludesPath(), $content);
  }

  /**
   * Get the controllers
   *
   * @return array<string>
   */
  private static function getControllers(): array {
    $root = Fern::getRoot();
    $dir = trailingslashit($root) . 'App/Controllers';

    return self::getFilesRecursively($dir);
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
    $appPath = trailingslashit($root) . 'App';

    return self::getFilesRecursively($appPath, function ($fileInfo) {
      return $fileInfo->isFile()
        && $fileInfo->getExtension() === 'php'
        && str_starts_with($fileInfo->getFilename(), '_')  ;
    });
  }
}
