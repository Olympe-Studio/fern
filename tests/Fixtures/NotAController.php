<?php declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * A plain class that does not implement the Controller interface.
 */
class NotAController {
  public static string $handle = 'not-a-controller';

  public function handle(): int {
    return 0;
  }
}
