<?php declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Provides callables of every shape for reflection-based tests.
 */
class CallableFixture {
  public function instanceMethod(int $a, int $b): int {
    return $a + $b;
  }

  public static function staticMethod(string $a): string {
    return $a;
  }

  public function __invoke(int $x): int {
    return $x;
  }
}
