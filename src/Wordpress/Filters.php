<?php
declare(strict_types=1);
namespace Fern\Core\Wordpress;

class Filters {
  use Hooks;

  /**
   * Create a new filter function Alternative of add_filter
   *
   * @param string|string[]  $filters   The name of the filter to hook the callback to.
   * @param callable         $callback  The callback
   * @param int              $priority  The callback priority. The higher the number, the latter the event will be triggered.
   * @param int              $acceptedArgs The number of arguments the function accept (by default Heracles will reflect it from the passed callback).
   *
   * @return void
   */
  public static function add(string|array $filters, $callback, int $priority = 10, int $acceptedArgs = -1) {
    self::_add('add_filter', $filters, $callback, $priority, $acceptedArgs);
  }

  /**
   * an alias that call apply_filter with the same arguments
   *
   * @param string $filter         The name of the filter hook.
   * @param mixed $startingValue   The value to filter.
   * @param mixed ...$args         Additional parameters to pass to the callback functions.
   *
   * @return mixed   The filtered value after all hooked functions are applied to it.
   */
  public static function apply(string $filter, $startingValue, ...$args) {
    return apply_filters($filter, $startingValue, ...$args);
  }
}