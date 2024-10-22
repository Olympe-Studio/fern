<?php

declare(strict_types=1);

namespace Fern\Core\Wordpress;

class Events {
  use Hooks;

  /**
   * Add an event handler to the event named. Alternative of add_action
   *
   * @param string|string[]  $eventName     The name of the event to hook the callback to.
   * @param callable         $callback      The callback
   * @param int              $priority      The callback priority.
   * @param int              $acceptedArgs  The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   *
   * @return void
   */
  public static function addHandlers(string|array $eventName, $callback, int $priority = 10, int $acceptedArgs = -1) {
    self::_add('add_action', $eventName, $callback, $priority, $acceptedArgs);
  }

  /**
   * Trigger an event with the passed arguments.
   *
   * @param string  $eventName The name of the event to trigger.
   * @param mixed[] $args
   */
  public static function trigger(string $name, ...$args) {
    do_action($name, ...$args);
  }

  /**
   * Remove an event handler from the event named. Alternative of remove_action
   *
   * @param string|string[] $eventName The name of the event to remove the callback from.
   *
   * @return void
   */
  public static function removeHandlers(string|array $eventName) {
    remove_all_actions($eventName);
  }
}
