<?php declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    Monkey\setUp();
});

afterEach(function (): void {
    Monkey\tearDown();
});

it('stubs a WordPress function return value', function (): void {
    Functions\when('get_option')->justReturn(1);

    expect(get_option('anything'))->toBe(1);
});

it('asserts a WordPress function is called with given arguments', function (): void {
    Functions\expect('update_option')
        ->once()
        ->with('key', 'value')
        ->andReturn(true);

    expect(update_option('key', 'value'))->toBeTrue();
});

it('aliases a filter to pass through its value', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $tag, mixed $value): mixed => $value);

    expect(apply_filters('some_filter', 'untouched'))->toBe('untouched');
});
