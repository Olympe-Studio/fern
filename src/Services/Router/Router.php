<?php

declare(strict_types=1);

namespace Fern\Core\Services\Router;

use Fern\Core\Config;
use Fern\Core\Context;
use Fern\Core\Errors\ActionException;
use Fern\Core\Errors\ActionNotFoundException;
use Fern\Core\Errors\AttributeValidationException;
use Fern\Core\Errors\RouterException;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\AttributesManager;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\Controller\ControllerResolver;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use ReflectionMethod;
use Throwable;

/**
 * @phpstan-type RouterConfig array{
 *     disable?: array{
 *         author_archive?: bool,
 *         tag_archive?: bool,
 *         category_archive?: bool,
 *         date_archive?: bool,
 *         feed?: bool,
 *         search?: bool
 *     }
 * }
 */
class Router extends Singleton {
  /**
   * @var array<string>
   */
  private const RESERVED_ACTIONS = ['handle', 'init', 'configure'];

  /**
   */
  private Request $request;

  /**
   */
  private ControllerResolver $controllerResolver;

  /**
   */
  private AttributesManager $attributeManagerr;

  /**
   */
  public bool $didPass;

  /**
   * @phpstan-var RouterConfig
   */
  private array $config;

  /**
   * @var array<string, string|null> Controller resolution cache
   */
  private array $controllerCache = [];

  public function __construct() {
    $this->request = Request::getInstance();
    $this->config = Config::get('core.routes');
    $this->controllerResolver = ControllerResolver::getInstance();
    $this->attributeManagerr = AttributesManager::getInstance();
    $this->didPass = false;
  }

  /**
   * Get the config
   *
   * @return RouterConfig
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Checks if the router has passed
   */
  public static function passed(): bool {
    return Router::getInstance()->didPass;
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

    Filters::on(['template_include', 'admin_'], static function (): void {
      Context::boot();

      $router = Router::getInstance();
      $router->resolve();
    }, 10, 1);

    if ($req->isAction()) {
      AttributesManager::boot();

      Filters::on('admin_init', static function (): void {
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
    /** @var class-string<Controller> $controller */
    $controller = $this->resolveController('admin');
    $this->handleActionRequest($controller);
  }

  /**
   * Resolves the request and calls the appropriate controller.
   */
  public function resolve(): void {
    Events::trigger('qm/start', 'fern:resolve_routes');
    $req = $this->request;

    // Pass back to WP.
    if ($this->shouldStop()) {
      Events::trigger('qm/stop', 'fern:resolve_routes');
      $this->didPass = true;
      return;
    }

    if ($this->should404()) {
      $this->handle404();

      return;
    }

    /** @var class-string<Controller>|null $controller */
    $controller = $this->resolveController();

    if ($controller !== null) {
      if ($req->isGet()) {
        $this->handleGetRequest($controller);
      }

      if ($req->isPost() && $req->isAction()) {
        $this->handleActionRequest($controller);
      }
    } else {
      $this->didPass = true;
    }
  }

  /**
   * Handles a 404 error.
   */
  public function handle404(): void {
    $controller = $this->controllerResolver->get404Controller();

    if (is_null($controller)) {
      // Let WordPress handle the 404.
      return;
    }

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
   *
   * @param string|null $viewType The view type to resolve.
   *
   * @return string|null The controller name or null if it doesn't exists.
   */
  public function resolveController(?string $viewType = null): ?string {
    $cacheKey = $viewType ?? 'view';

    // For admin requests, add the page to the cache key
    if ($viewType === 'admin') {
      $cacheKey .= '_' . $this->request->getUrlParam('page');
    } else {
      $id = $this->request->getCurrentId();
      $type = $this->request->isTerm() ? $this->request->getTaxonomy() : $this->request->getPostType();
      $cacheKey .= '_' . $id . '_' . ($type ?? 'unknown');

      if ($this->request->isArchive()) {
        $cacheKey .= '_archive';
      }
    }

    if (isset($this->controllerCache[$cacheKey])) {
      return $this->controllerCache[$cacheKey];
    }

    $controller = $this->resolveControllerInternal($viewType);
    $this->controllerCache[$cacheKey] = $controller;

    return $controller;
  }

  /**
   * Internal implementation of resolveController without caching
   * 
   * @param string|null $viewType The view type to resolve.
   * 
   * @return string|null The controller name or null if it doesn't exists.
   */
  private function resolveControllerInternal(?string $viewType = null): ?string {
    if ($viewType === 'admin') {
      return $this->handleAdminController();
    }

    $id = $this->request->getCurrentId();

    if ($id !== -1) {
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
        $actualViewType = $viewType ?? 'view';
        /** @var class-string<Controller>|null $idController */
        $idController = $this->controllerResolver->resolve($actualViewType, (string) $id);

        if ($idController !== null) {
          return $idController;
        }
      }
    }

    $type = $this->request->isTerm() ? $this->request->getTaxonomy() : $this->request->getPostType();

    /**
     * If we are on an archive page, resolve the controller for the archive page using a page ID.
     */
    if ($this->request->isArchive()) {
      $controller = $this->resolveArchivePage($type, $viewType);

      if ($controller !== null) {
        return $controller;
      }
    }

    // If we are on a Page unhandled, it's going to fail.
    if ($type === null || $type === 'page' && $viewType !== 'admin') {
      /** @var class-string<Controller> $defaultController */
      $defaultController = $this->controllerResolver->getDefaultController();

      return $defaultController;
    }

    $actualViewType = $viewType ?? 'view';
    /** @var class-string<Controller>|null $typeController */
    $typeController = $this->controllerResolver->resolve($actualViewType, $type);

    if ($typeController !== null) {
      return $typeController;
    }

    // Unhandled post type, return the default controller.
    if ($viewType !== 'admin') {
      /** @var class-string<Controller> $defaultController */
      $defaultController = $this->controllerResolver->getDefaultController();

      return $defaultController;
    }

    return null;
  }

  /**
   * Resolve the controller for an archive page using a page ID.
   *
   * @param string|null $type     The type to resolve the archive page for
   * @param string|null $viewType The view type to resolve the archive page for
   *
   * @return string|null The controller name or null if it doesn't exists.
   */
  private function resolveArchivePage(?string $type, ?string $viewType): ?string {
    $pageId = $this->getArchivePageId($type);
    $actualViewType = $viewType ?? 'view';

    if ($pageId > 0) {
      /** @var class-string<Controller>|null $controller */
      $controller = $this->controllerResolver->resolve($actualViewType, (string) $pageId);
    } else {
      $handle = "archive_{$type}";
      /** @var class-string<Controller>|null $controller */
      $controller = $this->controllerResolver->resolve($actualViewType, $handle);
    }

    return $controller;
  }

  /**
   * Get the archive page ID
   *
   * @param string|null $type The type to get the archive page ID for
   *
   * @return int The archive page ID
   */
  private function getArchivePageId(?string $type): int {
    $id = -1;

    if (function_exists('wc_get_page_id')) {
      if (\is_shop()) {
        $id = \wc_get_page_id('shop');
      }

      if (\is_account_page()) {
        $id = \wc_get_page_id('myaccount');
      }

      if (\is_cart()) {
        $id = \wc_get_page_id('cart');
      }

      if (\is_checkout()) {
        $id = \wc_get_page_id('checkout');
      }
    }

    if (\is_home()) {
      $id = (int) \get_option('page_for_posts');
    }

    return Filters::apply('fern:core:router:get_archive_page_id', $id, $type);
  }

  /**
   * Handles the admin controller.
   *
   * @return string|null The controller name or null if it doesn't exists.
   */
  private function handleAdminController(): ?string {
    $page = $this->request->getUrlParam('page');

    return $this->controllerResolver->resolve('admin', $page);
  }

  /**
   * Handles an action request.
   *
   * @param class-string<Controller> $ctr The controller to handle the action
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

      if ($name === '' || !$name) {
        throw new ActionNotFoundException('Action name is required.');
      }

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
   * @param string $name       The action name
   * @param object $controller The controller instance
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
   */
  private function isReservedOrMagicMethod(string $methodName): bool {
    return in_array($methodName, self::RESERVED_ACTIONS, true) || str_starts_with($methodName, '_');
  }

  /**
   * Handles a GET request.
   *
   * @param string $controller The controller to handle the request
   */
  private function handleGetRequest(string $controller): void {
    Events::trigger('qm/start', 'fern:make_all_queries');
    $reply = $controller::getInstance()->handle($this->request);

    if ($reply instanceof Reply) {
      $reply->send();
    }

    $req = $this->request;

    if ($req->is404()) {
      $this->handle404();
    } else {
      throw new RouterException('Controller handle method must return a Reply object ready to be sent.');
    }
  }

  /**
   * Checks if the request should stop the router from resolving.
   */
  private function shouldStop(): bool {
    $request = $this->request;

    // Fast path: if any of these conditions are true, return immediately
    if ($request->isCLI() || $request->isXMLRPC() || $request->isAutoSave()) {
      return true;
    }

    // Consolidate remaining conditions to minimize method calls
    return $request->isCRON()
      || $request->isREST()
      || $request->isSitemap()
      || ($request->isAjax() && !$request->isAction())
      || (is_null(\get_queried_object()) && !$request->isAction() && !$request->is404());
  }

  /**
   * Checks if the request should return a 404 error.
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
