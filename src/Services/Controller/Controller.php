<?php declare(strict_types=1);

namespace Fern\Core\Services\Controller;

use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

interface Controller {
  /**
   * Handle the request and return a reply.
   */
  public function handle(Request $request): Reply;
}
