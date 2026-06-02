<?php declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

/**
 * A top-level admin controller used to assert add_menu_page registration.
 */
class TopLevelAdminController extends Singleton implements Controller {
  use AdminController;

  public static string $handle = 'top-admin';

  public function handle(Request $request): Reply {
    return new Reply(200, '');
  }

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return [
      'page_title' => 'Top Page',
      'menu_title' => 'Top Menu',
      'icon' => 'dashicons-admin',
    ];
  }
}
