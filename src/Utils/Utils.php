<?php
declare(strict_types=1);
namespace Fern\Core\Utils;

use Closure;
use ReflectionFunction;
use ReflectionMethod;

class Utils {

  /**
  * Reflec a callable.
  *
  * @param callable $callable  The callable to reflect
  *
  * @return ReflectionFunctionAbstract
  */
  public static function reflectCallable($callable) {
   if ($callable instanceof Closure) {
     return new ReflectionFunction($callable);
   }

   if (is_string($callable)) {
     $pcs = explode('::', $callable);
     return count($pcs) > 1
       ? new ReflectionMethod($pcs[0], $pcs[1])
       : new ReflectionFunction($callable)
     ;
   }

   if (!is_array($callable)) {
     $callable = [$callable, '__invoke'];
   }

   return new ReflectionMethod($callable[0], $callable[1]);
 }

  /**
    * Get the number of arguments expected by a callable.
    *
    * @param callable $callable  The callable to inspect.
    *
    * @return int
    */
  public static function getCallableExpectedArgumentsNumber($callable) {
   $reflec = self::reflectCallable($callable);
   return $reflec->getNumberOfParameters();
 }
}