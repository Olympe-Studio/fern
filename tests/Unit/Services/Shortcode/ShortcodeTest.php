<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Services\Shortcode\Shortcode;

/**
 * Registers a shortcode and returns the callback handed to add_shortcode.
 *
 * @param array<int,string> $allowed
 */
function captureShortcodeCallback(string $tag, array $allowed, string $template, ?callable $augment = null): callable {
  $captured = null;

  Functions\expect('add_shortcode')->once()->with($tag, Mockery::on(function ($cb) use (&$captured): bool {
    $captured = $cb;

    return is_callable($cb);
  }));

  Shortcode::getInstance()->register($tag, $allowed, $template, $augment);

  expect($captured)->toBeCallable();

  return $captured;
}

describe('register', function (): void {
  it('registers the tag with a callable through add_shortcode', function (): void {
    Functions\expect('add_shortcode')->once()->with('hero', Mockery::type('Closure'));

    Shortcode::getInstance()->register('hero', ['title'], 'blocks/hero');
  });
});

describe('attribute whitelisting', function (): void {
  it('keeps only the whitelisted attributes and passes them to the render hook', function (): void {
    $callback = captureShortcodeCallback('card', ['title', 'color'], 'blocks/card');

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo '<div>card</div>';
    });

    $html = $callback(['title' => 'Hi', 'color' => 'red', 'evil' => 'drop'], 'body');

    expect($html)->toBe('<div>card</div>')
      ->and($seen)->toBe([
        'attrs' => ['title' => 'Hi', 'color' => 'red'],
        'content' => 'body',
      ]);
  });

  it('passes empty attrs when none are whitelisted', function (): void {
    $callback = captureShortcodeCallback('plain', [], 'blocks/plain');

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo 'x';
    });

    $callback(['anything' => 'value']);

    expect($seen)->toBe(['attrs' => [], 'content' => '']);
  });
});

describe('augment callback', function (): void {
  it('merges extra attributes returned by the augment callback', function (): void {
    $augment = fn (array $attrs, string $content): array => ['extra' => strtoupper($content)];
    $callback = captureShortcodeCallback('aug', ['title'], 'blocks/aug', $augment);

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo 'ok';
    });

    $callback(['title' => 'Kept'], 'note');

    expect($seen['attrs'])->toBe(['title' => 'Kept', 'extra' => 'NOTE']);
  });

  it('lets the augment callback override a whitelisted attribute', function (): void {
    $augment = fn (array $attrs): array => ['title' => 'overridden'];
    $callback = captureShortcodeCallback('over', ['title'], 'blocks/over', $augment);

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo 'ok';
    });

    $callback(['title' => 'original']);

    expect($seen['attrs'])->toBe(['title' => 'overridden']);
  });
});

describe('filter hooks', function (): void {
  it('applies the attrs, data and html filters around the render', function (): void {
    $callback = captureShortcodeCallback('filtered', ['name'], 'blocks/filtered');

    Filters\expectApplied('fern:core:shortcode:attrs')
      ->once()
      ->with(['name' => 'Joe'], 'filtered', 'inner')
      ->andReturn(['name' => 'Jane']);

    Filters\expectApplied('fern:core:shortcode:data')
      ->once()
      ->andReturnUsing(fn (array $data): array => $data);

    Filters\expectApplied('fern:core:shortcode:html')
      ->once()
      ->andReturn('<b>wrapped</b>');

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo 'raw';
    });

    $html = $callback(['name' => 'Joe'], 'inner');

    expect($seen['attrs'])->toBe(['name' => 'Jane'])
      ->and($html)->toBe('<b>wrapped</b>');
  });

  it('falls back to the original attrs when the attrs filter returns a non-array', function (): void {
    $callback = captureShortcodeCallback('nonarray', ['name'], 'blocks/nonarray');

    Filters\expectApplied('fern:core:shortcode:attrs')->once()->andReturn('not-an-array');

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo 'ok';
    });

    $callback(['name' => 'Joe']);

    expect($seen['attrs'])->toBe([]);
  });

  it('falls back to the original data when the data filter returns a non-array', function (): void {
    $callback = captureShortcodeCallback('datafallback', ['name'], 'blocks/datafallback');

    Filters\expectApplied('fern:core:shortcode:data')->once()->andReturn(null);

    $seen = null;
    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (string $tag, array $data) use (&$seen): void {
      $seen = $data;
      echo 'ok';
    });

    $callback(['name' => 'Joe'], 'c');

    expect($seen)->toBe(['attrs' => ['name' => 'Joe'], 'content' => 'c']);
  });

  it('coerces a non-string html filter result to a string', function (): void {
    $callback = captureShortcodeCallback('htmlcoerce', [], 'blocks/htmlcoerce');

    Filters\expectApplied('fern:core:shortcode:html')->once()->andReturn(12345);

    Actions\expectDone('fern:core:shortcode:render')->once()->whenHappen(function (): void {
      echo 'ignored';
    });

    expect($callback([]))->toBe('12345');
  });
});
