<?php

namespace Fern\Core\Services\Controller;

trait AdminController {

  /**
   * Checks if the current controller is an admin controller
   *
   * @return bool
   */
  public function __isAdminController(): bool {
    return true;
  }

  /**
   * Configure the admin_menu_page.
   *
   * Please note the callback will be overriden to the handle method of the controller.
   *
   * @return array
   */
  abstract public function configure(): array;
}
