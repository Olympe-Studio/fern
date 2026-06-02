<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Services\Actions\Attributes\CacheHandler;
use Fern\Core\Services\Actions\Attributes\CacheReply;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Fern;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Utils\Cache;
use Tests\Fixtures\FakeRequest;

class CacheHandlerController {
  public int $calls = 0;

  #[CacheReply(ttl: 600)]
  public function build(): Reply {
    $this->calls++;

    return (new Reply(200, ['count' => $this->calls]))->hijack();
  }

  #[CacheReply(ttl: 600, varyBy: ['id'])]
  public function varied(): Reply {
    $this->calls++;

    return (new Reply(200, ['count' => $this->calls]))->hijack();
  }

  #[CacheReply(key: 'fixed:key')]
  public function keyed(): Reply {
    $this->calls++;

    return (new Reply(200, ['count' => $this->calls]))->hijack();
  }

  #[Nonce('x')]
  public function notCache(): Reply {
    $this->calls++;

    return (new Reply(200, ['count' => $this->calls]))->hijack();
  }
}

/**
 * @return ReflectionAttribute<object>
 */
function cacheAttribute(string $method, string $attributeClass = CacheReply::class): ReflectionAttribute {
  $reflection = new ReflectionMethod(CacheHandlerController::class, $method);

  return $reflection->getAttributes($attributeClass)[0];
}

function setCacheAction(array $body): void {
  $property = new ReflectionProperty(Action::class, 'current');
  $property->setValue(null, new Action(new FakeRequest($body)));
}

beforeEach(function (): void {
  Functions\when('get_option')->justReturn([]);
  Functions\when('update_option')->justReturn(true);
  Functions\when('delete_option')->justReturn(true);
});

describe('cache miss', function (): void {
  it('executes the action and stores the reply array on a miss', function (): void {
    setCacheAction(['action' => 'build']);
    $controller = new CacheHandlerController();

    $result = (new CacheHandler())->handle(
      cacheAttribute('build'),
      $controller,
      'build',
      new FakeRequest([]),
    );

    $key = 'fern:action:cache:' . CacheHandlerController::class . ':build';

    expect($result)->toBeTrue()
      ->and($controller->calls)->toBe(1)
      ->and(Cache::get($key))->toBeArray()
      ->and(Cache::get($key)['status'])->toBe(200);
  });

  it('varies the cache key by the configured action parameters', function (): void {
    setCacheAction(['action' => 'varied', 'args' => ['id' => '7']]);
    $controller = new CacheHandlerController();

    (new CacheHandler())->handle(
      cacheAttribute('varied'),
      $controller,
      'varied',
      new FakeRequest([]),
    );

    $key = 'fern:action:cache:' . CacheHandlerController::class . ':varied:7';

    expect(Cache::get($key))->toBeArray();
  });

  it('uses an explicit cache key when one is provided', function (): void {
    setCacheAction(['action' => 'keyed']);
    $controller = new CacheHandlerController();

    (new CacheHandler())->handle(
      cacheAttribute('keyed'),
      $controller,
      'keyed',
      new FakeRequest([]),
    );

    expect(Cache::get('fern:action:cache:fixed:key'))->toBeArray();
  });
});

describe('cache hit', function (): void {
  it('replays the cached reply without executing the action when not in dev', function (): void {
    setCacheAction(['action' => 'build']);
    $controller = new CacheHandlerController();

    $key = 'fern:action:cache:' . CacheHandlerController::class . ':build';
    $cached = (new Reply(201, ['from' => 'cache']))->hijack()->toArray();
    Cache::set($key, $cached);

    $result = (new CacheHandler())->handle(
      cacheAttribute('build'),
      $controller,
      'build',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue()
      ->and($controller->calls)->toBe(0);
  });

  it('falls back to executing the action when the cached payload cannot be replayed', function (): void {
    setCacheAction(['action' => 'build']);
    $controller = new CacheHandlerController();

    $key = 'fern:action:cache:' . CacheHandlerController::class . ':build';
    Cache::set($key, ['status' => 'not-an-int', 'body' => fn () => null]);

    $result = (new CacheHandler())->handle(
      cacheAttribute('build'),
      $controller,
      'build',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue()
      ->and($controller->calls)->toBe(1);
  });
});

describe('development mode', function (): void {
  it('bypasses the cache hit and re-executes when Fern is in dev mode', function (): void {
    $isDev = new ReflectionProperty(Fern::class, 'isDev');
    $isDev->setValue(null, true);

    setCacheAction(['action' => 'build']);
    $controller = new CacheHandlerController();

    $key = 'fern:action:cache:' . CacheHandlerController::class . ':build';
    $cached = (new Reply(201, ['from' => 'cache']))->hijack()->toArray();
    Cache::set($key, $cached);

    $result = (new CacheHandler())->handle(
      cacheAttribute('build'),
      $controller,
      'build',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue()
      ->and($controller->calls)->toBe(1);
  });
});

describe('non-cache attribute', function (): void {
  it('short-circuits to true and never touches the cache', function (): void {
    setCacheAction(['action' => 'notCache']);
    $controller = new CacheHandlerController();

    $result = (new CacheHandler())->handle(
      cacheAttribute('notCache', Nonce::class),
      $controller,
      'notCache',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue()
      ->and($controller->calls)->toBe(0);
  });
});
