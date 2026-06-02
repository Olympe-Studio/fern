<?php declare(strict_types=1);

use Fern\Core\Config;
use Fern\Core\Errors\FernConfigurationExceptions;

function configWith(array $values): void {
  Config::getInstance()->setConfig($values);
}

describe('get', function (): void {
  it('returns the default when the key is missing', function (): void {
    expect(Config::get('missing', 'fallback'))->toBe('fallback')
      ->and(Config::get('missing'))->toBeNull();
  });

  it('returns a top-level value', function (): void {
    configWith(['name' => 'fern']);

    expect(Config::get('name'))->toBe('fern');
  });

  it('resolves dot notation', function (): void {
    configWith(['seo' => ['flags' => ['sitemap' => true]]]);

    expect(Config::get('seo.flags.sitemap'))->toBeTrue();
  });

  it('returns the default when a nested key is missing', function (): void {
    configWith(['seo' => ['flags' => []]]);

    expect(Config::get('seo.flags.sitemap', 'nope'))->toBe('nope');
  });

  it('serves repeated lookups from the cache', function (): void {
    $config = Config::getInstance();
    $config->setConfig(['a' => 1]);

    expect(Config::get('a'))->toBe(1);

    $property = new ReflectionProperty($config, 'config');
    $property->setValue($config, ['a' => 999]);

    expect(Config::get('a'))->toBe(1);
  });
});

describe('has', function (): void {
  it('is true for present keys, including nested ones', function (): void {
    configWith(['a' => 1, 'seo' => ['title' => 'x']]);

    expect(Config::has('a'))->toBeTrue()
      ->and(Config::has('seo.title'))->toBeTrue();
  });

  it('is false for absent keys', function (): void {
    configWith(['a' => 1]);

    expect(Config::has('b'))->toBeFalse()
      ->and(Config::has('a.b.c'))->toBeFalse();
  });

  it('serves a repeated lookup from the cache', function (): void {
    configWith(['a' => 1]);

    expect(Config::has('a'))->toBeTrue()
      ->and(Config::has('a'))->toBeTrue();
  });
});

describe('accessors', function (): void {
  it('returns the whole config via all and toArray', function (): void {
    configWith(['a' => 1, 'b' => 2]);

    expect(Config::all())->toBe(['a' => 1, 'b' => 2])
      ->and(Config::toArray())->toBe(['a' => 1, 'b' => 2]);
  });

  it('encodes the config to JSON', function (): void {
    configWith(['a' => 1]);

    expect(Config::toJson())->toBe('{"a":1}');
  });

  it('replaces the config and clears the cache on setConfig', function (): void {
    configWith(['a' => 1]);
    expect(Config::get('a'))->toBe(1);

    configWith(['b' => 2]);
    expect(Config::get('a'))->toBeNull()
      ->and(Config::get('b'))->toBe(2);
  });
});

describe('boot', function (): void {
  it('requires a root path', function (): void {
    Config::boot([]);
  })->throws(FernConfigurationExceptions::class, 'Root path is required.');

  it('rejects a config without root even when other keys are present', function (): void {
    Config::boot(['name' => 'fern']);
  })->throws(FernConfigurationExceptions::class);
});
