<?php declare(strict_types=1);

namespace Tests\Concerns;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Helpers for tests that exercise WordPress-coupled framework code.
 */
trait InteractsWithWp {
  /**
   * Stubs the broadly-used translation and escape helpers.
   *
   * The WordPress hook functions (add_action/add_filter/do_action/apply_filters)
   * are handled natively by Brain Monkey and asserted through its Actions/Filters
   * API, so they are intentionally not stubbed here.
   */
  protected function stubCommonWpFunctions(): void {
    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();
  }

  /**
   * Resets the superglobals consumed by Request so each test controls its own
   * request environment.
   */
  protected function resetSuperglobals(): void {
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset(
      $_SERVER['CONTENT_TYPE'],
      $_SERVER['REQUEST_URI'],
      $_SERVER['HTTP_USER_AGENT'],
      $_SERVER['HTTP_ACCEPT_LANGUAGE'],
    );
  }

  /**
   * Stubs WC() to return a mock WooCommerce container.
   *
   * @return \Mockery\MockInterface The mocked WooCommerce instance
   */
  protected function stubWooCommerce(): Mockery\MockInterface {
    $wc = Mockery::mock('WooCommerce');
    Functions\when('WC')->justReturn($wc);

    return $wc;
  }
}
