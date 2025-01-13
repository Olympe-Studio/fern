<?php

declare(strict_types=1);

namespace Fern\Logger;

use Fern\Core\Config;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;

/**
 * Simple WordPress File Logger
 *
 * Logs messages to WordPress debug.log or custom log files
 */
class Logger extends Singleton {
  /**
   * Log file path
   *
   * @var string
   */
  private string $logFilePath;

  /**
   * Constructor
   *
   * @throws \RuntimeException If unable to create log directory
   */
  public function __construct() {
    /** @var string|null */
    $configPath = Config::get('debug.log_file_path');
    $this->logFilePath = $configPath ?? self::getLogFolder() . '/debug.log';
  }

  /**
   * Get the log folder
   *
   * @throws \RuntimeException If unable to create log directory
   * @return string
   */
  public static function getLogFolder(): string {
    /** @var bool|string */
    $wpDebugLog = defined('WP_DEBUG_LOG') ? constant('WP_DEBUG_LOG') : false;

    $path = is_bool($wpDebugLog)
      ? Fern::getRoot() . '/logs'
      : (string) $wpDebugLog;

    $path = str_ends_with($path, 'debug.log')
      ? dirname($path)
      : rtrim($path, '/');

    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
      throw new \RuntimeException(
        sprintf('Directory "%s" was not created', $path)
      );
    }

    return $path;
  }

  public static function getLogFilePath(): string {
    $instance = self::getInstance();
    return $instance->logFilePath;
  }

  /**
   * Log an error message
   *
   * @param string|\Stringable $message Message to log
   * @return void
   */
  public static function error(string|\Stringable $message): void {
    self::log('ERROR', (string) $message);
  }

  /**
   * Log a warning message
   *
   * @param string|\Stringable $message Message to log
   * @return void
   */
  public static function warning(string|\Stringable $message): void {
    self::log('WARNING', (string) $message);
  }

  /**
   * Log an info message
   *
   * @param string|\Stringable $message Message to log
   * @return void
   */
  public static function info(string|\Stringable $message): void {
    self::log('INFO', (string) $message);
  }

  /**
   * Log a debug message
   *
   * @param string|\Stringable $message Message to log
   * @return void
   */
  public static function debug(string|\Stringable $message): void {
    self::log('DEBUG', (string) $message);
  }

  /**
   * Main logging function
   *
   * @param string $level Log level
   * @param string $message Message to log
   * @return void
   */
  private static function log(string $level, string $message): void {
    if (empty($message)) {
      return;
    }

    /** @var string */
    $timestamp = current_time('mysql');
    $logEntry = sprintf('[%s - %s]: %s%s', $timestamp, $level, $message, PHP_EOL);

    if (defined('WP_DEBUG') && WP_DEBUG === true) {
      error_log($logEntry, 3, self::getLogFilePath());
    }
  }
}
