<?php

declare(strict_types=1);

namespace Fern\Core\Services\Router;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;

class Router extends Singleton {
  /**
   * Resolves the request and calls the appropriate controller.
   *
   * @return void
   */
  public static function resolve() {
    $req = Request::getInstance();

    if (self::shouldStop($req)) {
      return;
    }

    // ID Specific Controllers gets the priority
    $id = $req->getCurrentId();

    if ($req->isTerm()) {
      $taxonomy = $req->getTaxonomy();
      // blablabla
    }

    if (!$req->isTerm()) {
      $postType = $req->getPostType();
      // blablabla
    }

    $controller = null;
    if (!is_null($controller)) {
      if ($req->isGet()) {
        // blablabla
      }

      if ($req->isPost() && $req->isAction()) {
        // blablabla
      }
    }
  }

  /**
   * Checks if the request should stop the router from resolving.
   *
   * @param Request $req  The request instance.
   *
   * @return bool
   */
  private static function shouldStop(Request $req): bool {
    return $req->isCLI()
      || $req->isXMLRPC()
      || $req->isAutoSave()
      || $req->isCRON()
      || $req->isREST()
      || $req->isAjax()
    ;
  }
}
