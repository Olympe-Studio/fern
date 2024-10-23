<?php declare(strict_types=1);

namespace Fern\Core\Services\Controller;

trait AdminController {
  /**
   * Checks if the current controller is an admin controller
   */
  public function __isAdminController(): bool {
    return true;
  }

  /**
   * Configure the admin_menu_page.
   *
   * Please note the callback will be overriden to the handle method of the controller.
   */
  abstract public function configure(): array;
}
