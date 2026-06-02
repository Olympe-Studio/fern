<?php declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

/**
 * Implements Controller and extends Singleton but is missing the required
 * static `handle` property, so validation must reject it.
 */
class NoHandleController extends Singleton implements Controller {
  public function handle(Request $request): Reply {
    return new Reply(200, '');
  }
}
