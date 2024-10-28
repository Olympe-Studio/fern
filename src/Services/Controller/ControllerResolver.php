<?php declare(strict_types=1);

namespace Fern\Core\Services\Controller;

use Fern\Core\Errors\ControllerRegistration;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Events;
use ReflectionClass;

/**
 * @phpstan-type ControllerRegistry array{
 *   view: array<string, class-string<Controller>>,
 *   admin: array<string, class-string<Controller>>,
 *   default: ?class-string<Controller>,
 *   _404: ?class-string<Controller>
 * }
 */
class ControllerResolver extends Singleton {
  /** @var string Prefix for controller handles to avoid problems with numeric values */
  private const PREFIX = 'c_';

  /** @var string Constant for view type controllers */
  private const TYPE_VIEW = 'view';

  /** @var string Constant for admin type controllers */
  private const TYPE_ADMIN = 'admin';

  /** @var string Constant for default type controller */
  private const TYPE_DEFAULT = 'default';

  /** @var string Constant for 404 type controller */
  private const TYPE_404 = '_404';

  /** @var ControllerRegistry */
  private array $controllers;

  public function __construct() {
    $this->controllers = [
      self::TYPE_VIEW => [],
      self::TYPE_ADMIN => [],
      self::TYPE_DEFAULT => null,
      self::TYPE_404 => null,
    ];
  }

  /**
   * Boots the ControllerResolver by processing all declared classes.
   */
  public static function boot(): void {
    $instance = self::getInstance();
    $declaredClasses = get_declared_classes();

    foreach ($declaredClasses as $className) {
      $instance->processClass($className);
    }

    Events::on('admin_menu', [$instance, 'registerAdminMenus'], 10, 0);
  }

  /**
   * Register all admin menus from controllers
   */
  public function registerAdminMenus(): void {
    /** @var array<string, class-string<Controller>> $adminControllers */
    $adminControllers = $this->controllers[self::TYPE_ADMIN];

    foreach ($adminControllers as $controller) {
      $this->registerAdminMenu($controller);
    }
  }

  /**
   * Processes a single class to determine if it's a valid controller and registers it if so.
   *
   * @param class-string $className
   */
  public function processClass(string $className): void {
    if (!class_exists($className)) {
      return;
    }

    /** @var class-string<Controller> $className */
    $reflection = new ReflectionClass($className);

    if (!$reflection->implementsInterface(Controller::class)) {
      return;
    }

    /** @var ReflectionClass<Controller> $reflection */
    $this->validateControllerClass($reflection);
    /** @var ReflectionClass<Controller> $reflection */
    $type = $this->determineControllerType($reflection);
    $handle = (string) $reflection->getProperty('handle')->getValue();

    $this->register($type, $handle, $reflection->getName());
  }

  /**
   * Register a controller.
   *
   * @param string                   $type       The type of the controller (view, admin, or default)
   * @param string                   $handle     The handle of the controller
   * @param class-string<Controller> $controller
   */
  public function register(string $type, string $handle, string $controller): void {
    if ($type === self::TYPE_DEFAULT) {
      /** @var class-string<Controller> $controller */
      $this->controllers[self::TYPE_DEFAULT] = $controller;

      return;
    }

    if ($type === self::TYPE_404) {
      /** @var class-string<Controller> $controller */
      $this->controllers[self::TYPE_404] = $controller;

      return;
    }

    if ($type === self::TYPE_VIEW || $type === self::TYPE_ADMIN) {
      $handle = self::PREFIX . $handle;
      /** @var array<string, class-string<Controller>> $typeControllers */
      $typeControllers = $this->controllers[$type];
      $typeControllers[$handle] = $controller;
      $this->controllers[$type] = $typeControllers;
    }
  }

  /**
   * Resolve a controller by its class name.
   *
   * @param string $type   The type of the controller (view, admin, or default)
   * @param string $handle The handle of the controller
   */
  public function resolve(string $type, string $handle): string|null {
    $handle = self::PREFIX . $handle;

    return $this->controllers[$type][$handle] ?? null;
  }

  /**
   * Get the default controller.
   */
  public function getDefaultController(): string|null {
    $default = $this->controllers[self::TYPE_DEFAULT];

    if (!$default) {
      throw new ControllerRegistration('No default controller registered. Please register a default controller in  /App/Controller with handle set to `_default`.');
    }

    return $default;
  }

  /**
   * Get the 404 controller.
   */
  public function get404Controller(): string|null {
    $notFound = $this->controllers[self::TYPE_404];

    if (!$notFound) {
      throw new ControllerRegistration('No NotFound controller registered. Please register a 404 controller in  /App/Controller with handle set to `_404`.');
    }

    return $notFound;
  }

  /**
   * Register admin menu for a specific controller
   *
   * @param class-string $controllerClass
   */
  private function registerAdminMenu(string $controllerClass): void {
    $controller = $controllerClass::getInstance();

    if (!method_exists($controller, 'configure')) {
      return;
    }

    /** @phpstan-ignore-next-line */
    $config = $controller->configure();

    // Validate required configuration
    if (!isset($config['page_title']) || !isset($config['menu_title'])) {
      throw new ControllerRegistration("Admin controller {$controllerClass} must provide 'page_title' and 'menu_title' in configure()");
    }

    // Set default values for optional parameters
    $defaults = [
      'capability' => 'manage_options',
      'menu_slug' => '',
      'icon' => '',
      'position' => null,
      'parent_slug' => null,
    ];

    $config = array_merge($defaults, $config);

    // Override the menu slug with the controller's handle
    $reflection = new ReflectionClass($controllerClass);
    $handle = $reflection->getProperty('handle')->getValue();
    $config['menu_slug'] = $handle;

    // Override the callback with the controller's handle
    $callback = function () use ($controllerClass): void {
      $controller = $controllerClass::getInstance();
      $reply = $controller->handle(Request::getCurrent());
      $reply->send();
    };

    // Register the menu based on whether it's a submenu or top-level menu
    if ($config['parent_slug']) {
      add_submenu_page(
          $config['parent_slug'],
          $config['page_title'],
          $config['menu_title'],
          $config['capability'],
          $config['menu_slug'],
          $callback,
      );
    } else {
      add_menu_page(
          $config['page_title'],
          $config['menu_title'],
          $config['capability'],
          $config['menu_slug'],
          $callback,
          $config['icon'],
          $config['position'],
      );

      // If there are submenu items defined, register them
      if (isset($config['submenu']) && is_array($config['submenu'])) {
        foreach ($config['submenu'] as $submenu) {
          // Ensure required submenu fields are present
          if (!isset($submenu['page_title']) || !isset($submenu['menu_title'])) {
            continue;
          }

          $submenu = array_merge([
            'capability' => $config['capability'],
            'menu_slug' => '',
            'callback' => $callback,
          ], $submenu);

          add_submenu_page(
              $config['menu_slug'],
              $submenu['page_title'],
              $submenu['menu_title'],
              $submenu['capability'],
              $submenu['menu_slug'],
              $submenu['callback'],
          );
        }
      }
    }
  }

  /**
   * Validates that a controller class has the required 'handle' property.
   *
   * @param ReflectionClass<Controller> $reflection The reflection class instance
   *
   * @throws ControllerRegistration<Controller> if the class doesn't meet the requirements
   */
  private function validateControllerClass(ReflectionClass $reflection): void {
    $className = $reflection->getName();

    if (!$reflection->hasProperty('handle') || !$reflection->getProperty('handle')->isPublic() || !$reflection->getProperty('handle')->isStatic()) {
      throw new ControllerRegistration("Controller {$className} must have a static public `handle` property.");
    }

    if (!$reflection->isSubclassOf(Singleton::class)) {
      throw new ControllerRegistration("Controller {$className} must extend \Fern\Core\Factory\Singleton class.");
    }
  }

  /**
   * Determines the type of a controller based on its properties.
   *
   * @param ReflectionClass<Controller> $reflection The reflection class instance
   *
   * @return string The determined controller type
   */
  private function determineControllerType(ReflectionClass $reflection): string {
    $handleProperty = $reflection->getProperty('handle');
    $handleProperty->setAccessible(true);
    $handleValue = $handleProperty->getValue();

    if ($handleValue === '_default') {
      return self::TYPE_DEFAULT;
    }

    if ($handleValue === '_404') {
      return self::TYPE_404;
    }

    $traits = $reflection->getTraitNames();

    if (in_array('Fern\Core\Services\Controller\AdminController', $traits, true)) {
      return self::TYPE_ADMIN;
    }

    return self::TYPE_VIEW;
  }
}
