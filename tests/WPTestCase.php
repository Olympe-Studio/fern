<?php declare(strict_types=1);

namespace Tests;

use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Concerns\FlushesSingletons;
use Tests\Concerns\InteractsWithWp;

/**
 * Base case for tests that exercise WordPress-coupled code. Wires Brain Monkey
 * so global WP functions can be stubbed and asserted.
 */
abstract class WPTestCase extends BaseTestCase {
  use FlushesSingletons;
  use InteractsWithWp;

  protected function setUp(): void {
    parent::setUp();
    Monkey\setUp();
    $this->flushSingletons();
    $this->resetSuperglobals();
    $this->stubCommonWpFunctions();
  }

  protected function tearDown(): void {
    $this->flushSingletons();
    Mockery::close();
    Monkey\tearDown();
    parent::tearDown();
  }
}
