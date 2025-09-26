<?php

declare(strict_types=1);

namespace Fern\Core\Services\Performance;

use Fern\Core\Fern;

/**
 * Performance monitoring wrapper for Query Monitor integration
 *
 * Provides a standardized interface for timing operations and monitoring
 * performance improvements in the Fern framework.
 */
class PerformanceMonitor {
  /** @var bool Whether performance monitoring is enabled */
  private static bool $enabled = false;

  /** @var array<string, float> Active timers with their start times */
  private static array $activeTimers = [];

  /** @var array<string, array{total_time: float, memory_delta: int, calls: int}> Timer statistics */
  private static array $timerStats = [];

  /**
   * Initialize performance monitoring
   */
  public static function init(): void {
    // Always enable in development mode for standalone profiling
    // Don't check for QM here as WordPress isn't loaded yet
    self::$enabled = Fern::isDev();

    if (self::$enabled) {
      // Register shutdown hook to optionally integrate with QM later
      register_shutdown_function([self::class, 'enableQMIntegrationIfAvailable']);
    }
  }

  /**
   * Enable Query Monitor integration if available (called later when WP is loaded)
   */
  public static function enableQMIntegrationIfAvailable(): void {
    // This will be called after WordPress is fully loaded
    // We can safely check for QM functions here
  }

  /**
   * Check if performance monitoring is enabled
   */
  public static function isEnabled(): bool {
    return self::$enabled;
  }

  /**
   * Start a performance timer
   *
   * @param string $name Timer name (will be prefixed with 'fern:')
   * @param string $category Optional category for grouping (e.g., 'controller', 'cache')
   */
  public static function start(string $name, string $category = 'general'): void {
    if (!self::$enabled) {
      return;
    }

    $timerName = self::formatTimerName($name, $category);

    // Store start time and memory usage
    self::$activeTimers[$timerName] = [
      'start_time' => microtime(true),
      'start_memory' => memory_get_usage(true),
    ];

    // Start QM timer only if WordPress is loaded
    if (function_exists('do_action') && function_exists('QM')) {
      do_action('qm/start', $timerName);
    }
  }

  /**
   * Stop a performance timer
   *
   * @param string $name Timer name
   * @param string $category Optional category for grouping
   */
  public static function stop(string $name, string $category = 'general'): void {
    if (!self::$enabled) {
      return;
    }

    $timerName = self::formatTimerName($name, $category);

    // Stop QM timer only if WordPress is loaded
    if (function_exists('do_action') && function_exists('QM')) {
      do_action('qm/stop', $timerName);
    }

    // Calculate our own statistics
    if (isset(self::$activeTimers[$timerName])) {
      $startData = self::$activeTimers[$timerName];
      $endTime = microtime(true);
      $endMemory = memory_get_usage(true);

      $duration = $endTime - $startData['start_time'];
      $memoryDelta = $endMemory - $startData['start_memory'];

      // Update statistics
      if (!isset(self::$timerStats[$timerName])) {
        self::$timerStats[$timerName] = [
          'total_time' => 0.0,
          'memory_delta' => 0,
          'calls' => 0,
        ];
      }

      self::$timerStats[$timerName]['total_time'] += $duration;
      self::$timerStats[$timerName]['memory_delta'] += $memoryDelta;
      self::$timerStats[$timerName]['calls']++;

      unset(self::$activeTimers[$timerName]);
    }
  }

  /**
   * Record a lap time (for iterative operations)
   *
   * @param string $name Timer name
   * @param string $category Optional category for grouping
   */
  public static function lap(string $name, string $category = 'general'): void {
    if (!self::$enabled) {
      return;
    }

    $timerName = self::formatTimerName($name, $category);

    // Record QM lap only if WordPress is loaded
    if (function_exists('do_action') && function_exists('QM')) {
      do_action('qm/lap', $timerName);
    }
  }

  /**
   * Log a debug message (stored internally, optionally sent to QM)
   *
   * @param mixed $message Message to log
   * @param string $category Category for grouping
   */
  public static function debug($message, string $category = 'fern'): void {
    if (!self::$enabled) {
      return;
    }

    // Store internally for our own reporting
    // (In a real implementation, we'd use a proper debug log)
    
    // Only use QM if WordPress is loaded
    if (function_exists('do_action') && function_exists('QM')) {
      do_action('qm/debug', $message, ['category' => $category]);
    }
  }

  /**
   * Time a callable function
   *
   * @param callable $callback Function to time
   * @param string $name Timer name
   * @param string $category Optional category for grouping
   * @return mixed Return value of the callback
   */
  public static function time(callable $callback, string $name, string $category = 'general') {
    self::start($name, $category);

    try {
      $result = $callback();
      return $result;
    } finally {
      self::stop($name, $category);
    }
  }

  /**
   * Get performance statistics
   *
   * @return array<string, array{total_time: float, memory_delta: int, calls: int, avg_time: float}>
   */
  public static function getStats(): array {
    $stats = [];

    foreach (self::$timerStats as $timerName => $data) {
      $stats[$timerName] = [
        'total_time' => $data['total_time'],
        'memory_delta' => $data['memory_delta'],
        'calls' => $data['calls'],
        'avg_time' => $data['calls'] > 0 ? $data['total_time'] / $data['calls'] : 0.0,
      ];
    }

    return $stats;
  }

  /**
   * Format timer name with category prefix
   *
   * @param string $name Timer name
   * @param string $category Category
   * @return string Formatted timer name
   */
  private static function formatTimerName(string $name, string $category): string {
    return "fern:{$category}:{$name}";
  }

  /**
   * Clear all performance statistics
   */
  public static function clearStats(): void {
    self::$timerStats = [];
    self::$activeTimers = [];
  }

  /**
   * Generate a performance report
   *
   * @return array{enabled: bool, active_timers: int, completed_timers: int, stats: array}
   */
  public static function getReport(): array {
    return [
      'enabled' => self::$enabled,
      'active_timers' => count(self::$activeTimers),
      'completed_timers' => count(self::$timerStats),
      'stats' => self::getStats(),
    ];
  }
}
