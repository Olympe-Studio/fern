<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Errors\ReplyParsingError;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Tests\Fixtures\FakeRequest;

/**
 * Reply subclass whose terminate() seam throws instead of exiting, so send()
 * and redirect() can be exercised in-process.
 */
final class TerminatingReply extends Reply {
  protected function terminate(): never {
    throw new RuntimeException('terminated');
  }
}

/**
 * Request double that lets each test control isAction().
 */
final class ActionAwareRequest extends FakeRequest {
  public function __construct(private bool $action = false) {}

  public function isAction(): bool {
    return $this->action;
  }
}

function bindRequest(bool $isAction): void {
  $fake = new ActionAwareRequest($isAction);
  (new ReflectionProperty(Singleton::class, '_instances'))
    ->setValue(null, [Request::class => $fake]);
}

/**
 * @return array{output: string, terminated: bool}
 */
function captureSend(Reply $reply): array {
  $terminated = false;
  $baseLevel = ob_get_level();
  ob_start();

  try {
    $reply->send();
  } catch (RuntimeException $e) {
    $terminated = $e->getMessage() === 'terminated';
  }

  $output = ob_get_level() > $baseLevel ? ob_get_clean() : '';

  while (ob_get_level() < $baseLevel) {
    ob_start();
  }

  return ['output' => $output === false ? '' : $output, 'terminated' => $terminated];
}

beforeEach(function (): void {
  Functions\when('get_option')->justReturn('UTF-8');
});

describe('send', function (): void {
  it('echoes the JSON-encoded content for an array body', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, ['ok' => true]);

    $result = captureSend($reply);

    expect($result['output'])->toBe('{"ok":true}')
      ->and($result['terminated'])->toBeTrue();
  });

  it('throws ReplyParsingError when an application/json reply has a non-array body', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, 'not-an-array', 'application/json');

    ob_start();
    $threw = false;

    try {
      $reply->send();
    } catch (ReplyParsingError) {
      $threw = true;
    }

    ob_get_clean();

    expect($threw)->toBeTrue();
  });

  it('echoes the string body for a text/html reply', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, '<p>hi</p>', 'text/html');

    $result = captureSend($reply);

    expect($result['output'])->toBe('<p>hi</p>')
      ->and($result['terminated'])->toBeTrue();
  });

  it('sets the X-FERN-ACTION-REPLY header when the request is an action', function (): void {
    bindRequest(true);
    $reply = new TerminatingReply(200, ['ok' => true]);

    $result = captureSend($reply);

    expect($reply->getHeader('X-FERN-ACTION-REPLY'))->toBeTrue()
      ->and($result['terminated'])->toBeTrue();
  });

  it('does not set the action header for a regular request', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, ['ok' => true]);

    captureSend($reply);

    expect($reply->hasHeader('X-FERN-ACTION-REPLY'))->toBeFalse();
  });

  it('emits chunk sizes on the chunked transfer path', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, 'hello', 'text/html');
    $reply->setHeader('Transfer-Encoding', 'chunked');

    $result = captureSend($reply);

    expect($result['output'])->toBe("5\r\nhello\r\n")
      ->and($result['terminated'])->toBeTrue();
  });

  it('emits the chunked body, closing chunk and trailers when trailers are present', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, 'hi', 'text/html');
    $reply->addTrailer('X-Sum', 'abc');

    $result = captureSend($reply);

    expect($result['output'])->toBe("2\r\nhi\r\n0\r\nX-Sum: abc\r\n\r\n")
      ->and($result['terminated'])->toBeTrue();
  });

  it('is a no-op when the reply is hijacked', function (): void {
    bindRequest(false);
    $reply = new TerminatingReply(200, ['ok' => true]);
    $reply->hijack();

    $result = captureSend($reply);

    expect($result['output'])->toBe('')
      ->and($result['terminated'])->toBeFalse();
  });
});

describe('redirect', function (): void {
  it('calls wp_safe_redirect then terminates', function (): void {
    bindRequest(false);
    Functions\expect('wp_safe_redirect')->once()->with('/target', 302)->andReturn(true);

    $reply = new TerminatingReply(302, '', 'text/html');
    $terminated = false;

    try {
      $reply->redirect('/target');
    } catch (RuntimeException $e) {
      $terminated = $e->getMessage() === 'terminated';
    }

    expect($terminated)->toBeTrue();
  });

  it('is a no-op when the reply is hijacked', function (): void {
    bindRequest(false);
    Functions\when('wp_safe_redirect')->justReturn(true);

    $reply = new TerminatingReply(302, '', 'text/html');
    $reply->hijack();
    $terminated = false;

    try {
      $reply->redirect('/target');
    } catch (RuntimeException $e) {
      $terminated = $e->getMessage() === 'terminated';
    }

    expect($terminated)->toBeFalse();
  });
});
