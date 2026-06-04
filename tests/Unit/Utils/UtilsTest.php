<?php declare(strict_types=1);

use Fern\Core\Utils\Utils;
use Tests\Fixtures\CallableFixture;

mutates(Utils::class);

describe('reflectCallable', function (): void {
  it('reflects a closure as a function', function (): void {
    expect(Utils::reflectCallable(fn (): int => 1))->toBeInstanceOf(ReflectionFunction::class);
  });

  it('reflects a function name string as a function', function (): void {
    expect(Utils::reflectCallable('strlen'))->toBeInstanceOf(ReflectionFunction::class);
  });

  it('reflects a "Class::method" string as a method', function (): void {
    $reflection = Utils::reflectCallable(CallableFixture::class . '::staticMethod');

    expect($reflection)->toBeInstanceOf(ReflectionMethod::class)
      ->and($reflection->getName())->toBe('staticMethod');
  });

  it('reflects an [object, method] array as a method', function (): void {
    expect(Utils::reflectCallable([new CallableFixture(), 'instanceMethod']))
      ->toBeInstanceOf(ReflectionMethod::class);
  });

  it('reflects a [class, staticMethod] array as a method', function (): void {
    expect(Utils::reflectCallable([CallableFixture::class, 'staticMethod']))
      ->toBeInstanceOf(ReflectionMethod::class);
  });

  it('reflects an invokable object via __invoke', function (): void {
    $reflection = Utils::reflectCallable(new CallableFixture());

    expect($reflection)->toBeInstanceOf(ReflectionMethod::class)
      ->and($reflection->getName())->toBe('__invoke');
  });
});

describe('addTrailingSlash', function (): void {
  it('appends a single trailing slash', function (string $in, string $out): void {
    expect(Utils::addTrailingSlash($in))->toBe($out);
  })->with([
    'no slash' => ['path', 'path/'],
    'already slashed' => ['path/', 'path/'],
    'backslash' => ['path\\', 'path/'],
    'multiple slashes' => ['path///', 'path/'],
    'empty' => ['', '/'],
  ]);
});

describe('getCallableExpectedArgumentsNumber', function (): void {
  it('counts closure parameters', function (): void {
    expect(Utils::getCallableExpectedArgumentsNumber(fn (int $a, int $b): int => $a + $b))->toBe(2);
  });

  it('counts native function parameters', function (): void {
    expect(Utils::getCallableExpectedArgumentsNumber('strlen'))->toBe(1);
  });

  it('counts method parameters', function (): void {
    expect(Utils::getCallableExpectedArgumentsNumber([new CallableFixture(), 'instanceMethod']))->toBe(2);
  });

  it('counts invokable parameters', function (): void {
    expect(Utils::getCallableExpectedArgumentsNumber(new CallableFixture()))->toBe(1);
  });
});
