<?php declare(strict_types=1);

use Fern\Core\Utils\Types;

mutates(Types::class);

describe('getSafeWpValue', function (): void {
  it('maps a WP_Error to null', function (): void {
    expect(Types::getSafeWpValue(new WP_Error('code', 'boom')))->toBeNull();
  });

  it('maps WordPress empty states to null', function (): void {
    expect(Types::getSafeWpValue(false))->toBeNull()
      ->and(Types::getSafeWpValue(''))->toBeNull()
      ->and(Types::getSafeWpValue(null))->toBeNull()
      ->and(Types::getSafeWpValue([]))->toBeNull();
  });

  it('treats the literal strings null/false/undefined as null, case-insensitively', function (): void {
    expect(Types::getSafeWpValue('null'))->toBeNull()
      ->and(Types::getSafeWpValue('FALSE'))->toBeNull()
      ->and(Types::getSafeWpValue('Undefined'))->toBeNull();
  });

  it('does not treat the literal string "true" as empty', function (): void {
    expect(Types::getSafeWpValue('true'))->toBe('true');
  });

  it('returns real scalar and array values untouched', function (): void {
    expect(Types::getSafeWpValue('hello'))->toBe('hello')
      ->and(Types::getSafeWpValue(0))->toBe(0)
      ->and(Types::getSafeWpValue(['a' => 1]))->toBe(['a' => 1]);
  });
});

describe('getSafeFloat', function (): void {
  it('coerces values to float', function (mixed $in, float $out): void {
    expect(Types::getSafeFloat($in))->toBe($out);
  })->with([
    'float' => [1.5, 1.5],
    'int' => [3, 3.0],
    'numeric string' => ['2.5', 2.5],
    'true' => [true, 1.0],
    'false' => [false, 0.0],
    'null' => [null, 0.0],
    'array falls through to zero' => [[1], 0.0],
  ]);
});

describe('getSafeInt', function (): void {
  it('coerces values to int', function (mixed $in, int $out): void {
    expect(Types::getSafeInt($in))->toBe($out);
  })->with([
    'int' => [5, 5],
    'float truncates' => [3.9, 3],
    'numeric string' => ['42', 42],
    'true' => [true, 1],
    'false' => [false, 0],
    'null' => [null, 0],
    'array falls through to zero' => [['x'], 0],
  ]);
});

describe('getSafeString', function (): void {
  it('coerces scalars and null to string', function (mixed $in, string $out): void {
    expect(Types::getSafeString($in))->toBe($out);
  })->with([
    'string' => ['x', 'x'],
    'int' => [5, '5'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
    'null' => [null, ''],
  ]);

  it('uses __toString when available', function (): void {
    $stringable = new class () {
      public function __toString(): string {
        return 'stringified';
      }
    };

    expect(Types::getSafeString($stringable))->toBe('stringified');
  });

  it('returns empty string for non-stringable objects and arrays', function (): void {
    expect(Types::getSafeString(new stdClass()))->toBe('')
      ->and(Types::getSafeString([1, 2]))->toBe('');
  });
});

describe('getSafeBool', function (): void {
  it('reads truthy strings case-insensitively', function (string $in): void {
    expect(Types::getSafeBool($in))->toBeTrue();
  })->with(['true', 'TRUE', '1', 'yes', 'On']);

  it('treats other strings as false', function (string $in): void {
    expect(Types::getSafeBool($in))->toBeFalse();
  })->with(['false', 'no', 'off', '', 'maybe']);

  it('coerces non-strings with native boolean rules', function (): void {
    expect(Types::getSafeBool(1))->toBeTrue()
      ->and(Types::getSafeBool(0))->toBeFalse()
      ->and(Types::getSafeBool(null))->toBeFalse()
      ->and(Types::getSafeBool([]))->toBeFalse()
      ->and(Types::getSafeBool(['x']))->toBeTrue();
  });
});

describe('getSafeArray', function (): void {
  it('returns empty array for null', function (): void {
    expect(Types::getSafeArray(null))->toBe([]);
  });

  it('returns arrays untouched', function (): void {
    expect(Types::getSafeArray([1, 2]))->toBe([1, 2]);
  });

  it('wraps a non-array scalar into a single-element array', function (): void {
    expect(Types::getSafeArray('x'))->toBe(['x'])
      ->and(Types::getSafeArray(5))->toBe([5]);
  });
});

describe('getSafeEmail', function (): void {
  it('returns valid emails', function (): void {
    expect(Types::getSafeEmail('a@b.co'))->toBe('a@b.co');
  });

  it('returns empty string for invalid emails', function (): void {
    expect(Types::getSafeEmail('nope'))->toBe('')
      ->and(Types::getSafeEmail(''))->toBe('')
      ->and(Types::getSafeEmail(42))->toBe('');
  });
});

describe('getSafeUrl', function (): void {
  it('returns valid urls', function (): void {
    expect(Types::getSafeUrl('https://example.test/path'))->toBe('https://example.test/path');
  });

  it('returns empty string for invalid urls', function (): void {
    expect(Types::getSafeUrl('not a url'))->toBe('')
      ->and(Types::getSafeUrl(''))->toBe('');
  });
});

describe('getSafeSlug', function (): void {
  it('builds a url-friendly slug', function (string $in, string $out): void {
    expect(Types::getSafeSlug($in))->toBe($out);
  })->with([
    'accents and punctuation' => ['  Héllo World! ## ', 'hllo-world'],
    'already a slug' => ['Already-Slug', 'already-slug'],
    'collapses whitespace' => ['multiple   spaces', 'multiple-spaces'],
    'collapses mixed separators' => ['a - b', 'a-b'],
  ]);

  it('returns empty string when nothing survives sanitisation', function (): void {
    expect(Types::getSafeSlug('***'))->toBe('');
  });
});
