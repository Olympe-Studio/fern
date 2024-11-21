<?php

declare(strict_types=1);

namespace Fern\Core\Services\Scheduler;

use Fern\Core\Errors\SchedulerParsingError;
use Fern\Core\Factory\Singleton;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use InvalidArgumentException;

/**
 * A better way of working with WordPress cron jobs.
 */
class Scheduler extends Singleton {
  /** @var array<string, Task> */
  private static array $tasks = [];

  /**
   * Get all the tasks currently scheduled.
   *
   * @return array<string, Task>
   */
  public static function getTasks(): array {
    return self::$tasks;
  }

  /**
   * Get all the schedules.
   *
   * @return array<int|string, array{interval: int, display: string}>
   */
  public static function getSchedules(): array {
    return wp_get_schedules();
  }

  /**
   * Create a schedule from a string that match pattern "every_{number}_{(seconds|minutes|hours|days)}"
   *
   * @param string $str The schedule string
   *
   * @throws SchedulerParsingError
   */
  public static function createSchedule(string $str): void {
    $existingSchedules = wp_get_schedules();

    if (isset($existingSchedules[$str])) {
      return;
    }

    $schedule = self::parseScheduleString($str);

    /**
     * Add the schedule to the cron schedules.
     */
    Filters::on('cron_schedules', function ($schedules) use ($str, $schedule) {
      $schedules[$str] = $schedule;

      return $schedules;
    });
  }

  /**
   * Schedule a hook.
   *
   * @param string       $taskName The name of the task to schedule
   * @param string       $interval The interval to schedule the task
   * @param callable     $callback The callback to schedule
   * @param array<mixed> $args     The arguments to pass to the callback
   * @param int          $startAt  Unix timestamp when to start the task, -1 for now
   *
   * @throws SchedulerParsingError    If the interval string is invalid
   * @throws InvalidArgumentException If the callback is not callable
   */
  public static function schedule(string $taskName, string $interval, $callback, array $args = [], int $startAt = -1): Task {
    self::createSchedule($interval);

    if ($startAt === -1) {
      $startAt = time();
    }

    if (empty($taskName)) {
      throw new InvalidArgumentException('Task name cannot be empty');
    }

    if (!wp_next_scheduled($taskName)) {
      wp_schedule_event($startAt, $interval, $taskName, $args);
    }

    Events::on($taskName, $callback, 10, 1);
    $task = new Task($taskName, $interval, $callback, $args, $startAt);
    self::$tasks[$taskName] = $task;

    return $task;
  }

  /**
   * Get a task by its name
   *
   * @param string $taskName The name of the task
   *
   * @return Task|null The task if found, null otherwise
   */
  public static function getTask(string $taskName): ?Task {
    return self::$tasks[$taskName] ?? null;
  }

  /**
   * Unschedule a hook by its task name.
   *
   * @param string $taskName The name of the task to unschedule
   * @param int    $when     Unix  timestamp when to unschedule the task, -1 for now
   *
   * @return bool True if the task was unscheduled, false if it wasn't scheduled
   */
  public static function unschedule(string $taskName, int $when = -1): bool {
    if (empty($taskName)) {
      throw new InvalidArgumentException('Task name must be a non-empty string');
    }

    $unscheduled = false;

    if ($when === -1) {
      $when = wp_next_scheduled($taskName);

      if (!$when) {
        return false;
      }
    }

    $unscheduled = wp_unschedule_event($when, $taskName);

    if ($unscheduled) {
      Events::removeHandlers($taskName);
      unset(self::$tasks[$taskName]);
    }

    return $unscheduled;
  }

  /**
   * Parse a schedule string into an interval and display text.
   *
   * @param string $str Match pattern "every_{number}_{(seconds|minutes|hours|days)}"
   *
   * @return array{interval: int, display: string}
   *
   * @throws SchedulerParsingError
   */
  private static function parseScheduleString(string $str): array {
    if (!preg_match('/^every_(\d+)_(seconds|minutes|hours|days)$/', $str, $matches)) {
      throw new SchedulerParsingError('Invalid schedule string. Received: ' . $str . ' but expected pattern "every_{number}_{(seconds|minutes|hours|days)}" with a number greater than 1 and a valid unit.');
    }

    $interval = intval($matches[1]);
    $unit = $matches[2];

    if ($interval < 1 || !in_array($unit, ['seconds', 'minutes', 'hours', 'days'], true)) {
      throw new SchedulerParsingError('Invalid schedule string. Received: ' . $str . ' but expected pattern "every_{number}_{(seconds|minutes|hours|days)}" with an integer greater than 0 and a valid unit.');
    }

    // Convert to seconds based on unit
    $secondsMultipliers = [
      'seconds' => 1,
      'minutes' => 60,
      'hours' => 3600,
      'days' => 86400,
    ];

    if (($interval > PHP_INT_MAX / $secondsMultipliers[$unit])) {
      throw new SchedulerParsingError('Interval is too large to be scheduled. Interval can\'t be greater than `PHP_INT_MAX` value when converted to timestamp');
    }

    $totalSeconds = $interval * $secondsMultipliers[$unit];
    $display = sprintf('Every %d %s', $interval, $unit);

    return [
      'interval' => $totalSeconds,
      'display' => $display,
    ];
  }
}
