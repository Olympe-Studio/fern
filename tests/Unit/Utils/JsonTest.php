<?php declare(strict_types=1);

use Fern\Core\Utils\JSON;

mutates(JSON::class);

describe('validate', function (): void {
  it('rejects an empty string', function (): void {
    expect(JSON::validate(''))->toBeFalse();
  });

  it('accepts well-formed JSON', function (): void {
    expect(JSON::validate('{"a":1}'))->toBeTrue()
      ->and(JSON::validate('[1,2,3]'))->toBeTrue();
  });

  it('rejects malformed JSON', function (): void {
    expect(JSON::validate('{"a":}'))->toBeFalse()
      ->and(JSON::validate('not json'))->toBeFalse();
  });
});

describe('encode', function (): void {
  it('encodes data to a compact JSON string', function (): void {
    expect(JSON::encode(['a' => 1, 'b' => true]))->toBe('{"a":1,"b":true}');
  });

  it('leaves unicode and slashes unescaped by default', function (): void {
    expect(JSON::encode(['name' => 'Héllo']))->toBe('{"name":"Héllo"}')
      ->and(JSON::encode(['url' => 'a/b']))->toBe('{"url":"a/b"}');
  });

  it('honours custom flags', function (): void {
    expect(JSON::encode(['url' => 'a/b'], JSON_THROW_ON_ERROR))->toBe('{"url":"a\\/b"}');
  });
});

describe('decode', function (): void {
  it('returns null for an empty string without the throw flag', function (): void {
    expect(JSON::decode(''))->toBeNull();
  });

  it('throws for an empty string with the throw flag', function (): void {
    JSON::decode('', false, 512, JSON_THROW_ON_ERROR);
  })->throws(InvalidArgumentException::class, 'JSON string cannot be empty');

  it('decodes to an object by default', function (): void {
    $result = JSON::decode('{"a":1}');

    expect($result)->toBeInstanceOf(stdClass::class)
      ->and($result->a)->toBe(1);
  });

  it('decodes to an associative array when asked', function (): void {
    expect(JSON::decode('{"a":1}', true))->toBe(['a' => 1]);
  });

  it('returns null for malformed JSON without the throw flag', function (): void {
    expect(JSON::decode('{invalid'))->toBeNull();
  });

  it('rethrows for malformed JSON with the throw flag', function (): void {
    JSON::decode('{invalid', false, 512, JSON_THROW_ON_ERROR);
  })->throws(JsonException::class);
});

describe('decodeToArray', function (): void {
  it('throws for an empty string', function (): void {
    JSON::decodeToArray('');
  })->throws(JsonException::class, 'JSON string cannot be empty');

  it('decodes an object into an associative array', function (): void {
    expect(JSON::decodeToArray('{"a":1,"b":2}'))->toBe(['a' => 1, 'b' => 2]);
  });

  it('decodes a JSON array', function (): void {
    expect(JSON::decodeToArray('[1,2,3]'))->toBe([1, 2, 3]);
  });

  it('throws when the decoded value is not an array', function (): void {
    JSON::decodeToArray('42');
  })->throws(JsonException::class, 'Decoded JSON is not an array');
});

describe('pretty', function (): void {
  it('pretty-prints with indentation and newlines', function (): void {
    $pretty = JSON::pretty(['a' => 1]);

    expect($pretty)->toBe("{\n    \"a\": 1\n}");
  });
});
