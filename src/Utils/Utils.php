<?php

declare(strict_types=1);

namespace Fern\Core\Utils;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Utils {
  /**
   * Reflect a callable.
   *
   * @param callable $callable The callable to reflect
   */
  public static function reflectCallable($callable): ReflectionFunctionAbstract {
    if ($callable instanceof Closure) {
      return new ReflectionFunction($callable);
    }

    if (is_string($callable)) {
      $pcs = explode('::', $callable);

      return count($pcs) > 1
        ? new ReflectionMethod($pcs[0], $pcs[1])
        : new ReflectionFunction($callable);
    }

    if (!is_array($callable) || count($callable) !== 2) {
      $callable = [$callable, '__invoke'];
    }

    return new ReflectionMethod($callable[0], $callable[1]);
  }

  /**
   * Formats a path with trailing slash
   *
   * @param string $path Path to format
   *
   * @return string Path with trailing slash
   */
  public static function addTrailingSlash(string $path): string {
    return rtrim($path, '/\\') . '/';
  }

  /**
   * Get the number of arguments expected by a callable.
   *
   * @param callable $callable The callable to inspect.
   */
  public static function getCallableExpectedArgumentsNumber($callable): int {
    $reflec = self::reflectCallable($callable);

    return $reflec->getNumberOfParameters();
  }
}
