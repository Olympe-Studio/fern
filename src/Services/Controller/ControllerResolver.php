<?php

namespace Fern\Core\Services\Controller;

use Fern\Core\Errors\ControllerRegistration;
use Fern\Core\Factory\Singleton;
use ReflectionClass;

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

  /** @var array Stores registered controllers */
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
   *
   * @return void
   */
  public static function boot(): void {
    $instance = self::getInstance();
    $declaredClasses = get_declared_classes();

    foreach ($declaredClasses as $className) {
      $instance->processClass($className);
    }
  }

  /**
   * Processes a single class to determine if it's a valid controller and registers it if so.
   *
   * @param string $className The name of the class to process
   * @return void
   */
  public function processClass(string $className): void {
    $reflection = new ReflectionClass($className);

    if (!$reflection->implementsInterface(Controller::class)) {
      return;
    }

    $this->validateControllerClass($reflection);

    $instance = $className::getInstance();
    $type = $this->determineControllerType($reflection, $instance);
    $handle = (string) $reflection->getProperty('handle')->getValue();

    $this->register($type, $handle, $reflection->getName());
  }

  /**
   * Validates that a controller class has the required 'handle' property.
   *
   * @param ReflectionClass $reflection
   * @throws ControllerRegistration if the class doesn't meet the requirements
   *
   * @return void
   */
  private function validateControllerClass(ReflectionClass $reflection): void {
    if (!$reflection->hasProperty('handle') || !$reflection->getProperty('handle')->isPublic() || !$reflection->getProperty('handle')->isStatic()) {
      throw new ControllerRegistration("Controller {$reflection->getName()} must have a static public `handle` property.");
    }
  }

  /**
   * Determines the type of a controller based on its properties.
   *
   * @param ReflectionClass $reflection
   * @param object $instance
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

    return ($reflection->hasProperty('isAdmin') && $reflection->getProperty('isAdmin')->isPublic() && $reflection->getProperty('isAdmin')->getValue())
      ? self::TYPE_ADMIN
      : self::TYPE_VIEW;
  }

  /**
   * Register a controller.
   *
   * @param string $type The type of the controller (view, admin, or default)
   * @param string $handle
   * @param string $controller
   *
   * @return void
   */
  public function register(string $type, string $handle, string $controller): void {
    if ($type === self::TYPE_DEFAULT) {
      $this->controllers[self::TYPE_DEFAULT] = $controller;
      return;
    }

    if ($type === self::TYPE_404) {
      $this->controllers[self::TYPE_404] = $controller;
      return;
    }

    $handle = self::PREFIX . $handle;
    $this->controllers[$type][$handle] = $controller;
  }

  /**
   * Resolve a controller by its class name.
   *
   * @param string $type    The type of the controller (view, admin, or default)
   * @param string $handle  The handle of the controller
   *
   * @return string|null
   */
  public function resolve(string $type, string $handle): string|null {
    $handle = self::PREFIX . $handle;

    return $this->controllers[$type][$handle] ?? null;
  }

  /**
   * Get the default controller.
   *
   * @return string|null
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
   *
   * @return string|null
   */
  public function get404Controller(): string|null {
    $notFound = $this->controllers[self::TYPE_404];

    if (!$notFound) {
      throw new ControllerRegistration('No NotFound controller registered. Please register a 404 controller in  /App/Controller with handle set to `_404`.');
    }

    return $notFound;
  }
}
