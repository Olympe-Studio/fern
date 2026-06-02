<?php declare(strict_types=1);

use Fern\Core\Factory\Singleton;

final class SingletonAlpha extends Singleton {
  /** @var array<string, mixed> */
  public array $opts;

  /**
   * @param array<string, mixed> $opts
   */
  protected function __construct(array $opts = []) {
    parent::__construct();
    $this->opts = $opts;
  }
}

final class SingletonBeta extends Singleton {}

it('returns the same instance on repeated calls', function (): void {
  expect(SingletonAlpha::getInstance())->toBe(SingletonAlpha::getInstance());
});

it('keys instances by concrete class', function (): void {
  expect(SingletonAlpha::getInstance())->not->toBe(SingletonBeta::getInstance());
});

it('passes constructor arguments on first construction', function (): void {
  expect(SingletonAlpha::getInstance(['env' => 'test'])->opts)->toBe(['env' => 'test']);
});

it('ignores constructor arguments once constructed', function (): void {
  SingletonAlpha::getInstance(['first' => true]);

  expect(SingletonAlpha::getInstance(['second' => true])->opts)->toBe(['first' => true]);
});

it('creates a fresh instance after flushInstances', function (): void {
  $first = SingletonAlpha::getInstance();
  Singleton::flushInstances();

  expect(SingletonAlpha::getInstance())->not->toBe($first);
});

it('forbids cloning', function (): void {
  $instance = SingletonBeta::getInstance();

  expect(fn () => clone $instance)->toThrow(Error::class);
});

it('survives a serialization round-trip via __wakeup', function (): void {
  $restored = unserialize(serialize(SingletonBeta::getInstance()));

  expect($restored)->toBeInstanceOf(SingletonBeta::class);
});
