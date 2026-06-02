<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Concerns\FlushesSingletons;

/**
 * Base case for pure-logic tests that do not touch WordPress globals.
 */
abstract class TestCase extends BaseTestCase {
  use FlushesSingletons;

  protected function setUp(): void {
    parent::setUp();
    $this->flushSingletons();
  }

  protected function tearDown(): void {
    $this->flushSingletons();
    parent::tearDown();
  }
}
