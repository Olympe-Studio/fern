<?php declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

/**
 * An admin controller whose configure() omits the required keys, so menu
 * registration must throw.
 */
class InvalidConfigAdminController extends Singleton implements Controller {
  use AdminController;

  public static string $handle = 'invalid-admin';

  public function handle(Request $request): Reply {
    return new Reply(200, '');
  }

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return [];
  }
}
