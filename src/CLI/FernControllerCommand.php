<?php declare(strict_types=1);

namespace Fern\Core\CLI;

use Fern\Core\Fern;
use WP_CLI;

class FernControllerCommand {
  /**
   * Creates a new controller.
   *
   * ## OPTIONS
   *
   * <name>
   * : The name of the controller class.
   *
   * <handle>
   * : The handle of the controller, can be a post type, a taxonomy or an ID.
   *
   * [--subdir=<subdir>]
   * : Optional subdirectory to place the controller in.
   *
   * ## EXAMPLES
   *
   *     wp fern:controller create MyNewController
   *     wp fern:controller create AdminDashboard --handle=dashboard
   *
   * @when after_wp_load
   */
  public function create($args, $assocArgs) {
    if (count($args) !== 2) {
      WP_CLI::error('This command requires exactly two arguments: <name> and <handle>.');

      return;
    }

    list($name, $handle) = $args;
    $subdir = isset($assocArgs['subdir']) ? ucfirst($assocArgs['subdir']) : '';

    $templatePath = __DIR__ . '/templates/Controller.php';

    if (!file_exists($templatePath)) {
      WP_CLI::error("Template file not found at {$templatePath}");
    }

    $templateContent = file_get_contents($templatePath);
    $namespace = 'App\\Controllers' . (empty($subdir) ? '' : '\\' . $subdir);
    $templateContent = str_replace('namespace App\Controllers\Subdir;', "namespace {$namespace};", $templateContent);
    $controllerContent = str_replace('NameController', ucfirst($name) . 'Controller', $templateContent);
    $controllerContent = str_replace('id_or_post_type_or_taxonomy', $handle, $controllerContent);

    // Determine the output directory based on the type
    $outputDir = trailingslashit(Fern::getRoot()) . 'App/Controllers/';

    if (!empty($subdir)) {
      $outputDir .= trailingslashit($subdir);
    }

    // Ensure the output directory exists
    if (!is_dir($outputDir)) {
      mkdir($outputDir, 0755, true);
    }

    // Generate the output file path
    $outputFile = $outputDir . $name . '.php';

    // Check if the file already exists
    if (file_exists($outputFile)) {
      WP_CLI::error("A controller named {$name} already exists.");
    }

    // Write the new controller file
    if (file_put_contents($outputFile, $controllerContent) === false) {
      WP_CLI::error('Failed to create controller file.');
    }

    WP_CLI::success("Controller {$name} created successfully in " . realpath($outputFile));
  }
}
