<?php

declare(strict_types=1);

namespace Fern\Core\Services\Router;

use Fern\Core\Config;
use Fern\Core\Errors\ActionException;
use Fern\Core\Errors\ActionNotFoundException;
use Fern\Core\Errors\AttributeValidationException;
use Fern\Core\Errors\RouterException;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\AttributesManager;
use Fern\Core\Services\Controller\ControllerResolver;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use ReflectionMethod;
use Throwable;

/**
 * The Router class is responsible for resolving the request and calling the appropriate controller.
 */
class Router extends Singleton {
  /**
   * @var array
   */
  private const RESERVED_ACTIONS = ['handle', 'init', 'configure'];
  /**
   * @var Request
   */
  private Request $request;
  /**
   * @var ControllerResolver
   */
  private ControllerResolver $controllerResolver;
  /**
   * @var AttributesManager
   */
  private AttributesManager $attributeManagerr;
  /**
   * @var array
   */
  private array $config;

  public function __construct() {
    $this->request = Request::getInstance();
    $this->config = Config::get('core.routes');
    $this->controllerResolver = ControllerResolver::getInstance();
    $this->attributeManagerr = AttributesManager::getInstance();
  }

  /**
   * Get the config
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Boot the Router
   */
  public static function boot(): void {
    /**
     * Boot the controller resolver.
     */
    ControllerResolver::boot();
    $req = Request::getCurrent();

    Filters::add(['template_include', 'admin_'], static function () {
      $router = Router::getInstance();
      $router->resolve();
    }, 99, 1);

    if ($req->isAction()) {
      AttributesManager::boot();

      Filters::add('admin_init', static function () {
        $router = Router::getInstance();
        $router->resolveAdminActions();
      }, 99, 1);
    }
  }

  /**
   * Resolve the admin actions
   */
  public function resolveAdminActions(): void {
    if ($this->shouldStop()) {
      return;
    }

    $controller = $this->resolveController('admin');
    $this->handleActionRequest($controller);
  }

  /**
   * Resolves the request and calls the appropriate controller.
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
   * Handles a 404 error.
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

      throw new RouterException('Controller handle method must return a Reply object ready to be sent.');
    }
  }

  /**
   * Resolves the controller based on the request.
   */
  private function resolveController(?string $viewType = 'view'): ?string {
    if ($viewType === 'admin') {
      return $this->handleAdminController();
    }

    $id = $this->request->getCurrentId();

    if ($id !== null) {
      /**
       * In the context of multilingual sites, the ID might be an alternate language and we don't want to hardcode everyone of them.
       * This filter allows to change the ID to the appropriate one for the current language before resolving the controller.
       *
       * @param int     $id  The ID to resolve.
       * @param Request $req The current Request
       *
       * @return int|null The resolved ID. When returning null, we will resolve the controller using its taxonomy or post_type instead.
       */
      $id = Filters::apply('fern:core:router:resolve_id', (int) $id, $this->request);

      if (!is_numeric($id) || $id < 0) {
        if (!is_null($id)) {
          throw new RouterException("Invalid ID: {$id}. Must be an integer greater than or equal to 0 or null.");
        }
      }

      if (!is_null($id)) {
        $idController = $this->controllerResolver->resolve($viewType, (string) $id);

        if ($idController !== null) {
          return $idController;
        }
      }
    }

    $type = $this->request->isTerm() ? $this->request->getTaxonomy() : $this->request->getPostType();

    // If we are on a Page unhandled, it's going to fail.
    if ($type === null || $type === 'page' && $viewType !== 'admin') {
      return $this->controllerResolver->getDefaultController();
    }

    $typeController = $this->controllerResolver->resolve($viewType, $type);

    if ($typeController !== null) {
      return $typeController;
    }

    // Unhandled post type, return the default controller.
    if ($viewType !== 'admin') {
      return $this->controllerResolver->getDefaultController();
    }

    return null;
  }

  /**
   * Handles the admin controller.
   */
  private function handleAdminController(): ?string {
    $page = $this->request->getUrlParam('page');

    return $this->controllerResolver->resolve('admin', $page);
  }

  /**
   * Handles an action request.
   *
   * @param string $ctr The controller to handle the action
   *
   * @throws ActionException
   * @throws ActionNotFoundException
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
        throw new ActionException("Action '{$name}' is reserved or a magic method and cannot be used as an action name.");
      }

      if (!method_exists($controller, $name) || !$this->canRunAction($name, $controller)) {
        throw new ActionNotFoundException("Action {$name} not found.");
      }

      $reflection = new ReflectionMethod($controller, $name);

      if (!$reflection->isPublic() || $reflection->isStatic()) {
        throw new ActionException("Action {$name} must be a public and non-static method.");
      }

      $canRun = Filters::apply('fern:core:action:can_run', true, $action, $controller);

      if (!$canRun) {
        throw new ActionNotFoundException("Action {$name} not found.");
      }

      $reply = $controller->{$name}($this->request, $action);
      $reply->send();
    } catch (Throwable $e) {
      $reply = new Reply(500, $e->getMessage(), 'text/plain');
      $reply->send();
    }
  }

  /**
   * Validates if an action can be executed and handles validation errors
   *
   * @param string $name The action name
   * @param object $controller The controller instance
   *
   * @return bool
   */
  private function canRunAction(string $name, object $controller): bool {
    try {
      $validation = $this->attributeManagerr->validateMethod(
        $controller,
        $name,
        $this->request,
      );
    } catch (AttributeValidationException $e) {
      // Hide the error from the user
      error_log($e->getMessage());

      return false;
    }

    return ! ($validation !== true);
  }

  /**
   * Checks if a method name is reserved or a magic method.
   *
   * @param string $methodName The method name to check
   *
   * @return bool
   */
  private function isReservedOrMagicMethod(string $methodName): bool {
    return in_array($methodName, self::RESERVED_ACTIONS, true) || strpos($methodName, '_') === 0;
  }

  /**
   * Handles a GET request.
   *
   * @param string $controller The controller to handle the request
   *
   * @return void
   */
  private function handleGetRequest(string $controller): void {
    $reply = $controller::getInstance()->handle($this->request);

    if ($reply instanceof Reply) {
      $reply->send();
    } else {
      throw new RouterException('Controller handle method must return a Reply object ready to be sent.');
    }
  }

  /**
   * Checks if the request should stop the router from resolving.
   *
   * @return bool
   */
  private function shouldStop(): bool {
    return $this->request->isCLI()
      || $this->request->isXMLRPC()
      || $this->request->isAutoSave()
      || $this->request->isCRON()
      || $this->request->isREST()
      || ($this->request->isAjax() && !$this->request->isAction());
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
      || ((!isset($disabled['search']) || $disabled['search'] !== false) && $this->request->isSearch());
  }
}
