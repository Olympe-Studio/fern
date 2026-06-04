<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Errors\SchedulerParsingError;
use Fern\Core\Services\Scheduler\Scheduler;
use Fern\Core\Services\Scheduler\Task;

function clearSchedulerTasks(): void {
  $property = new ReflectionProperty(Scheduler::class, 'tasks');
  $property->setValue(null, []);
}

beforeEach(function (): void {
  clearSchedulerTasks();
});

describe('createSchedule', function (): void {
  it('does nothing for the built-in recurrences', function (string $builtin): void {
    Filters\expectAdded('cron_schedules')->never();

    Scheduler::createSchedule($builtin);

    expect(true)->toBeTrue();
  })->with(['hourly', 'twicedaily', 'daily']);

  it('registers a cron_schedules filter for a valid custom interval', function (): void {
    Filters\expectAdded('cron_schedules')->once()->with(Mockery::type('Closure'), 25, 1);

    Scheduler::createSchedule('every_5_minutes');
  });

  it('builds the schedule entry the filter injects', function (): void {
    $captured = null;

    Filters\expectAdded('cron_schedules')->once()->whenHappen(function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    Scheduler::createSchedule('every_2_hours');

    expect($captured)->toBeInstanceOf(Closure::class);

    $schedules = $captured([]);

    expect($schedules)->toHaveKey('every_2_hours')
      ->and($schedules['every_2_hours'])->toBe([
        'interval' => 7200,
        'display' => 'Every 2 hours',
      ]);
  });

  it('coerces a non-array incoming schedules value to an array', function (): void {
    $captured = null;

    Filters\expectAdded('cron_schedules')->once()->whenHappen(function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    Scheduler::createSchedule('every_1_days');

    $schedules = $captured('not-an-array');

    expect($schedules)->toBe([
      'every_1_days' => ['interval' => 86400, 'display' => 'Every 1 days'],
    ]);
  });

  it('converts every supported unit to seconds', function (string $interval, int $seconds, string $display): void {
    $captured = null;

    Filters\expectAdded('cron_schedules')->once()->whenHappen(function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    Scheduler::createSchedule($interval);

    $schedules = $captured([]);

    expect($schedules[$interval])->toBe(['interval' => $seconds, 'display' => $display]);
  })->with([
    'seconds' => ['every_30_seconds', 30, 'Every 30 seconds'],
    'minutes' => ['every_15_minutes', 900, 'Every 15 minutes'],
    'hours' => ['every_6_hours', 21600, 'Every 6 hours'],
    'days' => ['every_3_days', 259200, 'Every 3 days'],
  ]);

  it('throws on a malformed interval string', function (string $bad): void {
    Filters\expectAdded('cron_schedules')->never();

    expect(fn () => Scheduler::createSchedule($bad))
      ->toThrow(SchedulerParsingError::class);
  })->with([
    'no prefix' => ['5_minutes'],
    'unknown unit' => ['every_5_weeks'],
    'missing number' => ['every__minutes'],
    'trailing text' => ['every_5_minutes_extra'],
    'empty' => [''],
  ]);

  it('throws when the interval overflows PHP_INT_MAX once converted to seconds', function (): void {
    $huge = (string) PHP_INT_MAX;

    expect(fn () => Scheduler::createSchedule("every_{$huge}_days"))
      ->toThrow(SchedulerParsingError::class, 'too large');
  });
});

describe('schedule', function (): void {
  it('schedules a new cron event and registers the handler', function (): void {
    $callback = fn (): int => 1;

    Filters\expectAdded('cron_schedules')->once();
    Functions\expect('wp_next_scheduled')->once()->with('my_task')->andReturn(false);
    Functions\expect('wp_schedule_event')->once()->with(1000, 'every_5_minutes', 'my_task', ['a']);
    Actions\expectAdded('my_task')->once()->with($callback, 10, 1);

    $task = Scheduler::schedule('my_task', 'every_5_minutes', $callback, ['a'], 1000);

    expect($task)->toBeInstanceOf(Task::class)
      ->and($task->getName())->toBe('my_task')
      ->and($task->getInterval())->toBe('every_5_minutes')
      ->and($task->getCallback())->toBe($callback)
      ->and($task->getArgs())->toBe(['a'])
      ->and($task->getStartAt())->toBe(1000);
  });

  it('does not re-schedule when the event already exists', function (): void {
    Filters\expectAdded('cron_schedules')->once();
    Functions\expect('wp_next_scheduled')->once()->with('existing')->andReturn(123456);
    Functions\expect('wp_schedule_event')->never();
    Actions\expectAdded('existing')->once();

    $task = Scheduler::schedule('existing', 'every_5_minutes', fn (): int => 1);

    expect($task)->toBeInstanceOf(Task::class);
  });

  it('defaults startAt to the current time when -1 is passed', function (): void {
    Filters\expectAdded('cron_schedules')->once();
    Functions\expect('wp_next_scheduled')->andReturn(false);
    Functions\expect('wp_schedule_event')->once()->with(
      Mockery::on(fn ($ts): bool => is_int($ts) && abs($ts - time()) <= 2),
      'every_5_minutes',
      'now_task',
      [],
    );
    Actions\expectAdded('now_task')->once();

    $task = Scheduler::schedule('now_task', 'every_5_minutes', fn (): int => 1);

    expect($task->getStartAt())->toBeGreaterThan(0);
  });

  it('accepts the built-in recurrences without registering a custom schedule', function (): void {
    Filters\expectAdded('cron_schedules')->never();
    Functions\expect('wp_next_scheduled')->andReturn(false);
    Functions\expect('wp_schedule_event')->once();
    Actions\expectAdded('hourly_task')->once();

    $task = Scheduler::schedule('hourly_task', 'hourly', fn (): int => 1, [], 500);

    expect($task->getInterval())->toBe('hourly');
  });

  it('throws on an empty task name', function (): void {
    Filters\expectAdded('cron_schedules')->once();

    expect(fn () => Scheduler::schedule('', 'every_5_minutes', fn (): int => 1))
      ->toThrow(InvalidArgumentException::class, 'Task name cannot be empty');
  });

  it('propagates the parsing error for an invalid interval', function (): void {
    expect(fn () => Scheduler::schedule('t', 'bogus', fn (): int => 1))
      ->toThrow(SchedulerParsingError::class);
  });
});

describe('task registry', function (): void {
  it('starts empty', function (): void {
    expect(Scheduler::getTasks())->toBe([])
      ->and(Scheduler::getTask('anything'))->toBeNull();
  });

  it('records and retrieves a scheduled task', function (): void {
    Filters\expectAdded('cron_schedules')->once();
    Functions\expect('wp_next_scheduled')->andReturn(false);
    Functions\expect('wp_schedule_event')->once();
    Actions\expectAdded('reg_task')->once();

    $task = Scheduler::schedule('reg_task', 'every_5_minutes', fn (): int => 1);

    expect(Scheduler::getTasks())->toBe(['reg_task' => $task])
      ->and(Scheduler::getTask('reg_task'))->toBe($task);
  });
});

describe('getSchedules', function (): void {
  it('delegates to wp_get_schedules', function (): void {
    $schedules = ['hourly' => ['interval' => 3600, 'display' => 'Hourly']];
    Functions\expect('wp_get_schedules')->once()->andReturn($schedules);

    expect(Scheduler::getSchedules())->toBe($schedules);
  });
});

describe('unschedule', function (): void {
  it('throws on an empty task name', function (): void {
    expect(fn () => Scheduler::unschedule(''))
      ->toThrow(InvalidArgumentException::class, 'Task name must be a non-empty string');
  });

  it('returns false when no next run exists for the default timestamp', function (): void {
    Functions\expect('wp_next_scheduled')->once()->with('ghost')->andReturn(false);
    Functions\expect('wp_unschedule_event')->never();

    expect(Scheduler::unschedule('ghost'))->toBeFalse();
  });

  it('unschedules with the resolved next-run timestamp and clears state', function (): void {
    $property = new ReflectionProperty(Scheduler::class, 'tasks');
    $property->setValue(null, ['t' => new Task('t', 'every_5_minutes', fn (): int => 1)]);

    Functions\expect('wp_next_scheduled')->once()->with('t')->andReturn(999);
    Functions\expect('wp_unschedule_event')->once()->with(999, 't')->andReturn(true);
    Functions\expect('remove_all_actions')->once()->with('t');

    expect(Scheduler::unschedule('t'))->toBeTrue()
      ->and(Scheduler::getTask('t'))->toBeNull();
  });

  it('uses an explicit timestamp and skips the wp_next_scheduled lookup', function (): void {
    $property = new ReflectionProperty(Scheduler::class, 'tasks');
    $property->setValue(null, ['t' => new Task('t', 'every_5_minutes', fn (): int => 1)]);

    Functions\expect('wp_next_scheduled')->never();
    Functions\expect('wp_unschedule_event')->once()->with(777, 't')->andReturn(true);
    Functions\expect('remove_all_actions')->once()->with('t');

    expect(Scheduler::unschedule('t', 777))->toBeTrue();
  });

  it('keeps the task when wp_unschedule_event fails', function (): void {
    $task = new Task('keep', 'every_5_minutes', fn (): int => 1);
    $property = new ReflectionProperty(Scheduler::class, 'tasks');
    $property->setValue(null, ['keep' => $task]);

    Functions\expect('wp_next_scheduled')->once()->with('keep')->andReturn(555);
    Functions\expect('wp_unschedule_event')->once()->with(555, 'keep')->andReturn(false);
    Functions\expect('remove_all_actions')->never();

    expect(Scheduler::unschedule('keep'))->toBeFalse()
      ->and(Scheduler::getTask('keep'))->toBe($task);
  });
});
