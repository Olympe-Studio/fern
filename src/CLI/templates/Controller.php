<?php declare(strict_types=1);

namespace App\Controllers\Subdir;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

class NameController extends Singleton implements Controller {
  public static string $handle = 'id_or_post_type_or_taxonomy';

  /**
   * Handle the request and return a reply.
   *
   * @param Request $request The request
   */
  public function handle(Request $request): Reply {
    return new Reply(200, 'Hello, world!');
  }

  /**
   * An exemple of an action that say Hello World.
   *
   * @param Request $request The request
   *
   * @see https://fern.dev/actions
   */
  public function sayHelloWorld(Request $request): Reply {
    $action = $request->getAction();
    $greeting = $action->get('greeting');

    return new Reply(200, "Hello, {$greeting}!");
  }

  /**
   * An exemple of an action that is only available to users with the manage_options capability.
   *
   * @param Request $request The request
   *
   * @see https://fern.dev/attributes/require-capabilities
   */
  #[RequireCapabilities(['manage_options'])]
  public function optionsManagerOnlyAction(Request $request): Reply {
    return new Reply(200, 'Hello, Options Manager!');
  }
}
