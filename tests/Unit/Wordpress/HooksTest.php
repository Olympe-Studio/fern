<?php declare(strict_types=1);

use Fern\Core\Wordpress\Events;

/**
 * Hooks::_add is abstract base behaviour, exercised here through Events with a
 * spy registrar so the generic logic is covered without touching WordPress.
 */
describe('Hooks::_add', function (): void {
  it('normalises a string event name into a single registration', function (): void {
    $calls = [];
    $registrar = function (...$args) use (&$calls): void {
      $calls[] = $args;
    };

    Events::_add($registrar, 'single_event', fn (): int => 1, 10, 1);

    expect($calls)->toHaveCount(1)
      ->and($calls[0][0])->toBe('single_event');
  });

  it('registers once per event for an array of events', function (): void {
    $events = [];
    $registrar = function (string $event) use (&$events): void {
      $events[] = $event;
    };

    Events::_add($registrar, ['a', 'b', 'c'], fn (): int => 1, 10, 1);

    expect($events)->toBe(['a', 'b', 'c']);
  });

  it('reflects the accepted argument count from the callback when -1', function (): void {
    $captured = null;
    $registrar = function (string $event, $cb, int $priority, int $acceptedArgs) use (&$captured): void {
      $captured = $acceptedArgs;
    };

    Events::_add($registrar, 'evt', fn (int $a, int $b, int $c): int => $a + $b + $c);

    expect($captured)->toBe(3);
  });

  it('respects an explicit accepted argument count', function (): void {
    $captured = null;
    $registrar = function (string $event, $cb, int $priority, int $acceptedArgs) use (&$captured): void {
      $captured = $acceptedArgs;
    };

    Events::_add($registrar, 'evt', fn (int $a): int => $a, 10, 7);

    expect($captured)->toBe(7);
  });

  it('passes the priority through to the registrar', function (): void {
    $captured = null;
    $registrar = function (string $event, $cb, int $priority) use (&$captured): void {
      $captured = $priority;
    };

    Events::_add($registrar, 'evt', fn (): int => 1, 42, 0);

    expect($captured)->toBe(42);
  });
});
