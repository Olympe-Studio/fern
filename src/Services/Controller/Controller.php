<?php

namespace Fern\Services\Controller;

use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

interface Controller {
  /**
   * Handle the request and return a reply.
   *
   * @param Request $request
   * @return Reply
   */
  public function handle(Request $request): Reply;
}
