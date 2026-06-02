<?php declare(strict_types=1);

use Fern\Core\Context;

it('starts empty', function (): void {
  expect(Context::get())->toBe([]);
});

it('stores and retrieves the whole context', function (): void {
  Context::set(['user' => 1, 'locale' => 'fr']);

  expect(Context::get())->toBe(['user' => 1, 'locale' => 'fr']);
});

it('replaces the context on set', function (): void {
  Context::set(['a' => 1]);
  Context::set(['b' => 2]);

  expect(Context::get())->toBe(['b' => 2]);
});

it('exposes the context through the public property', function (): void {
  Context::set(['k' => 'v']);

  expect(Context::getInstance()->context)->toBe(['k' => 'v']);
});

it('is isolated between tests', function (): void {
  expect(Context::get())->toBe([]);
});
