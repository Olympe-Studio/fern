<?php declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

/**
 * Implements Controller and exposes a static handle but does not extend
 * Singleton, so validation must reject it.
 */
class NonSingletonController implements Controller {
  public static string $handle = 'non-singleton';

  public function handle(Request $request): Reply {
    return new Reply(200, '');
  }

  /**
   * @param array<int, mixed> $args
   */
  public static function getInstance(array ...$args): static {
    return new static();
  }
}
