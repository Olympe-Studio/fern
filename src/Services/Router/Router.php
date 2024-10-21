<?php

declare(strict_types=1);

namespace Fern\Core\Services\Router;

use Fern\Core\Config;
use Fern\Core\Errors\ActionException;
use Fern\Core\Errors\ActionNotFoundException;
use Fern\Core\Errors\RouterException;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Controller\ControllerResolver;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use ReflectionMethod;
use Throwable;

/**
 * The Router class is responsible for resolving the request and calling the appropriate controller.
 */
class Router extends Singleton {
  private const RESERVED_ACTIONS = ['handle', 'init'];

  private Request $request;
  private ControllerResolver $controllerResolver;
  private array $config;

  public function __construct() {
    $this->request = Request::getInstance();
    $this->config = Config::get('core.routes');
    $this->controllerResolver = ControllerResolver::getInstance();
  }

  /**
   * Get the config
   *
   * @return array
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Boot the Router
   *
   * @return void
   */
  public static function boot(): void {
    Filters::add('template_include', static function() {
      /**
       * Boot the controller resolver.
       */
      ControllerResolver::boot();
      require_once __DIR__ . '/RouteResolver.php';
    }, 9999, 1);
  }

  /**
   * Resolves the request and calls the appropriate controller.
   *
   * @return void
   */
  public function resolve(): void {
    Events::trigger('qm/start', 'fern:resolve_routes');
    $req = $this->request;

    if ($this->shouldStop()) {
      Events::trigger('qm/stop', 'fern:resolve_routes');
      return;
    }

    if ($this->should404()) {
      $this->handle404();
      return;
    }

    $controller = $this->resolveController();
    if ($controller !== null) {
      if ($req->isGet()) {
        $this->handleGetRequest($controller);
      }

      if ($req->isPost() && $req->isAction()) {
        $this->handleActionRequest($controller);
      }
    }
  }

  /**
   * Resolves the controller based on the request.
   *
   * @return string|null
   */
  private function resolveController(?string $viewType = 'view'): ?string {
    $id = $this->request->getCurrentId();

    if ($id !== null) {
      $idController = $this->controllerResolver->resolve($viewType, (string) $id);
      if ($idController !== null) {
        return $idController;
      }
    }

    $type = $this->request->isTerm() ? $this->request->getTaxonomy() : $this->request->getPostType();

    // If we are on a Page unhandled, it's going to fail.
    if ($type === null || $type === 'page') {
      return $this->controllerResolver->getDefaultController();
    }

    $typeController = $this->controllerResolver->resolve($viewType, $type);
    if ($typeController !== null) {
      return $typeController;
    }

    return $this->controllerResolver->getDefaultController();
  }

  /**
   * Handles a 404 error.
   *
   * @return void
   */
  public function handle404(): void {
    $controller = $this->controllerResolver->get404Controller();
    $reply = $controller::getInstance()->handle($this->request);

    if ($reply instanceof Reply) {
      $reply->code(404);
      Events::trigger('qm/stop', 'fern:resolve_routes');
      $reply->send();
    } else {
      Events::trigger('qm/stop', 'fern:resolve_routes');
      throw new RouterException("Controller handle method must return a Reply object ready to be sent.");
    }
  }

  /**
   * Handles an action request.
   *
   * @param string $controller
   *
   * @return void
   */
  private function handleActionRequest(string $ctr): void {
    $action = $this->request->getAction();

    if ($action->isBadRequest()) {
      $reply = new Reply(400, 'Bad Request', 'text/plain');
      $reply->send();
      return;
    }

    try {
      $name = $action->getName();
      $controller = $ctr::getInstance();

      if ($this->isReservedOrMagicMethod($name)) {
        throw new ActionException("Action '$name' is reserved or a magic method and cannot be used as an action name.");
      }

      if (!method_exists($controller, $name)) {
        throw new ActionNotFoundException("Action $name not found in controller " . get_class($controller));
      }

      $reflection = new ReflectionMethod($controller, $name);
      if (!$reflection->isPublic() || $reflection->isStatic()) {
        throw new ActionNotFoundException("Action $name must be a public and non-static method.");
      }

      $reply = $controller->{$name}($this->request, $action);
      $reply->send();
    } catch (Throwable $e) {
      $reply = new Reply(500, $e->getMessage(), 'text/plain');
      $reply->send();
    }
  }

  /**
   * Checks if a method name is reserved or a magic method.
   *
   * @param string $methodName
   * @return bool
   */
  private function isReservedOrMagicMethod(string $methodName): bool {
    return in_array($methodName, self::RESERVED_ACTIONS, true) || strpos($methodName, '__') === 0;
  }

  /**
   * Handles a GET request.
   *
   * @param string $controller
   *
   * @return void
   */
  private function handleGetRequest(string $controller): void {
    $reply = $controller::getInstance()->handle($this->request);

    if ($reply instanceof Reply) {
      $reply->send();
    } else {
      throw new RouterException("Controller handle method must return a Reply object ready to be sent.");
    }
  }

  /**
   * Checks if the request should stop the router from resolving.
   *
   * @param Request $req The request instance.
   *
   * @return bool
   */
  private function shouldStop(): bool {
    return $this->request->isCLI()
      || $this->request->isXMLRPC()
      || $this->request->isAutoSave()
      || $this->request->isCRON()
      || $this->request->isREST()
      || ($this->request->isAjax() && !$this->request->isAction())
    ;
  }

  /**
   * Checks if the request should return a 404 error.
   *
   * @return bool
   */
  private function should404(): bool {
    if ($this->request->isAction()) {
      return false;
    }

    $disabled = $this->getConfig()['disable'] ?? [];

    return $this->request->is404()
      // Always return 404 for attachments as it should never create pages beside the media URL.
      || $this->request->isAttachment()
      // User can disable author, tag, category and date archives.
      || ((!isset($disabled['author_archive']) || $disabled['author_archive'] !== false) && $this->request->isAuthor())
      || ((!isset($disabled['tag_archive']) || $disabled['tag_archive'] !== false) && $this->request->isTag())
      || ((!isset($disabled['category_archive']) || $disabled['category_archive'] !== false) && $this->request->isCategory())
      || ((!isset($disabled['date_archive']) || $disabled['date_archive'] !== false) && $this->request->isDate())
      || ((!isset($disabled['feed']) || $disabled['feed'] !== false) && $this->request->isFeed())
      || ((!isset($disabled['search']) || $disabled['search'] !== false) && $this->request->isSearch())
    ;
  }
}
