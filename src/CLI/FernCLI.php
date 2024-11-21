<?php declare(strict_types=1);

namespace Fern\Core\CLI;

use RuntimeException;
use WP_CLI;

class FernCLI {
  public function __construct() {
    WP_CLI::add_command('fern:controller', FernControllerCommand::class);
  }

  /**
   * Boot the CLI
   */
  public static function boot(): FernCLI {
    if (!defined('WP_CLI') || !constant('WP_CLI')) {
      throw new RuntimeException('WP CLI is not available.');
    }

    return new self();
  }
}
