<?php

declare(strict_types=1);

namespace Fern\Core\CLI;

use Fern\Core\Fern;
use WP_CLI;
use WP_Error;

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
   * [--create-page]
   * : Whether to create a new page when handle is 'page'.
   *
   * [--light]
   * : Use the light controller template instead of the default one.
   *
   * ## EXAMPLES
   *
   *     wp fern:controller create MyNewController
   *     wp fern:controller create AdminDashboard --handle=dashboard
   *
   * @when after_wp_load
   *
   * @param array<int, string>    $args      Positional arguments (name, handle)
   * @param array<string, string> $assocArgs Associative arguments (--subdir)
   */
  public function create(array $args, array $assocArgs): void {
    if (count($args) !== 2) {
      WP_CLI::error('This command requires exactly two arguments: <name> and <handle>.');

      return;
    }

    list($name, $handle) = $args;
    $name = $this->cleanControllerName($name);

    if ($handle === 'page') {
      $handle = $this->handlePageCreation($name, $assocArgs);

      if ($handle === false) {
        return;
      }
    }

    $subdir = isset($assocArgs['subdir']) ? ucfirst($assocArgs['subdir']) : '';
    $templateFile = isset($assocArgs['light']) ? 'LightController.php' : 'Controller.php';
    $templatePath = Fern::getRoot() . '/fern/src/CLI/templates/' . $templateFile;

    if (!file_exists($templatePath)) {
      WP_CLI::error("Template file not found at {$templatePath}");
      exit;
    }

    $templateContent = file_get_contents($templatePath);

    if (!$templateContent) {
      WP_CLI::error('Failed to read template file.');
      exit;
    }

    $namespace = 'App\\Controllers' . (empty($subdir) ? '' : '\\' . $subdir);
    $templateContent = str_replace('namespace App\Controllers\Subdir;', "namespace {$namespace};", $templateContent);
    $controllerContent = str_replace('NameController', ucfirst($name) . 'Controller', $templateContent);
    $controllerContent = str_replace('NameView', ucfirst($name), $controllerContent);
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
    $outputFile = $outputDir . $name . 'Controller.php';

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

  /**
   * Cleans the controller name by removing 'Controller' variations
   */
  private function cleanControllerName(string $name): string {
    $variations = ['Controller', 'controller', 'CONTROLLER'];

    foreach ($variations as $suffix) {
      if (str_ends_with($name, $suffix)) {
        $name = substr($name, 0, -strlen($suffix));
      }
    }

    return $name;
  }

  /**
   * Handles page creation when handle is 'page'
   *
   * @param array<string, string> $assocArgs
   *
   * @return string|false Returns the page ID if successful, false otherwise
   */
  private function handlePageCreation(string $name, array $assocArgs): string|false {
    if (!isset($assocArgs['create-page'])) {
      $createPage = WP_CLI::confirm('Would you like to create a new page and assign its ID to the controller?');
    } else {
      $createPage = true;
    }

    if ($createPage) {
      $pageArgs = [
        'post_type' => 'page',
        'post_title' => $name,
        'post_status' => 'publish',
      ];

      /** @var int|WP_Error $pageId */
      $pageId = wp_insert_post($pageArgs);

      if ($pageId instanceof WP_Error) {
        WP_CLI::error('Failed to create the page: ' . $pageId->get_error_message());

        return false;
      }

      WP_CLI::success("Page '{$name}' created with ID: {$pageId}");
      WP_CLI::success('You can edit the page at : ' . get_edit_post_link($pageId));

      return (string) $pageId;
    }

    return 'page';
  }
}
