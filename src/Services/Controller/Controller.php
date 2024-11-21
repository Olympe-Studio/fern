<?php declare(strict_types=1);

namespace Fern\Core\Services\Controller;

use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

interface Controller {
  /**
   * Handle the request and return a reply.
   */
  public function handle(Request $request): Reply;

  /**
   * Return the unique instance of the class called.
   *
   * @param array<int, mixed> $args
   *
   * @return static The class classed as a unique instance.
   */
  public static function getInstance(array ...$args): static;
}
