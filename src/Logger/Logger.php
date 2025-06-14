<?php

declare(strict_types=1);

namespace Fern\Core\Logger;

use Fern\Core\Config;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
use JsonException;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Simple WordPress File Logger
 *
 * Logs messages to WordPress debug.log or custom log files
 */
class Logger extends Singleton {
  /**
   * Default log file name
   */
  private const DEFAULT_LOG_FILE = 'debug.log';

  /**
   * Log file path
   */
  private string $logFilePath;

  /**
   * Log file name
   */
  private string $logFileName;

  /**
   * Constructor
   *
   * @param string $logFileName The name of the log file. Defaults to 'debug.log'.
   * @throws RuntimeException If unable tocreate log directory
   */
  public function __construct(string $logFileName = self::DEFAULT_LOG_FILE) {
    $this->setLogFileName($logFileName);
  }

  /**
   * Set the log file name
   *
   * @param string $logFileName The name of the log file.
   */
  private function setLogFileName(string $logFileName = self::DEFAULT_LOG_FILE): void {
    $logFileName = empty($logFileName) ? self::DEFAULT_LOG_FILE : $logFileName;

    $this->logFileName = $logFileName;
    $this->logFilePath = self::getLogFolder() . '/' . $this->logFileName;
  }

  /**
   * Get the log folder
   *
   * @throws RuntimeException If unable to create log directory
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
      throw new RuntimeException(
        sprintf('Directory "%s" was not created', $path),
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
   * @param string|Stringable $message    Message to log
   * @param mixed             ...$context Additional context data
   */
  public static function error(string|Stringable $message, mixed ...$context): void {
    self::log('ERROR', (string) $message, ...$context);
  }

  /**
   * Log a warning message
   *
   * @param string|Stringable $message    Message to log
   * @param mixed             ...$context Additional context data
   */
  public static function warning(string|Stringable $message, mixed ...$context): void {
    self::log('WARNING', (string) $message, ...$context);
  }

  /**
   * Log an info message
   *
   * @param string|Stringable $message    Message to log
   * @param mixed             ...$context Additional context data
   */
  public static function info(string|Stringable $message, mixed ...$context): void {
    self::log('INFO', (string) $message, ...$context);
  }

  /**
   * Log a debug message
   *
   * @param string|Stringable $message    Message to log
   * @param mixed             ...$context Additional context data
   */
  public static function debug(string|Stringable $message, mixed ...$context): void {
    self::log('DEBUG', (string) $message, ...$context);
  }

  /**
   * Format context data for logging
   *
   * @param mixed ...$context Context data to format
   *
   * @return string
   */
  private static function formatContext(mixed ...$context): string {
    if (empty($context)) {
      return '';
    }

    // If first context argument is an array, use it directly
    if (count($context) === 1 && is_array($context[0])) {
      $contextData = $context[0];
    } else {
      // Otherwise combine all arguments
      $contextData = $context;
    }

    try {
      return ' Context: ' . json_encode($contextData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      @error_log('Error encoding context data: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Main logging function
   *
   * @param string $level      Log level
   * @param string $message    Message to log
   * @param mixed  ...$context Additional context data
   */
  private static function log(string $level, string $message, mixed ...$context): void {
    if (empty($message)) {
      return;
    }

    try {
      $timestamp = current_time('mysql');
      $contextStr = self::formatContext(...$context);
      $logEntry = sprintf('[%s - %s]: %s%s%s', $timestamp, $level, $message, $contextStr, PHP_EOL);
      @error_log($logEntry, 3, self::getLogFilePath());
    } catch (Throwable $e) {
      // Silently continue if logging fails
      return;
    }
  }

  /**
   * Use a specific log file for subsequent logging calls.
   *
   * @param string|null $logFileName The name of the log file to use.  If null or 'default', uses the default log file.
   * @return void
   */
  public static function useLogger(?string $logFileName = null): void {
    if (is_null($logFileName) || $logFileName === 'default') {
      $logFileName = self::DEFAULT_LOG_FILE;
    }

    $logger = self::getInstance();
    $logger->setLogFileName($logFileName);
  }
}
