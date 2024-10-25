<?php declare(strict_types=1);

namespace App\Controllers\Subdir;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class NameController extends Singleton implements Controller {
  public static string $handle = 'id_or_post_type_or_taxonomy';

  /**
   * Handle the request and return a reply.
   *
   * @param Request $request The request
   */
  public function handle(Request $request): Reply {
    return new Reply(200, Views::render('NameView', []));
  }
}
