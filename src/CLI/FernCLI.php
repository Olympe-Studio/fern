<?php

namespace Fern\Core\CLI;


class FernCLI {
  public function __construct() {
    WP_CLI::add_command('fern:controller', FernControllerCommand::class);
  }

  /**
   * Boot the CLI
   *
   * @return FernCLI
   */
  public static function boot(): FernCLI {
    if (!defined('WP_CLI') || !WP_CLI) {
      throw new \RuntimeException('WP CLI is not available.');
    }

    return new self();
  }
}
