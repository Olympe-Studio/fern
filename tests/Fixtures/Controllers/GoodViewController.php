<?php declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

/**
 * A valid view controller used to exercise processClass / resolve.
 */
class GoodViewController extends Singleton implements Controller {
  public static string $handle = 'good-view';

  public function handle(Request $request): Reply {
    return new Reply(200, '');
  }

  public function doSomething(): int {
    return 1;
  }
}
