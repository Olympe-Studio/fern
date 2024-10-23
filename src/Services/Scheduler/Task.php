<?php

declare(strict_types=1);

use Fern\Core\Services\Scheduler\Scheduler;

/**
 * Represents a scheduled task with its parameters
 */
class Task {
  /**
   * @param string       $name     The unique name of the task
   * @param string       $interval The interval pattern (e.g. "every_5_minutes")
   * @param mixed        $callback The callback function to execute
   * @param array<mixed> $args     Arguments to pass to the callback
   * @param int          $startAt  Unix timestamp when the task starts, -1 for immediate
   */
  public function __construct(
      private readonly string $name,
      private readonly string $interval,
      private readonly mixed $callback,
      private readonly array $args = [],
      private readonly int $startAt = -1,
  ) {
  }

  /**
   * Get a task by its name
   *
   * @param string $name The name of the task
   */
  public static function getByName(string $name): ?Task {
    return Scheduler::getTask($name);
  }

  /**
   * Get the name of the task
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the interval of the task
   */
  public function getInterval(): string {
    return $this->interval;
  }

  /**
   * Get the callback of the task
   */
  public function getCallback(): mixed {
    return $this->callback;
  }

  /**
   * Get the arguments of the task
   *
   * @return array<mixed>
   */
  public function getArgs(): array {
    return $this->args;
  }

  /**
   * Get the start time of the task
   */
  public function getStartAt(): int {
    return $this->startAt;
  }

  /**
   * Run the task now
   *
   * @param bool $unschedule Whether to unschedule the task after running
   */
  public function runNow(bool $unschedule = false): void {
    call_user_func($this->getCallback(), ...$this->getArgs());

    if ($unschedule) {
      Scheduler::unschedule($this->getName());
    }
  }

  /**
   * Check if the task is currently scheduled in WordPress
   */
  public function isScheduled(): bool {
    return $this->getNextRun() !== false;
  }

  /**
   * Get the next scheduled run time
   *
   * @return int|false Unix timestamp of next execution or false if not scheduled
   */
  public function getNextRun(): int|false {
    return wp_next_scheduled($this->getName(), $this->getArgs());
  }
}
