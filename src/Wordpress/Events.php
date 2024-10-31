<?php

declare(strict_types=1);

namespace Fern\Core\Wordpress;

class Events extends Hooks {
  /**
   * Add an event handler to the event named. Alternative of add_action
   *
   * @param string|string[] $eventName    The name of the event to hook the callback to.
   * @param callable        $callback     The callback
   * @param int             $priority     The callback priority.
   * @param int             $acceptedArgs The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   */
  public static function addHandlers(string|array $eventName, $callback, int $priority = 10, int $acceptedArgs = -1): void {
    self::_add('add_action', $eventName, $callback, $priority, $acceptedArgs);
  }

  /**
   * Alias of addHandlers
   *
   * @param string|string[] $eventName    The name of the event to hook the callback to.
   * @param callable        $callback     The callback
   * @param int             $priority     The callback priority.
   * @param int             $acceptedArgs The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   */
  public static function on(string|array $eventName, $callback, int $priority = 10, int $acceptedArgs = -1): void {
    self::addHandlers($eventName, $callback, $priority, $acceptedArgs);
  }

  /**
   * Trigger an event with the passed arguments.
   *
   * @param string $name    The name of the event to trigger.
   * @param mixed  ...$args The arguments to pass to the event.
   */
  public static function trigger(string $name, mixed ...$args): void {
    do_action($name, ...$args);
  }

  /**
   * Trigger an event and return the output as a string.
   *
   * @param string       $name The name of the event to trigger.
   * @param array<mixed> $args The arguments to pass to the event.
   *
   * @return string The output of the event.
   */
  public static function renderToString(string $name, array $args = []): string {
    ob_start();
    self::trigger($name, ...$args);
    $result = ob_get_clean();

    return $result ? $result : '';
  }

  /**
   * Remove an event handler from the event named. Alternative of remove_action
   *
   * @param string|array<string> $eventName The name of the event to remove the callback from.
   */
  public static function removeHandlers(string|array $eventName): void {
    if (is_string($eventName)) {
      $eventName = [$eventName];
    }

    foreach ($eventName as $event) {
      remove_all_actions($event);
    }
  }
}
