<?php

namespace Fern\Core\Services\QueryMonitor;

use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

class QueryMonitor {
  public static function disable(): void {
    /**
     * Disable Query Monitor output for Fern action requests
     * to prevent JSON corruption.
     */
    Events::on('init', function (): void {
      $request = Request::getCurrent();

      if ($request->isAction()) {
        // Disable Query Monitor output for action requests
        Filters::on('qm/process', '__return_false');
        Filters::on('qm/dispatchers/html', '__return_empty_array');
        Filters::on('qm/dispatchers/ajax', '__return_empty_array');

        // Prevent QM from adding shutdown hooks
        remove_all_actions('shutdown');
        remove_all_actions('wp_footer');

        // Clean any existing output buffers to prevent interference
        while (ob_get_level()) {
          ob_end_clean();
        }
      }
    }, 5);

    /**
     * Alternative approach: Remove QM shutdown hooks after Reply is sent
     */
    Events::on('fern:core:reply:has_been_sent', function (): void {
      // Remove any remaining shutdown actions that could interfere
      remove_all_actions('shutdown');

      // Force clean exit
      if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
      }
    }, 1);
  }
}
