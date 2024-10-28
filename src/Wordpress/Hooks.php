<?php

declare(strict_types=1);

namespace Fern\Core\Wordpress;

use Fern\Core\Utils\Utils;

abstract class Hooks {
  /**
   * Add an event handler to the event named. Alternative of add_action
   *
   * @param callable        $function     The function to call.
   * @param string|string[] $eventName    The name of the event to hook the callback to.
   * @param callable        $callback     The callback
   * @param int             $priority     The callback priority.
   * @param int             $acceptedArgs The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   */
  public static function _add($function, string|array $eventName, $callback, int $priority = 10, int $acceptedArgs = -1): void {
    if (is_string($eventName)) {
      $eventName = [$eventName];
    }

    if ($acceptedArgs === -1) {
      $acceptedArgs = Utils::getCallableExpectedArgumentsNumber($callback);
    }

    foreach ($eventName as $event) {
      call_user_func($function, $event, $callback, $priority, $acceptedArgs);
    }
  }
}
