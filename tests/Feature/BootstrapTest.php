<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Fern\Core\Config;
use Fern\Core\Context;
use Fern\Core\Errors\FernConfigurationExceptions;

describe('Config::boot', function (): void {
  it('applies the config filter and stores the result', function (): void {
    Filters\expectApplied('fern:core:config')->once()->andReturnFirstArg();

    Config::boot(['root' => '/srv/app', 'name' => 'fern']);

    expect(Config::get('root'))->toBe('/srv/app')
      ->and(Config::get('name'))->toBe('fern');
  });

  it('throws when the root path is missing', function (): void {
    Config::boot([]);
  })->throws(FernConfigurationExceptions::class, 'Root path is required.');

  it('throws when the config filter returns a non-array', function (): void {
    Filters\expectApplied('fern:core:config')->once()->andReturn('not-an-array');

    Config::boot(['root' => '/srv/app']);
  })->throws(FernConfigurationExceptions::class);
});

describe('Context::boot', function (): void {
  it('seeds the context from the filter when it returns an array', function (): void {
    Filters\expectApplied('fern:core:ctx')->once()->andReturn(['locale' => 'fr']);

    Context::boot();

    expect(Context::get())->toBe(['locale' => 'fr']);
  });

  it('falls back to an empty context when the filter returns a non-array', function (): void {
    Filters\expectApplied('fern:core:ctx')->once()->andReturn('nope');

    Context::boot();

    expect(Context::get())->toBe([]);
  });
});
