<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\QueryMonitor\QueryMonitor;

class FakeQmRequest extends Request {
  public function __construct(private bool $action = false) {}

  public function isAction(): bool {
    return $this->action;
  }
}

function injectCurrentRequest(bool $isAction): void {
  $instances = new ReflectionProperty(Singleton::class, '_instances');
  $current = $instances->getValue();
  $current[Request::class] = new FakeQmRequest($isAction);
  $instances->setValue(null, $current);
}

describe('hook registration', function (): void {
  it('wires the init guard at priority 5 and the reply hook at priority 1', function (): void {
    Actions\expectAdded('init')->once()->with(Mockery::type('Closure'), 5, 0);
    Actions\expectAdded('fern:core:reply:has_been_sent')->once()->with(Mockery::type('Closure'), 1, 0);

    QueryMonitor::disable();
  });
});

describe('init guard behaviour', function (): void {
  it('does nothing for a non-action request', function (): void {
    $captured = null;
    Actions\expectAdded('init')->once()->with(Mockery::capture($captured), 5, 0);
    Actions\expectAdded('fern:core:reply:has_been_sent')->once();

    QueryMonitor::disable();
    injectCurrentRequest(false);

    Functions\expect('add_filter')->never();
    Functions\expect('remove_all_actions')->never();

    $captured();

    expect($captured)->toBeInstanceOf(Closure::class);
  });

  it('disables Query Monitor output and clears hooks for an action request', function (): void {
    $captured = null;
    Actions\expectAdded('init')->once()->with(Mockery::capture($captured), 5, 0);
    Actions\expectAdded('fern:core:reply:has_been_sent')->once();

    QueryMonitor::disable();
    injectCurrentRequest(true);

    Functions\expect('remove_all_actions')->once()->with('shutdown');
    Functions\expect('remove_all_actions')->once()->with('wp_footer');

    Filters\expectAdded('qm/process')->once();
    Filters\expectAdded('qm/dispatchers/html')->once();
    Filters\expectAdded('qm/dispatchers/ajax')->once();

    $startLevel = ob_get_level();
    ob_start();

    $captured();

    $drainedLevel = ob_get_level();

    while (ob_get_level() < $startLevel) {
      ob_start();
    }

    expect($drainedLevel)->toBe(0);
  });
});

describe('reply-sent behaviour', function (): void {
  it('removes shutdown actions and finishes the request when fastcgi is available', function (): void {
    $captured = null;
    Actions\expectAdded('init')->once();
    Actions\expectAdded('fern:core:reply:has_been_sent')->once()->with(Mockery::capture($captured), 1, 0);

    QueryMonitor::disable();

    Functions\expect('remove_all_actions')->once()->with('shutdown');
    Functions\expect('fastcgi_finish_request')->once();

    $captured();

    expect($captured)->toBeInstanceOf(Closure::class);
  });
});
