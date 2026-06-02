<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Wordpress\Filters as FernFilters;

describe('add / on', function (): void {
  it('registers a filter with the reflected accepted args', function (): void {
    $callback = fn (string $value): string => $value;

    Filters\expectAdded('the_title')->once()->with($callback, 10, 1);

    FernFilters::add('the_title', $callback);
  });

  it('honours an explicit priority and accepted args via the on alias', function (): void {
    $callback = fn (string $value, int $id): string => $value;

    Filters\expectAdded('the_content')->once()->with($callback, 99, 2);

    FernFilters::on('the_content', $callback, 99, 2);
  });

  it('registers each filter in an array', function (): void {
    $callback = fn (string $value): string => $value;

    Filters\expectAdded('a')->once();
    Filters\expectAdded('b')->once();

    FernFilters::add(['a', 'b'], $callback);
  });
});

describe('apply', function (): void {
  it('applies a filter and returns the filtered value', function (): void {
    Filters\expectApplied('price')->once()->with(100)->andReturn(80);

    expect(FernFilters::apply('price', 100))->toBe(80);
  });

  it('forwards extra arguments to the filter', function (): void {
    Filters\expectApplied('greeting')->once()->with('hi', 'fr')->andReturn('salut');

    expect(FernFilters::apply('greeting', 'hi', 'fr'))->toBe('salut');
  });
});

describe('removeHandlers', function (): void {
  it('removes all filters for a single filter name', function (): void {
    Functions\expect('remove_all_filters')->once()->with('the_title');

    FernFilters::removeHandlers('the_title');
  });

  it('removes all filters for each name in an array', function (): void {
    Functions\expect('remove_all_filters')->once()->with('a');
    Functions\expect('remove_all_filters')->once()->with('b');

    FernFilters::removeHandlers(['a', 'b']);
  });
});
