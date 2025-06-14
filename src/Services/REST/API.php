<?php

declare(strict_types=1);

namespace Fern\Core\Services\REST;

use Exception;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Events;
use InvalidArgumentException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @phpstan-type RouteConfig array{
 *   namespace?: string,
 *   version?: string,
 *   useFernReply?: bool
 * }
 * @phpstan-type Route array{
 *   path: string,
 *   method: string,
 *   callback: callable(Request): mixed,
 *   permission?: callable(WP_REST_Request<array<string,mixed>>): bool
 * }
 */
class API extends Singleton {
  /** @var array<string, Route> */
  private array $routes = [];

  /** @var RouteConfig */
  private array $config = [
    'namespace' => 'fern',
    'version' => '1',
    'useFernReply' => true,
  ];

  protected function __construct() {
    parent::__construct();
    Events::on('rest_api_init', [$this, 'registerRoutes']);
  }

  /**
   * @param RouteConfig $config
   */
  public static function config(array $config): self {
    $instance = self::getInstance();
    $instance->config = array_merge($instance->config, $config);

    return $instance;
  }

  /**
   * @param callable(Request): mixed             $callback
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $permission
   */
  public static function get(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('GET', $path, $callback, $permission);
  }

  /**
   * @param callable(Request): mixed             $callback
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $permission
   */
  public static function post(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('POST', $path, $callback, $permission);
  }

  /**
   * @param callable(Request): mixed             $callback
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $permission
   */
  public static function put(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('PUT', $path, $callback, $permission);
  }

  /**
   * @param callable(Request): mixed             $callback
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $permission
   */
  public static function delete(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('DELETE', $path, $callback, $permission);
  }

  /**
   * @param callable(Request): mixed             $callback
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $permission
   */
  public static function patch(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('PATCH', $path, $callback, $permission);
  }

  /**
   * Check if a route exists
   *
   * @param string $key The route key
   *
   * @return bool True if the route exists, false otherwise
   */
  public function hasRouteCollision(string $key): bool {
    return isset($this->routes[$key]);
  }

  /**
   * Register the routes
   *
   * @return void
   */
  public function registerRoutes(): void {
    $namespace = trim((string) ($this->config['namespace'] ?? 'fern'), '/');
    $version = (string) ($this->config['version'] ?? '1');
    $base = sprintf('%s/v%s', $namespace, $version);

    foreach ($this->routes as $route) {
      register_rest_route(
        $base,
        $route['path'],
        [
          'methods' => $route['method'],
          'callback' => $this->createCallback($route['callback']),
          'permission_callback' => $this->wrapPermissionCallback($route['permission'] ?? null),
        ],
      );
    }
  }

  /**
   * @param callable(Request): mixed             $callback
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $permission
   */
  private static function addRoute(
    string $method,
    string $path,
    callable $callback,
    ?callable $permission,
  ): self {
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], true)) {
      throw new InvalidArgumentException('Invalid method: ' . $method);
    }

    $instance = self::getInstance();
    $path = trim($path, '/');
    $key = sprintf('%s:%s', $method, $path);

    if ($instance->hasRouteCollision($key)) {
      throw new InvalidArgumentException('Route collision: ' . $key);
    }

    $route = [
      'path' => $path,
      'method' => $method,
      'callback' => $callback,
    ];

    if ($permission !== null) {
      /** @var callable(WP_REST_Request<array<string,mixed>>): bool $permission */
      $route['permission'] = $permission;
    }

    /** @var Route $route */
    $instance->routes[$key] = $route;

    return $instance;
  }

  /**
   * @param callable(WP_REST_Request<array<string,mixed>>): bool|null $callback
   *
   * @return callable(WP_REST_Request<array<string,mixed>>): bool
   */
  private function wrapPermissionCallback(?callable $callback): callable {
    return function (WP_REST_Request $request) use ($callback): bool {
      if ($callback === null) {
        return true;
      }

      $isAllowed = $callback($request);

      if (!$isAllowed && ($this->config['useFernReply'] ?? false)) {
        $reply = new Reply(403, [
          'message' => 'Forbidden',
          'code' => 403,
          'success' => false,
        ]);
        $reply->send();
        exit;
      }

      return $isAllowed;
    };
  }

  /**
   * @param callable(Request): mixed $handler
   *
   * @return callable(WP_REST_Request<array<string,mixed>>): (WP_REST_Response|WP_Error)
   */
  private function createCallback(callable $handler): callable {
    return function (WP_REST_Request $wpRequest) use ($handler): WP_REST_Response|WP_Error {
      try {
        $request = Request::getCurrent();
        $result = $handler($request);

        if ($this->config['useFernReply'] ?? false) {
          if ($result instanceof Reply) {
            $result->send();
            exit;
          }

          throw new Exception('Invalid reply for endpoint ' . $wpRequest->get_method() . ' : ' . $wpRequest->get_route()
            . '. When useFernReply is true, the handler must return a `Fern\Core\Services\HTTP\Reply` instance.');
        }

        return new WP_REST_Response($result, 200);
      } catch (Throwable $e) {
        if ($this->config['useFernReply'] ?? false) {
          $reply = new Reply(500, [
            'message' => $e->getMessage(),
            'code' => 500,
            'success' => false,
          ]);
          $reply->send();
          exit;
        }

        return new WP_Error(
          'rest_error',
          $e->getMessage(),
          ['status' => 500],
        );
      }
    };
  }
}
