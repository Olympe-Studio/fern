<?php declare(strict_types=1);

use Fern\Core\Services\HTTP\Reply;

describe('construction', function (): void {
  it('defaults to a 200 text/html empty reply', function (): void {
    $reply = new Reply();

    expect($reply->getContentType())->toBe('text/html')
      ->and($reply->getBody())->toBe('')
      ->and($reply->toArray()['status'])->toBe(200);
  });

  it('infers application/json from an array body', function (): void {
    $reply = new Reply(200, ['a' => 1]);

    expect($reply->getContentType())->toBe('application/json');
  });

  it('keeps text/html for a string body', function (): void {
    expect((new Reply(200, 'hello'))->getContentType())->toBe('text/html');
  });

  it('does not override an explicit content type', function (): void {
    $reply = new Reply(201, ['a' => 1], 'text/plain');

    expect($reply->getContentType())->toBe('text/plain');
  });
});

describe('fluent setters', function (): void {
  it('sets the status via code, status and statusCode', function (): void {
    expect((new Reply())->code(404)->toArray()['status'])->toBe(404)
      ->and((new Reply())->status(500)->toArray()['status'])->toBe(500)
      ->and((new Reply())->statusCode(301)->toArray()['status'])->toBe(301);
  });

  it('sets the content type via contentType and type', function (): void {
    expect((new Reply())->contentType('application/xml')->getContentType())->toBe('application/xml')
      ->and((new Reply())->type('text/csv')->getContentType())->toBe('text/csv');
  });

  it('sets and reads the body', function (): void {
    $reply = (new Reply())->setBody('payload');

    expect($reply)->toBeInstanceOf(Reply::class)
      ->and($reply->getBody())->toBe('payload');
  });
});

describe('headers', function (): void {
  it('sets, reads and checks headers', function (): void {
    $reply = (new Reply())->setHeader('X-Foo', 'bar');

    expect($reply->getHeader('X-Foo'))->toBe('bar')
      ->and($reply->hasHeader('X-Foo'))->toBeTrue()
      ->and($reply->getHeader('missing'))->toBeNull()
      ->and($reply->getHeaders())->toBe(['X-Foo' => 'bar']);
  });

  it('removes a single header and resets all', function (): void {
    $reply = (new Reply())->setHeader('A', 1)->setHeader('B', 2);

    expect($reply->removeHeader('A')->hasHeader('A'))->toBeFalse();
    expect($reply->resetHeader()->getHeaders())->toBe([]);
  });
});

describe('trailers', function (): void {
  it('adds, reads, checks and removes trailers', function (): void {
    $reply = (new Reply())->addTrailer('X-Checksum', 'abc');

    expect($reply->getTrailers())->toBe(['X-Checksum' => 'abc'])
      ->and($reply->hasTrailer('X-Checksum'))->toBeTrue()
      ->and($reply->removeTrailer('X-Checksum')->hasTrailer('X-Checksum'))->toBeFalse();
  });

  it('resets all trailers', function (): void {
    $reply = (new Reply())->addTrailer('A', 1)->addTrailer('B', 2);

    expect($reply->resetTrailers()->getTrailers())->toBe([]);
  });
});

describe('hijacking', function (): void {
  it('reflects the hijack flag in toArray and can be reset', function (): void {
    $reply = (new Reply())->hijack();
    expect($reply->toArray()['hijacked'])->toBeTrue();

    expect($reply->resetHijack()->toArray()['hijacked'])->toBeFalse();
  });

  it('send() is a no-op when hijacked', function (): void {
    $reply = (new Reply(200, 'body'))->hijack();

    expect($reply->send())->toBeNull();
  });

  it('redirect() is a no-op when hijacked', function (): void {
    $reply = (new Reply())->hijack();

    expect($reply->redirect('/elsewhere'))->toBeNull();
  });
});

describe('serialization', function (): void {
  it('serializes an array body for json replies', function (): void {
    $reply = new Reply(200, ['a' => 1], 'application/json');

    expect($reply->toArray()['body'])->toBe(['a' => 1]);
  });

  it('serializes a non-array json body to an empty array', function (): void {
    $reply = new Reply(200, 'not-an-array', 'application/json');

    expect($reply->toArray()['body'])->toBe([]);
  });

  it('serializes scalar and stringable bodies for text replies', function (): void {
    $stringable = new class () {
      public function __toString(): string {
        return 'as-string';
      }
    };

    expect((new Reply(200, 42, 'text/html'))->toArray()['body'])->toBe('42')
      ->and((new Reply(200, $stringable, 'text/html'))->toArray()['body'])->toBe('as-string');
  });

  it('serializes a resource body via its stream contents', function (): void {
    $resource = fopen('php://memory', 'rb+');
    fwrite($resource, 'streamed');
    rewind($resource);

    expect((new Reply(200, $resource, 'text/html'))->toArray()['body'])->toBe('streamed');

    fclose($resource);
  });

  it('tags the array with class and timestamp metadata', function (): void {
    $array = (new Reply())->toArray();

    expect($array['__class'])->toBe(Reply::class)
      ->and($array['__timestamp'])->toBeInt();
  });

  it('round-trips through toArray/fromArray', function (): void {
    $original = (new Reply(202, ['ok' => true], 'application/json'))
      ->setHeader('X-Trace', 'id')
      ->addTrailer('X-Sum', 'z')
      ->hijack();

    $restored = Reply::fromArray($original->toArray());

    expect($restored->toArray()['status'])->toBe(202)
      ->and($restored->getContentType())->toBe('application/json')
      ->and($restored->getBody())->toBe(['ok' => true])
      ->and($restored->getHeader('X-Trace'))->toBe('id')
      ->and($restored->getTrailers())->toBe(['X-Sum' => 'z'])
      ->and($restored->toArray()['hijacked'])->toBeTrue();
  });

  it('exposes toArray through jsonSerialize', function (): void {
    $reply = new Reply(200, ['a' => 1], 'application/json');

    $serialized = $reply->jsonSerialize();
    $array = $reply->toArray();
    unset($serialized['__timestamp'], $array['__timestamp']);

    expect($serialized)->toBe($array);
  });
});
