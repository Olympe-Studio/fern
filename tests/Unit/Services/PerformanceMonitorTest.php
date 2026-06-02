<?php declare(strict_types=1);

use Fern\Core\Fern;
use Fern\Core\Services\Performance\PerformanceMonitor;

function setPerfEnabled(bool $value): void {
  (new ReflectionProperty(PerformanceMonitor::class, 'enabled'))->setValue(null, $value);
}

function setPerfFernDev(bool $value): void {
  (new ReflectionProperty(Fern::class, 'isDev'))->setValue(null, $value);
}

beforeEach(function (): void {
  PerformanceMonitor::clearStats();
  setPerfEnabled(false);
});

afterEach(function (): void {
  PerformanceMonitor::clearStats();
  setPerfEnabled(false);
});

describe('init / isEnabled', function (): void {
  it('enables monitoring in development mode', function (): void {
    setPerfFernDev(true);

    PerformanceMonitor::init();

    expect(PerformanceMonitor::isEnabled())->toBeTrue();
  });

  it('stays disabled outside of development mode', function (): void {
    setPerfFernDev(false);

    PerformanceMonitor::init();

    expect(PerformanceMonitor::isEnabled())->toBeFalse();
  });
});

describe('start / stop', function (): void {
  it('does nothing while monitoring is disabled', function (): void {
    setPerfEnabled(false);

    PerformanceMonitor::start('work', 'cache');
    PerformanceMonitor::stop('work', 'cache');

    expect(PerformanceMonitor::getReport()['active_timers'])->toBe(0)
      ->and(PerformanceMonitor::getReport()['completed_timers'])->toBe(0);
  });

  it('tracks an active timer between start and stop', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::start('render', 'controller');

    expect(PerformanceMonitor::getReport()['active_timers'])->toBe(1);

    PerformanceMonitor::stop('render', 'controller');

    expect(PerformanceMonitor::getReport()['active_timers'])->toBe(0)
      ->and(PerformanceMonitor::getReport()['completed_timers'])->toBe(1);
  });

  it('records stats under the category-prefixed timer name', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::start('query', 'db');
    PerformanceMonitor::stop('query', 'db');

    $stats = PerformanceMonitor::getStats();

    expect($stats)->toHaveKey('fern:db:query')
      ->and($stats['fern:db:query'])->toHaveKeys(['total_time', 'memory_delta', 'calls', 'avg_time'])
      ->and($stats['fern:db:query']['calls'])->toBe(1)
      ->and($stats['fern:db:query']['total_time'])->toBeGreaterThanOrEqual(0.0);
  });

  it('uses the general category by default', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::start('task');
    PerformanceMonitor::stop('task');

    expect(PerformanceMonitor::getStats())->toHaveKey('fern:general:task');
  });

  it('accumulates calls and total time across multiple start/stop cycles', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::start('loop', 'work');
    PerformanceMonitor::stop('loop', 'work');
    PerformanceMonitor::start('loop', 'work');
    PerformanceMonitor::stop('loop', 'work');

    $stats = PerformanceMonitor::getStats();

    expect($stats['fern:work:loop']['calls'])->toBe(2)
      ->and($stats['fern:work:loop']['avg_time'])->toBe($stats['fern:work:loop']['total_time'] / 2);
  });

  it('ignores a stop for a timer that was never started', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::stop('ghost', 'work');

    expect(PerformanceMonitor::getStats())->toBe([])
      ->and(PerformanceMonitor::getReport()['completed_timers'])->toBe(0);
  });
});

describe('time', function (): void {
  it('runs the callback and returns its value while timing it', function (): void {
    setPerfEnabled(true);

    $result = PerformanceMonitor::time(static fn (): string => 'done', 'op', 'svc');

    expect($result)->toBe('done')
      ->and(PerformanceMonitor::getStats())->toHaveKey('fern:svc:op');
  });

  it('still stops the timer when the callback throws', function (): void {
    setPerfEnabled(true);

    expect(static function (): void {
      PerformanceMonitor::time(static function (): void {
        throw new RuntimeException('boom');
      }, 'op', 'svc');
    })->toThrow(RuntimeException::class, 'boom');

    expect(PerformanceMonitor::getReport()['active_timers'])->toBe(0)
      ->and(PerformanceMonitor::getStats())->toHaveKey('fern:svc:op');
  });
});

describe('lap / debug (no-ops)', function (): void {
  it('does not throw when called while enabled or disabled', function (bool $enabled): void {
    setPerfEnabled($enabled);

    PerformanceMonitor::lap('x', 'cat');
    PerformanceMonitor::debug('message', 'cat');

    expect(PerformanceMonitor::getStats())->toBe([]);
  })->with([
    'enabled' => [true],
    'disabled' => [false],
  ]);
});

describe('getStats / getReport / clearStats', function (): void {
  it('reports zero averages when there are no completed timers', function (): void {
    expect(PerformanceMonitor::getStats())->toBe([]);
  });

  it('builds a full report snapshot', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::start('a', 'one');
    PerformanceMonitor::start('b', 'two');
    PerformanceMonitor::stop('a', 'one');

    $report = PerformanceMonitor::getReport();

    expect($report)->toHaveKeys(['enabled', 'active_timers', 'completed_timers', 'stats'])
      ->and($report['enabled'])->toBeTrue()
      ->and($report['active_timers'])->toBe(1)
      ->and($report['completed_timers'])->toBe(1);
  });

  it('clears both active timers and accumulated stats', function (): void {
    setPerfEnabled(true);

    PerformanceMonitor::start('a', 'one');
    PerformanceMonitor::stop('a', 'one');
    PerformanceMonitor::start('b', 'two');

    PerformanceMonitor::clearStats();

    expect(PerformanceMonitor::getStats())->toBe([])
      ->and(PerformanceMonitor::getReport()['active_timers'])->toBe(0)
      ->and(PerformanceMonitor::getReport()['completed_timers'])->toBe(0);
  });
});
