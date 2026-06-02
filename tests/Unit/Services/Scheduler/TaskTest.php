<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\Scheduler\Scheduler;
use Fern\Core\Services\Scheduler\Task;

function resetSchedulerRegistry(): void {
  $property = new ReflectionProperty(Scheduler::class, 'tasks');
  $property->setValue(null, []);
}

beforeEach(function (): void {
  resetSchedulerRegistry();
});

describe('construction and getters', function (): void {
  it('exposes every constructor argument through its getter', function (): void {
    $callback = fn (int $a, int $b): int => $a + $b;
    $task = new Task('report', 'every_5_minutes', $callback, ['x', 'y'], 4242);

    expect($task->getName())->toBe('report')
      ->and($task->getInterval())->toBe('every_5_minutes')
      ->and($task->getCallback())->toBe($callback)
      ->and($task->getArgs())->toBe(['x', 'y'])
      ->and($task->getStartAt())->toBe(4242);
  });

  it('applies the default args and startAt', function (): void {
    $task = new Task('bare', 'hourly', 'strlen');

    expect($task->getArgs())->toBe([])
      ->and($task->getStartAt())->toBe(-1);
  });

  it('accepts a string callable as the callback', function (): void {
    $task = new Task('strtask', 'daily', 'trim');

    expect($task->getCallback())->toBe('trim');
  });
});

describe('getByName', function (): void {
  it('returns the registered task from the scheduler', function (): void {
    $task = new Task('known', 'every_5_minutes', fn (): int => 1);
    $property = new ReflectionProperty(Scheduler::class, 'tasks');
    $property->setValue(null, ['known' => $task]);

    expect(Task::getByName('known'))->toBe($task);
  });

  it('returns null for an unknown task', function (): void {
    expect(Task::getByName('nope'))->toBeNull();
  });
});

describe('runNow', function (): void {
  it('invokes the callback with the stored arguments', function (): void {
    $received = null;
    $callback = function (string $a, string $b) use (&$received): void {
      $received = "{$a}:{$b}";
    };
    $task = new Task('run', 'hourly', $callback, ['one', 'two']);

    $task->runNow();

    expect($received)->toBe('one:two');
  });

  it('does nothing for a non-callable callback', function (): void {
    $task = new Task('bad', 'hourly', 'this_function_does_not_exist_at_all');

    $task->runNow();

    expect(true)->toBeTrue();
  });

  it('unschedules after running when requested', function (): void {
    $ran = false;
    $callback = function () use (&$ran): void {
      $ran = true;
    };
    $task = new Task('cleanup', 'hourly', $callback);

    Functions\expect('wp_next_scheduled')->once()->with('cleanup')->andReturn(321);
    Functions\expect('wp_unschedule_event')->once()->with(321, 'cleanup')->andReturn(true);
    Functions\expect('remove_all_actions')->once()->with('cleanup');

    $task->runNow(true);

    expect($ran)->toBeTrue();
  });

  it('does not unschedule when the flag is omitted', function (): void {
    $task = new Task('keep', 'hourly', fn (): int => 1);

    Functions\expect('wp_next_scheduled')->never();
    Functions\expect('wp_unschedule_event')->never();

    $task->runNow();

    expect(true)->toBeTrue();
  });
});

describe('getNextRun / isScheduled', function (): void {
  it('returns the next run timestamp from wp_next_scheduled with its args', function (): void {
    $task = new Task('next', 'hourly', fn (): int => 1, ['p']);

    Functions\expect('wp_next_scheduled')->once()->with('next', ['p'])->andReturn(123456);

    expect($task->getNextRun())->toBe(123456);
  });

  it('reports a scheduled task as scheduled', function (): void {
    $task = new Task('on', 'hourly', fn (): int => 1);

    Functions\expect('wp_next_scheduled')->once()->with('on', [])->andReturn(999);

    expect($task->isScheduled())->toBeTrue();
  });

  it('reports an unscheduled task as not scheduled', function (): void {
    $task = new Task('off', 'hourly', fn (): int => 1);

    Functions\expect('wp_next_scheduled')->once()->with('off', [])->andReturn(false);

    expect($task->isScheduled())->toBeFalse();
  });
});
