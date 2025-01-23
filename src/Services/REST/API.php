<?php

declare(strict_types=1);

namespace Fern\Core\Services\REST;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Wordpress\Events;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

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
 *   permission?: callable(WP_REST_Request): bool
 * }
 */
class API extends Singleton {
  /** @var array<string, Route> */
  private array $routes = [];

  /** @var RouteConfig */
  private array $config = [
    'namespace' => 'fern',
    'version' => '1',
    'useFernReply' => true
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
   * @param string $path
   * @param callable(Request): mixed $callback
   * @param callable(WP_REST_Request): bool|null $permission
   */
  public static function get(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('GET', $path, $callback, $permission);
  }

  /**
   * @param string $path
   * @param callable(Request): mixed $callback
   * @param callable(WP_REST_Request): bool|null $permission
   */
  public static function post(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('POST', $path, $callback, $permission);
  }

  /**
   * @param string $path
   * @param callable(Request): mixed $callback
   * @param callable(WP_REST_Request): bool|null $permission
   */
  public static function put(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('PUT', $path, $callback, $permission);
  }

  /**
   * @param string $path
   * @param callable(Request): mixed $callback
   * @param callable(WP_REST_Request): bool|null $permission
   */
  public static function delete(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('DELETE', $path, $callback, $permission);
  }

  /**
   * @param string $path
   * @param callable(Request): mixed $callback
   * @param callable(WP_REST_Request): bool|null $permission
   */
  public static function patch(string $path, callable $callback, ?callable $permission = null): self {
    return self::addRoute('PATCH', $path, $callback, $permission);
  }

  /**
   * @param string $key
   * @return bool
   */
  public function hasRouteCollision(string $key): bool {
    return isset($this->routes[$key]);
  }

  /**
   * @param string $method
   * @param string $path
   * @param callable(Request): mixed $callback
   * @param callable(WP_REST_Request): bool|null $permission
   */
  private static function addRoute(
    string $method,
    string $path,
    callable $callback,
    ?callable $permission
  ): self {
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])) {
      throw new \InvalidArgumentException('Invalid method: ' . $method);
    }

    $instance = self::getInstance();
    $path = trim($path, '/');
    $key = sprintf('%s:%s', $method, $path);

    if ($instance->hasRouteCollision($key)) {
      throw new \InvalidArgumentException('Route collision: ' . $key);
    }

    $instance->routes[$key] = [
      'path' => $path,
      'method' => $method,
      'callback' => $callback,
      'permission' => $permission
    ];

    return $instance;
  }

  /**
   * @param callable(WP_REST_Request): bool|null $callback
   * @return callable(WP_REST_Request): bool|Reply
   */
  private function wrapPermissionCallback(?callable $callback): callable {
    return function (WP_REST_Request $request) use ($callback): bool|Reply {
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
   * @return void
   */
  public function registerRoutes(): void {
    $namespace = trim($this->config['namespace'], '/');
    $version = $this->config['version'];
    $base = sprintf('%s/v%s', $namespace, $version);

    foreach ($this->routes as $route) {
      register_rest_route(
        $base,
        $route['path'],
        [
          'methods' => $route['method'],
          'callback' => $this->createCallback($route['callback']),
          'permission_callback' => $this->wrapPermissionCallback($route['permission'] ?? null),
        ]
      );
    }
  }

  /**
   * @param callable(Request): mixed $handler
   * @return callable(WP_REST_Request): WP_REST_Response|WP_Error|Reply
   */
  private function createCallback(callable $handler): callable {
    return function (WP_REST_Request $wpRequest) use ($handler): WP_REST_Response|WP_Error|Reply {
      try {
        $request = Request::getCurrent();
        $result = $handler($request);

        if ($this->config['useFernReply'] ?? false) {
          if ($result instanceof Reply) {
            $result->send();
            exit;
          }

          throw new \Exception('Invalid reply for endpoint ' . $wpRequest->get_method() . ' : ' . $wpRequest->get_route()
            . '. When useFernReply is true, the handler must return a `Fern\Core\Services\HTTP\Reply` instance.');
        }

        return new WP_REST_Response($result, 200);
      } catch (\Throwable $e) {
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
          ['status' => 500]
        );
      }
    };
  }
}
