<?php declare(strict_types=1);

namespace Tests\Concerns;

use Fern\Core\Factory\Singleton;
use ReflectionProperty;

/**
 * Resets framework-wide static state so each test runs in isolation.
 */
trait FlushesSingletons {
  /**
   * Static properties (other than the Singleton registry) that cache state
   * across the whole process and must be reset between tests.
   *
   * @var array<class-string, array<string, mixed>>
   */
  private array $staticStateToReset = [
    \Fern\Core\Fern::class => ['isDev' => null],
    \Fern\Core\Services\Actions\Action::class => ['current' => null],
    \Fern\Core\Services\Controller\ControllerResolver::class => [
      'controllerTypeCache' => [],
      'controllerRegistry' => [],
      'registryLoaded' => false,
    ],
  ];

  protected function flushSingletons(): void {
    Singleton::flushInstances();

    foreach ($this->staticStateToReset as $class => $props) {
      foreach ($props as $name => $value) {
        $this->resetStaticProperty($class, $name, $value);
      }
    }
  }

  /**
   * @param class-string $class
   */
  private function resetStaticProperty(string $class, string $name, mixed $value): void {
    if (!property_exists($class, $name)) {
      return;
    }

    $property = new ReflectionProperty($class, $name);
    $property->setValue(null, $value);
  }
}
