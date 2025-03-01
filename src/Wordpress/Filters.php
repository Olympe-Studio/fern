<?php

declare(strict_types=1);

namespace Fern\Core\Wordpress;

class Filters extends Hooks {
  /**
   * Create a new filter function Alternative of add_filter
   *
   * @param string|string[] $filters      The name of the filter to hook the callback to.
   * @param callable        $callback     The callback
   * @param int             $priority     The callback priority. The higher the number, the latter the event will be triggered.
   * @param int             $acceptedArgs The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   */
  public static function add(string|array $filters, $callback, int $priority = 10, int $acceptedArgs = -1): void {
    self::_add('add_filter', $filters, $callback, $priority, $acceptedArgs);
  }

  /**
   * Alias of add
   *
   * @param string|string[] $eventName    The name of the event to hook the callback to.
   * @param callable        $callback     The callback
   * @param int             $priority     The callback priority. The higher the number, the latter the event will be triggered.
   * @param int             $acceptedArgs The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   */
  public static function on(string|array $eventName, $callback, int $priority = 10, int $acceptedArgs = -1): void {
    self::add($eventName, $callback, $priority, $acceptedArgs);
  }

  /**
   * an alias that call apply_filter with the same arguments
   *
   * @param string $filter        The name of the filter hook.
   * @param mixed  $startingValue The value to filter.
   * @param mixed  ...$args       Additional parameters to pass to the callback functions.
   *
   * @return mixed The filtered value after all hooked functions are applied to it.
   */
  public static function apply(string $filter, $startingValue, ...$args) {
    return apply_filters($filter, $startingValue, ...$args);
  }

  /**
   * Remove a filter handler from the filter named. Alternative of remove_filter
   *
   * @param string|string[] $filterName The name of the filter to remove the callback from.
   */
  public static function removeHandlers(string|array $filterName): void {
    if (is_string($filterName)) {
      $filterName = [$filterName];
    }

    foreach ($filterName as $filter) {
      remove_all_filters($filter);
    }
  }
}
