<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Utils\Cache;

beforeEach(function (): void {
  Functions\when('get_option')->justReturn([]);
});

describe('get / set in memory', function (): void {
  it('stores and reads a value in the in-memory cache', function (): void {
    Cache::set('greeting', 'hello');

    expect(Cache::get('greeting'))->toBe('hello');
  });

  it('returns null for a missing key', function (): void {
    expect(Cache::get('missing'))->toBeNull();
  });

  it('does not mark the instance dirty for a non-persistent set', function (): void {
    Cache::set('volatile', 42);

    expect(Cache::getInstance()->isDirty())->toBeFalse();
  });
});

describe('persistence flag', function (): void {
  it('marks the instance dirty when persist is true', function (): void {
    Cache::set('token', 'abc', true);

    expect(Cache::getInstance()->isDirty())->toBeTrue();
  });

  it('stores the value in the persistent cache with an expiry', function (): void {
    Cache::set('token', 'abc', true, 60);

    $persistent = Cache::getInstance()->getCaches()['persistent'];

    expect($persistent)->toHaveKey('token')
      ->and($persistent['token']['value'])->toBe('abc')
      ->and($persistent['token']['expires'])->toBeGreaterThan(time());
  });
});

describe('expiry handling', function (): void {
  it('returns the value from the persistent cache when it has not expired', function (): void {
    Functions\when('get_option')->justReturn([
      'fresh' => ['value' => 'kept', 'expires' => time() + 1000],
    ]);

    expect(Cache::get('fresh'))->toBe('kept');
  });

  it('returns null and evicts an expired persistent item', function (): void {
    Functions\when('get_option')->justReturn([
      'stale' => ['value' => 'gone', 'expires' => time() - 1000],
    ]);

    $cache = Cache::getInstance();

    expect($cache->get('stale'))->toBeNull()
      ->and($cache->getCaches()['persistent'])->not->toHaveKey('stale');
  });

  it('marks the instance dirty during init when expired items are pruned', function (): void {
    Functions\when('get_option')->justReturn([
      'stale' => ['value' => 'gone', 'expires' => time() - 1000],
      'fresh' => ['value' => 'kept', 'expires' => time() + 1000],
    ]);

    expect(Cache::getInstance()->isDirty())->toBeTrue();
  });
});

describe('useMemo', function (): void {
  it('computes the value once and caches it by dependency', function (): void {
    $calls = 0;
    $compute = function () use (&$calls): string {
      $calls++;

      return "result-{$calls}";
    };

    $memo = Cache::useMemo($compute, ['v1']);

    expect($memo())->toBe('result-1')
      ->and($memo())->toBe('result-1')
      ->and($calls)->toBe(1);
  });

  it('recomputes when the dependencies change', function (): void {
    $calls = 0;
    $compute = function () use (&$calls): int {
      $calls++;

      return $calls;
    };

    $first = Cache::useMemo($compute, ['v1']);
    $second = Cache::useMemo($compute, ['v2']);

    expect($first())->toBe(1)
      ->and($second())->toBe(2)
      ->and($calls)->toBe(2);
  });

  it('wraps a callback failure in a RuntimeException', function (): void {
    $compute = function (): void {
      throw new LogicException('boom');
    };

    $memo = Cache::useMemo($compute, ['fail']);

    expect($memo)->toThrow(RuntimeException::class, 'Failed to execute memoized callback: boom');
  });
});

describe('save', function (): void {
  it('writes the surviving items via update_option when an item expires after init', function (): void {
    $cache = Cache::getInstance();

    $property = new ReflectionProperty($cache, 'persistentCache');
    $property->setValue($cache, [
      'kept' => ['value' => 'a', 'expires' => time() + 1000],
      'stale' => ['value' => 'b', 'expires' => time() - 1000],
    ]);
    $cache->setDirtyState(true);

    Functions\expect('update_option')
      ->once()
      ->with(Cache::PERSISTENT_CACHE_OPTION, Mockery::on(static function (array $value): bool {
        return array_keys($value) === ['kept'];
      }), true)
      ->andReturn(true);

    Cache::save();

    expect($cache->isDirty())->toBeFalse();
  });

  it('does not call update_option when the cache is clean', function (): void {
    Functions\expect('update_option')->never();

    Cache::save();
  });

  it('writes a freshly persisted value via update_option on save', function (): void {
    Cache::set('persisted', 'value', true, 600);

    Functions\expect('update_option')
      ->once()
      ->with(Cache::PERSISTENT_CACHE_OPTION, Mockery::on(static function (array $value): bool {
        return array_key_exists('persisted', $value) && $value['persisted']['value'] === 'value';
      }), true)
      ->andReturn(true);

    Cache::save();

    expect(Cache::getInstance()->isDirty())->toBeFalse();
  });

  it('deletes the option instead of writing when the persistent cache is empty but dirty', function (): void {
    Cache::getInstance()->setDirtyState(true);

    Functions\expect('update_option')->never();
    Functions\expect('delete_option')
      ->once()
      ->with(Cache::PERSISTENT_CACHE_OPTION)
      ->andReturn(true);

    Cache::save();
  });

  it('deletes the option when every persistent item has expired', function (): void {
    Functions\when('get_option')->justReturn([
      'stale' => ['value' => 'b', 'expires' => time() - 1000],
    ]);

    $cache = Cache::getInstance();

    expect($cache->isDirty())->toBeTrue();

    Functions\expect('update_option')->never();
    Functions\expect('delete_option')
      ->once()
      ->with(Cache::PERSISTENT_CACHE_OPTION)
      ->andReturn(true);

    Cache::save();
  });
});

describe('flush', function (): void {
  it('clears both caches, marks dirty and deletes the option', function (): void {
    Cache::set('mem', 'x');
    Cache::set('persist', 'y', true);

    Functions\expect('delete_option')
      ->once()
      ->with(Cache::PERSISTENT_CACHE_OPTION)
      ->andReturn(true);

    Cache::flush();

    $cache = Cache::getInstance();

    expect($cache->getCaches()['inmemory'])->toBe([])
      ->and($cache->getCaches()['persistent'])->toBe([])
      ->and($cache->isDirty())->toBeTrue()
      ->and(Cache::get('mem'))->toBeNull();
  });
});
