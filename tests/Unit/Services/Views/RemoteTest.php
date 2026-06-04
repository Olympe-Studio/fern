<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Services\Views\Engines\Remote;

function makeRemote(array $overrides = []): Remote {
  return new Remote([
    'protocol' => 'https',
    'host' => 'render.example.com',
    'port' => 3000,
    ...$overrides,
  ]);
}

describe('construction / validation', function (): void {
  it('throws when the protocol is missing', function (): void {
    expect(fn (): Remote => new Remote(['host' => 'h', 'port' => 1]))
      ->toThrow(InvalidArgumentException::class, 'Invalid configuration for remote rendering engine');
  });

  it('throws when the host is missing', function (): void {
    expect(fn (): Remote => new Remote(['protocol' => 'http', 'port' => 1]))
      ->toThrow(InvalidArgumentException::class, 'Invalid configuration for remote rendering engine');
  });

  it('throws when the port is missing', function (): void {
    expect(fn (): Remote => new Remote(['protocol' => 'http', 'host' => 'h']))
      ->toThrow(InvalidArgumentException::class, 'Invalid configuration for remote rendering engine');
  });

  it('boot is a no-op', function (): void {
    $engine = makeRemote();

    $engine->boot();

    expect(true)->toBeTrue();
  });
});

describe('render success', function (): void {
  it('posts to the composed url and returns the response body', function (): void {
    Functions\expect('wp_remote_post')
      ->once()
      ->with('https://render.example.com:3000/home', Mockery::on(static function (array $args): bool {
        return $args['body'] === '{"ctx":{"a":1}}'
          && $args['timeout'] === 2.5
          && $args['headers'] === ['Content-Type' => 'application/json']
          && $args['sslverify'] === false;
      }))
      ->andReturn(['body' => '<html>ok</html>']);

    Functions\expect('wp_remote_retrieve_body')
      ->once()
      ->with(['body' => '<html>ok</html>'])
      ->andReturn('<html>ok</html>');

    $engine = makeRemote();

    expect($engine->render('home', ['ctx' => ['a' => 1]]))->toBe('<html>ok</html>');
  });

  it('honours the sslverify config flag', function (): void {
    Functions\expect('wp_remote_post')
      ->once()
      ->with(Mockery::any(), Mockery::on(static function (array $args): bool {
        return $args['sslverify'] === true;
      }))
      ->andReturn(['k' => 'v']);

    Functions\when('wp_remote_retrieve_body')->justReturn('body');

    $engine = makeRemote(['sslverify' => true]);

    expect($engine->render('page', []))->toBe('body');
  });

  it('applies the timeout and headers filters', function (): void {
    Filters\expectApplied('fern:core:views:engines:remote_timeout')
      ->once()
      ->andReturn(7.0);

    Filters\expectApplied('fern:core:views:engines:remote_headers')
      ->once()
      ->andReturn(['X-Custom' => 'yes']);

    Functions\expect('wp_remote_post')
      ->once()
      ->with(Mockery::any(), Mockery::on(static function (array $args): bool {
        return $args['timeout'] === 7.0 && $args['headers'] === ['X-Custom' => 'yes'];
      }))
      ->andReturn(['ok' => true]);

    Functions\when('wp_remote_retrieve_body')->justReturn('out');

    $engine = makeRemote();

    expect($engine->render('home', []))->toBe('out');
  });

  it('falls back to empty headers when the headers filter returns a non-array', function (): void {
    Filters\expectApplied('fern:core:views:engines:remote_headers')
      ->once()
      ->andReturn('not-an-array');

    Functions\expect('wp_remote_post')
      ->once()
      ->with(Mockery::any(), Mockery::on(static function (array $args): bool {
        return $args['headers'] === [];
      }))
      ->andReturn([]);

    Functions\when('wp_remote_retrieve_body')->justReturn('ok');

    $engine = makeRemote();

    expect($engine->render('home', []))->toBe('ok');
  });

  it('delegates renderBlock to render', function (): void {
    Functions\expect('wp_remote_post')
      ->once()
      ->with('https://render.example.com:3000/my-block', Mockery::any())
      ->andReturn(['x' => 1]);

    Functions\when('wp_remote_retrieve_body')->justReturn('block-out');

    $engine = makeRemote();

    expect($engine->renderBlock('my-block', ['d' => 1]))->toBe('block-out');
  });
});

describe('render failure', function (): void {
  it('throws when wp_remote_post returns a WP_Error', function (): void {
    Functions\expect('wp_remote_post')
      ->once()
      ->andReturn(new WP_Error('http_request_failed', 'connection refused'));

    Functions\expect('wp_remote_retrieve_body')->never();

    $engine = makeRemote();

    expect(fn (): string => $engine->render('home', []))
      ->toThrow(InvalidArgumentException::class, 'Failed to fetch template from remote server. Check that the URL is correct. WP Error: connection refused');
  });
});
