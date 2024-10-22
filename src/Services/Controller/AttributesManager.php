<?php

declare(strict_types=1);

namespace Fern\Core\Services\Controller;

use Fern\Core\Errors\AttributeValidationException;
use ReflectionMethod;
use ReflectionAttribute;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Actions\Attributes\CapabilitiesHandler;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Filters;

/**
 * Service to handle method attributes validation and execution
 */
class AttributesManager extends Singleton {
  /** @var array<string, callable> */
  private array $handlers = [];

  /**
   * Bootstrap every attribute handlers
   */
  public static function boot(): void {
    $manager = AttributesManager::getInstance();
    $handlers = Filters::apply('fern:core:controller:attribute_handlers', [
      RequireCapabilities::class => [new CapabilitiesHandler(), 'handle'],
    ]);

    foreach ($handlers as $attributeClass => $handler) {
      $manager->register($attributeClass, $handler);
    }
  }

  /**
   * Register a handler for an attribute
   *
   * @param string $attributeClass The attribute class to handle
   * @param callable $handler The handler function
   */
  public function register(string $attributeClass, callable $handler): void {
    if (is_array($handler) && count($handler) === 2) {
      if (!($handler[0] instanceof AttributesHandler)) {
        throw new \InvalidArgumentException('Invalid handler provided for attribute. Handler must implement \Fern\Core\Services\Controller\AttributesHandler interface.');
      }
    } else {
      if (!($handler instanceof AttributesHandler)) {
        throw new \InvalidArgumentException('Invalid handler provided for attribute. Handler must implement \Fern\Core\Services\Controller\AttributesHandler interface.');
      }
    }

    $this->handlers[$attributeClass] = $handler;
  }

  /**
   * Validate and execute all attributes on a method
   *
   * @param object $controller The controller instance
   * @param string $methodName The method name to check
   * @param Request $request The current request
   *
   * @return bool
   */
  public function validateMethod(object $controller, string $methodName, Request $request): bool {
    try {
      $reflection = new ReflectionMethod($controller, $methodName);
      $errors = [];

      foreach ($reflection->getAttributes() as $attribute) {
        $result = $this->handleAttribute($attribute, $controller, $methodName, $request);
        if ($result !== true) {
          $errors[] = $result;
        }
      }

      if (!empty($errors)) {
        throw new AttributeValidationException(
          sprintf(
            'Validation failed for method %s::%s - %s',
            get_class($controller),
            $methodName,
            implode(', ', $errors)
          )
        );
      }

      return true;
    } catch (\ReflectionException $e) {
      throw new AttributeValidationException(
        sprintf('Failed to validate method %s::%s - %s',
          get_class($controller),
          $methodName,
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Handle a single attribute
   */
  private function handleAttribute(
    ReflectionAttribute $attribute,
    object $controller,
    string $methodName,
    Request $request
  ): bool|string {
    $attributeClass = $attribute->getName();

    if (isset($this->handlers[$attributeClass])) {
      return ($this->handlers[$attributeClass])(
        $attribute,
        $controller,
        $methodName,
        $request
      );
    }

    return true;
  }
}
