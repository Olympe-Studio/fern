<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Fern\Core\Wordpress\Events;

describe('addHandlers / on', function (): void {
  it('registers an action with the reflected accepted args', function (): void {
    $callback = fn (int $a, int $b): int => $a + $b;

    Actions\expectAdded('init')->once()->with($callback, 10, 2);

    Events::addHandlers('init', $callback);
  });

  it('honours an explicit priority and accepted args', function (): void {
    $callback = fn (): int => 1;

    Actions\expectAdded('init')->once()->with($callback, 20, 5);

    Events::on('init', $callback, 20, 5);
  });

  it('registers each event in an array', function (): void {
    $callback = fn (): int => 1;

    Actions\expectAdded('first')->once();
    Actions\expectAdded('second')->once();

    Events::on(['first', 'second'], $callback);
  });
});

describe('trigger', function (): void {
  it('dispatches an action with its arguments', function (): void {
    Actions\expectDone('my_event')->once()->with('one', 'two');

    Events::trigger('my_event', 'one', 'two');
  });
});

describe('renderToString', function (): void {
  it('captures echoed output produced while the action runs', function (): void {
    Actions\expectDone('render_event')->once()->whenHappen(function (): void {
      echo 'rendered output';
    });

    expect(Events::renderToString('render_event'))->toBe('rendered output');
  });

  it('returns an empty string when nothing is echoed', function (): void {
    Actions\expectDone('silent_event')->once();

    expect(Events::renderToString('silent_event'))->toBe('');
  });
});

describe('removeHandlers', function (): void {
  it('removes all actions for a single event', function (): void {
    Functions\expect('remove_all_actions')->once()->with('hook');

    Events::removeHandlers('hook');
  });

  it('removes all actions for each event in an array', function (): void {
    Functions\expect('remove_all_actions')->once()->with('a');
    Functions\expect('remove_all_actions')->once()->with('b');

    Events::removeHandlers(['a', 'b']);
  });
});
