<?php

declare(strict_types=1);

namespace Fern\Core\Factory;

abstract class Singleton {
  /** @var array<string, static> */
  private static array $_instances = [];

  protected function __construct() {
  }

  /**
   * Avoid clone instance
   */
  private function __clone() {
    // Private clone method to prevent cloning of the instance
  }

  /**
   * Prevent instance unserialization
   */
  final public function __wakeup() {
    // Private wakeup method to prevent unserializing of the instance
  }

  /**
   * Return the unique instance of the class called.
   *
   * @param array<int, mixed> $args
   *
   * @return static The class classed as a unique instance.
   */
  public static function getInstance(array ...$args): static {
    $calledClass = get_called_class();

    if (!isset(self::$_instances[$calledClass])) {
      self::$_instances[$calledClass] = new $calledClass(...$args);
    }

    return self::$_instances[$calledClass];
  }
}
