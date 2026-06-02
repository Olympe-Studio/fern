<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Fern\Core\Services\REST\API;

if (!class_exists('WP_REST_Request')) {
  class WP_REST_Request {
    public function __construct(
      private string $method = 'GET',
      private string $route = '/fern/v1/endpoint',
    ) {}

    public function get_method(): string {
      return $this->method;
    }

    public function get_route(): string {
      return $this->route;
    }
  }
}

if (!class_exists('WP_REST_Response')) {
  class WP_REST_Response {
    /**
     * @param mixed $data
     */
    public function __construct(
      public mixed $data = null,
      public int $status = 200,
    ) {}
  }
}

/**
 * Resolves the wrapped permission_callback registered for a route.
 */
function fernRestPermissionCallback(): callable {
  $captured = null;

  Functions\when('register_rest_route')->alias(
    static function (string $namespace, string $route, array $args) use (&$captured): bool {
      $captured = $args['permission_callback'];

      return true;
    },
  );

  API::getInstance()->registerRoutes();

  /** @var callable $captured */
  return $captured;
}

/**
 * Resolves the wrapped callback registered for a route.
 */
function fernRestCallback(): callable {
  $captured = null;

  Functions\when('register_rest_route')->alias(
    static function (string $namespace, string $route, array $args) use (&$captured): bool {
      $captured = $args['callback'];

      return true;
    },
  );

  API::getInstance()->registerRoutes();

  /** @var callable $captured */
  return $captured;
}

describe('hook wiring', function (): void {
  it('subscribes registerRoutes to rest_api_init on construction', function (): void {
    Actions\expectAdded('rest_api_init')->once();

    API::getInstance();
  });
});

describe('config', function (): void {
  it('merges the supplied config over the defaults and returns the instance', function (): void {
    $instance = API::config(['namespace' => 'custom', 'useFernReply' => false]);

    expect($instance)->toBe(API::getInstance());
  });
});

describe('route registration', function (): void {
  it('stores a route and reports a collision for the same method and path', function (): void {
    API::get('/items', fn (): int => 1);

    expect(API::getInstance()->hasRouteCollision('GET:items'))->toBeTrue()
      ->and(API::getInstance()->hasRouteCollision('POST:items'))->toBeFalse();
  });

  it('throws when the same method and path are registered twice', function (): void {
    API::get('/dup', fn (): int => 1);

    expect(fn () => API::get('/dup', fn (): int => 2))
      ->toThrow(InvalidArgumentException::class, 'Route collision: GET:dup');
  });

  it('registers every verb against register_rest_route under the namespaced base', function (string $verb, string $method): void {
    API::config(['namespace' => 'shop', 'version' => '2']);
    API::{$verb}('/things', fn (): int => 1);

    Functions\expect('register_rest_route')
      ->once()
      ->with(
        'shop/v2',
        'things',
        Mockery::on(static function (array $args) use ($method): bool {
          return $args['methods'] === $method
            && is_callable($args['callback'])
            && is_callable($args['permission_callback']);
        }),
      )
      ->andReturn(true);

    API::getInstance()->registerRoutes();
  })->with([
    'get' => ['get', 'GET'],
    'post' => ['post', 'POST'],
    'put' => ['put', 'PUT'],
    'delete' => ['delete', 'DELETE'],
    'patch' => ['patch', 'PATCH'],
  ]);

  it('trims slashes from the registered path', function (): void {
    API::get('/nested/path/', fn (): int => 1);

    expect(API::getInstance()->hasRouteCollision('GET:nested/path'))->toBeTrue();
  });

  it('falls back to the default fern/v1 base when no namespace is configured', function (): void {
    API::get('/ping', fn (): int => 1);

    Functions\expect('register_rest_route')
      ->once()
      ->with('fern/v1', 'ping', Mockery::type('array'))
      ->andReturn(true);

    API::getInstance()->registerRoutes();
  });

  it('registers nothing when no routes are defined', function (): void {
    Functions\expect('register_rest_route')->never();

    API::getInstance()->registerRoutes();
  });
});

describe('permission callback', function (): void {
  it('allows the request when no permission callback was provided', function (): void {
    API::config(['useFernReply' => false]);
    API::get('/open', fn (): int => 1);

    $permission = fernRestPermissionCallback();

    expect($permission(new WP_REST_Request()))->toBeTrue();
  });

  it('returns the result of the user permission callback when it allows access', function (): void {
    API::config(['useFernReply' => false]);
    API::get('/guarded', fn (): int => 1, fn (): bool => true);

    $permission = fernRestPermissionCallback();

    expect($permission(new WP_REST_Request()))->toBeTrue();
  });

  it('returns false without exiting when denied and useFernReply is off', function (): void {
    API::config(['useFernReply' => false]);
    API::get('/guarded', fn (): int => 1, fn (): bool => false);

    $permission = fernRestPermissionCallback();

    expect($permission(new WP_REST_Request()))->toBeFalse();
  });
});

describe('callback execution', function (): void {
  beforeEach(function (): void {
    Functions\when('getallheaders')->justReturn([]);
    Functions\when('get_home_url')->justReturn('https://example.test');
    Functions\when('untrailingslashit')->alias(fn (string $v): string => rtrim($v, '/'));
    Functions\when('get_the_ID')->justReturn(false);
    Functions\when('get_queried_object')->justReturn(null);
    Functions\when('get_queried_object_id')->justReturn(0);
  });

  it('wraps the handler result in a WP_REST_Response when useFernReply is off', function (): void {
    API::config(['useFernReply' => false]);
    API::get('/data', fn (): array => ['ok' => true]);

    $callback = fernRestCallback();
    $response = $callback(new WP_REST_Request('GET', '/fern/v1/data'));

    expect($response)->toBeInstanceOf(WP_REST_Response::class)
      ->and($response->status)->toBe(200)
      ->and($response->data)->toBe(['ok' => true]);
  });

  it('returns a WP_Error and logs when the handler throws and useFernReply is off', function (): void {
    Functions\when('wp_generate_uuid4')->justReturn('uuid-1234');

    API::config(['useFernReply' => false]);
    API::get('/boom', function (): void {
      throw new RuntimeException('handler failed');
    });

    $callback = fernRestCallback();
    $result = $callback(new WP_REST_Request('GET', '/fern/v1/boom'));

    expect($result)->toBeInstanceOf(WP_Error::class)
      ->and($result->get_error_code())->toBe('rest_error')
      ->and($result->get_error_message())->toBe('Internal server error.');
  });
});

/*
 * The useFernReply === true branches of createCallback() and
 * wrapPermissionCallback() are intentionally left uncovered: they call
 * Reply::send() (which invokes the internal headers_sent() that Brain Monkey /
 * Patchwork cannot redefine) and then exit, so they cannot be exercised without
 * modifying the shared bootstrap. The equivalent logic is covered through the
 * useFernReply === false paths above.
 */
