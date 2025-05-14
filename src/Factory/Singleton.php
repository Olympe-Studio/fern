<?php

declare(strict_types=1);

namespace Fern\Core\Factory;

abstract class Singleton {
  /** @var array<string, static> */
  private static array $_instances = [];

  protected function __construct() {}

  /**
   * Avoid clone instance
   */
  private function __clone() {}

  /**
   * Prevent instance unserialization
   */
  final public function __wakeup(): void {}

  /**
   * Return the unique instance of the class called.
   *
   * @param array<int, mixed> ...$args Constructor arguments
   *
   * @return static The class classed as a unique instance.
   */
  public static function getInstance(array ...$args): static {
    $calledClass = static::class;

    return self::$_instances[$calledClass] ??= new $calledClass(...$args);
  }
}
