<?php

declare(strict_types=1);

namespace Fern\Core\Services\Controller;

use Fern\Core\Errors\ControllerRegistration;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use ReflectionClass;
use ReflectionMethod;
use Fern\Core\Fern;

/**
 * @phpstan-type ControllerRegistry array{
 *   view: array<string, class-string<Controller>>,
 *   admin: array<string, class-string<Controller>>,
 *   default: ?class-string<Controller>,
 *   _404: ?class-string<Controller>
 * }
 */
class ControllerResolver extends Singleton {
  /** @var string Prefix for controller handles to avoid problems with numeric values */
  private const PREFIX = 'c_';

  /** @var string Constant for view type controllers */
  private const TYPE_VIEW = 'view';

  /** @var string Constant for admin type controllers */
  private const TYPE_ADMIN = 'admin';

  /** @var string Constant for widget type controllers */
  private const TYPE_WIDGET = 'widget';

  /** @var string Constant for default type controller */
  private const TYPE_DEFAULT = 'default';

  /** @var string Constant for 404 type controller */
  private const TYPE_404 = '_404';

  /** @var ControllerRegistry */
  private array $controllers;

  /**
   * Cache of ReflectionClass instances for discovered controllers.
   *
   * @var array<string, ReflectionClass<object>>
   */
  private array $reflectionCache = [];

  /**
   * @var array<string, string> Controller type cache for production
   */
  private static array $controllerTypeCache = [];

  /** @var string Cache file name */
  private const CACHE_FILE_NAME = '_routes_cache.fern.php';

  /** @var string Cache file path */
  private string $cacheFilePath;

  /**
   * @var array<string, array{
   *   type: string,
   *   handle: string,
   *   traits: array<string>,
   *   actions: array<string>,
   *   file_path: string,
   *   file_mtime: int
   * }> Complete controller registry with actions
   */
  private static array $controllerRegistry = [];

  /**
   * @var bool Whether the registry has been loaded from cache file
   */
  private static bool $registryLoaded = false;

  public function __construct() {
    $this->controllers = [
      self::TYPE_VIEW => [],
      self::TYPE_ADMIN => [],
      self::TYPE_WIDGET => [],
      self::TYPE_DEFAULT => null,
      self::TYPE_404 => null,
    ];

    $this->cacheFilePath = Fern::getRoot() . '/App/' . self::CACHE_FILE_NAME;

    // Auto-load registry in BOTH development and production
    $this->autoLoadRegistry();
  }

  /**
   * Auto-load registry with optimized cache handling
   */
  private function autoLoadRegistry(): void {
    $cacheExists = file_exists($this->cacheFilePath);

    // In production, prefer cache loading over regeneration
    if (!Fern::isDev() && $cacheExists) {
      if ($this->loadRegistryFromCacheFile()) {
        return;
      }
    }

    // In development or when cache is missing/invalid
    if ($cacheExists && $this->isCacheFresh()) {
      if ($this->loadRegistryFromCacheFile()) {
        return;
      }
    }

    // Only regenerate when absolutely necessary
    $this->scanAndCacheControllers();
    $this->generateCacheFile();
  }

  /**
   * Check if cache is fresh (optimized for production)
   */
  private function isCacheFresh(): bool {

    if (!file_exists($this->cacheFilePath)) {
      return false;
    }

    // In production, trust the cache exists and is valid
    // This eliminates expensive filesystem scanning
    if (!Fern::isDev()) {
      return true;
    }

    $cacheTime = filemtime($this->cacheFilePath);
    if ($cacheTime === false) {
      return false;
    }

    // Only perform expensive file checking in development
    $controllerDir = Fern::getRoot() . '/App/Controllers';
    if (!is_dir($controllerDir)) {
      return true; // No controllers directory, cache is valid
    }

    // Use a more efficient approach: check only a few key files first
    $criticalFiles = [
      __FILE__, // ControllerResolver.php
      Fern::getRoot() . '/src/fern/src/Fern.php',
    ];

    foreach ($criticalFiles as $file) {
      if (file_exists($file) && filemtime($file) > $cacheTime) {
        return false; // Framework files changed
      }
    }

    // Only scan controller directory if critical files are unchanged
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($controllerDir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'php') {
        if ($file->getMTime() > $cacheTime) {
          return false; // Found newer controller file
        }
      }
    }

    return true;
  }

  /**
   * Scan and cache all controllers with their actions
   */
  private function scanAndCacheControllers(): void {
    self::$controllerRegistry = [];

    // Scan controller files directly from filesystem
    $controllerDir = Fern::getRoot() . '/App/Controllers';
    if (!is_dir($controllerDir)) {
      return; // No controllers directory
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($controllerDir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $fileCount = 0;
    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'php') {
        $this->processControllerFile($file->getPathname());
        $fileCount++;
      }
    }
  }

  /**
   * Process a controller file and extract class information
   */
  private function processControllerFile(string $filePath): void {
    // Load the controller file
    require_once $filePath;

    // Extract class name from file path
    $className = $this->extractClassNameFromFile($filePath);
    if (!$className) {
      return;
    }

    // Process the class if it exists
    if (class_exists($className)) {
      $this->processClassWithActions($className);
    }
  }

  /**
   * Extract class name from controller file path
   */
  private function extractClassNameFromFile(string $filePath): ?string {
    // Convert file path to namespace
    $relativePath = str_replace(Fern::getRoot() . '/App/Controllers/', '', $filePath);
    $relativePath = str_replace('.php', '', $relativePath);

    // Handle subdirectories (e.g., MyAccount/DashboardController.php)
    $namespaceParts = explode('/', $relativePath);
    $className = 'App\\Controllers\\' . implode('\\', $namespaceParts);

    return $className;
  }

  /**
   * Process class and extract all public actions
   */
  private function processClassWithActions(string $className): void {
    if (!class_exists($className)) {
      return;
    }

    $reflection = new ReflectionClass($className);

    // Early return if not a Controller
    if (!$reflection->implementsInterface(Controller::class)) {
      return;
    }

    /** @var ReflectionClass<Controller> $reflection */
    $this->validateControllerClass($reflection);

    $type = $this->determineControllerType($reflection);
    $handle = (string) $reflection->getProperty('handle')->getValue();

    // Extract all public actions (methods without "_" prefix)
    $actions = $this->extractControllerActions($reflection);

    // Get file information
    $filePath = $reflection->getFileName() ?: '';
    $fileMtime = $filePath ? filemtime($filePath) : 0;

    // Store complete controller info in registry
    self::$controllerRegistry[$className] = [
      'type' => $type,
      'handle' => $handle,
      'traits' => $reflection->getTraitNames(),
      'actions' => $actions,
      'file_path' => $filePath,
      'file_mtime' => $fileMtime ?: 0,
    ];

    $this->register($type, $handle, $className);
  }

  /**
   * Extract all public action methods from controller
   */
  private function extractControllerActions(ReflectionClass $reflection): array {
    $actions = [];
    $reservedMethods = ['handle', 'init', 'configure', '__construct', 'getInstance'];

    // Core framework classes that should not contribute action methods
    $excludedClasses = [
      'Fern\\Core\\Factory\\Singleton',
      'Fern\\Core\\Services\\Controller\\Controller',
    ];

    // Get ALL public methods from the controller (including inherited and from traits)
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      $methodName = $method->getName();

      // Skip reserved methods, magic methods, and methods starting with "_"
      if (
        in_array($methodName, $reservedMethods, true) ||
        str_starts_with($methodName, '__') ||
        str_starts_with($methodName, '_') ||
        $method->isStatic()
      ) {
        continue;
      }

      $declaringClass = $method->getDeclaringClass()->getName();

      // Exclude methods from core framework classes
      if (in_array($declaringClass, $excludedClasses, true)) {
        continue;
      }

      // Include all other public methods as potential actions
      // This includes methods from:
      // - The controller itself
      // - Parent classes like ViewController
      // - Traits like WooCartActions
      // - Any other classes in the inheritance chain
      $actions[] = $methodName;
    }

    sort($actions);
    return $actions;
  }

  /**
   * Generate cache file using the same pattern as Autoloader
   */
  private function generateCacheFile(): void {
    $appDir = dirname($this->cacheFilePath);
    if (!is_dir($appDir)) {
      wp_mkdir_p($appDir);
    }

    $content = $this->buildCacheFileContent();

    // Atomic write (same as Autoloader)
    file_put_contents($this->cacheFilePath, $content);
  }

  /**
   * Build cache file content similar to Autoloader format
   */
  private function buildCacheFileContent(): string {
    $timestamp = date('Y-m-d H:i:s');
    $version = Fern::getVersion();
    $controllerCount = count(self::$controllerRegistry);
    $time = time();

    // Start building the file content
    $content = <<<PHP
<?php
/**
 * Fern Framework Controller Routes Cache
 *
 * This file is auto-generated. DO NOT MODIFY.
 * Generated using the same pattern as includes.php
 *
 * Generated: {$timestamp}
 * Fern Version: {$version}
 * Controllers: {$controllerCount}
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return [
    'metadata' => [
        'generated_at' => '{$timestamp}',
        'timestamp' => {$time},
        'version' => '{$version}',
        'controller_count' => {$controllerCount},
    ],
    'controllers' => [

PHP;

    // Add each controller with proper formatting
    foreach (self::$controllerRegistry as $className => $info) {
      $actionsExport = $this->formatArrayForExport($info['actions'], 12);
      $traitsExport = $this->formatArrayForExport($info['traits'], 12);

      $content .= <<<PHP
        '{$className}' => [
            'type' => '{$info['type']}',
            'handle' => '{$info['handle']}',
            'traits' => {$traitsExport},
            'actions' => {$actionsExport},
            'file_path' => '{$info['file_path']}',
            'file_mtime' => {$info['file_mtime']},
        ],

PHP;
    }

    $content .= <<<PHP
    ],
];
PHP;

    return $content;
  }

  /**
   * Format array for export with proper indentation
   */
  private function formatArrayForExport(array $array, int $indent): string {
    if (empty($array)) {
      return '[]';
    }

    $indentStr = str_repeat(' ', $indent);
    $items = array_map(fn($item) => "'{$item}'", $array);

    if (count($items) === 1) {
      return "[{$items[0]}]";
    }

    return "[\n{$indentStr}    " . implode(",\n{$indentStr}    ", $items) . ",\n{$indentStr}]";
  }

  /**
   * Load registry from cache file
   */
  private function loadRegistryFromCacheFile(): bool {
    if (self::$registryLoaded) {
      return true;
    }

    if (!file_exists($this->cacheFilePath) || !is_readable($this->cacheFilePath)) {
      return false;
    }

    try {
      $cached = include $this->cacheFilePath;

      if (!is_array($cached) || !isset($cached['controllers']) || !isset($cached['metadata'])) {
        return false;
      }

      // Validate cache version
      if ($cached['metadata']['version'] !== Fern::getVersion()) {
        return false;
      }

      self::$controllerRegistry = $cached['controllers'];
      self::$registryLoaded = true;

      // Register controllers from cache
      $this->registerControllersFromCache();

      return true;
    } catch (\Throwable) {
      // Cache file corrupted, remove it safely
      if (file_exists($this->cacheFilePath) && is_writable($this->cacheFilePath)) {
        unlink($this->cacheFilePath);
      }
      return false;
    }
  }

  /**
   * Register controllers from cached data (optimized for production)
   */
  private function registerControllersFromCache(): void {
    // In production, trust the cache and skip class_exists checks
    // This eliminates expensive class loading verification
    if (!Fern::isDev()) {
      foreach (self::$controllerRegistry as $className => $info) {
        $this->register($info['type'], $info['handle'], $className);
      }
      return;
    }

    // In development, verify classes exist for cache integrity
    foreach (self::$controllerRegistry as $className => $info) {
      if (!class_exists($className)) {
        // Class no longer exists, cache is stale - remove cache file safely
        if (file_exists($this->cacheFilePath) && is_writable($this->cacheFilePath)) {
          unlink($this->cacheFilePath);
        }
        self::$registryLoaded = false;
        return;
      }

      $this->register($info['type'], $info['handle'], $className);
    }
  }

  /**
   * Boots the ControllerResolver (optimized for production)
   */
  public static function boot(): void {
    $instance = self::getInstance();

    // In production, controllers are already cached - skip expensive scanning
    // In development, only scan if registry is empty or incomplete
    if (Fern::isDev() && (empty(self::$controllerRegistry) || !self::$registryLoaded)) {
      $declaredClasses = get_declared_classes();
      foreach ($declaredClasses as $className) {
        $instance->processClass($className);
      }
    }

    Events::on('admin_menu', [$instance, 'registerAdminMenus'], 10, 0);
  }

  /**
   * Get all actions for a controller
   */
  public function getControllerActions(string $controllerClass): array {
    if (!Fern::isDev() && isset(self::$controllerRegistry[$controllerClass])) {
      return self::$controllerRegistry[$controllerClass]['actions'];
    }

    // Fallback to reflection in development
    if (!class_exists($controllerClass)) {
      return [];
    }

    $reflection = new ReflectionClass($controllerClass);
    return $this->extractControllerActions($reflection);
  }

  /**
   * Check if controller has a specific action
   */
  public function hasAction(string $controllerClass, string $actionName): bool {
    $actions = $this->getControllerActions($controllerClass);
    return in_array($actionName, $actions, true);
  }

  /**
   * Find controller that has a specific action
   */
  public function findControllerWithAction(string $actionName): ?string {
    foreach (self::$controllerRegistry as $controllerClass => $info) {
      if (in_array($actionName, $info['actions'], true)) {
        return $controllerClass;
      }
    }

    return null;
  }

  /**
   * Get cache statistics for debugging
   *
   * @return array{registry_loaded: bool, registry_size: int, cache_file_exists: bool, cache_file_path: string}
   */
  public function getCacheStats(): array {
    return [
      'registry_loaded' => self::$registryLoaded,
      'registry_size' => count(self::$controllerRegistry),
      'cache_file_exists' => file_exists($this->cacheFilePath),
      'cache_file_path' => $this->cacheFilePath,
      'runtime_cache_size' => count(self::$controllerTypeCache),
      'reflection_cache_size' => count($this->reflectionCache),
    ];
  }

  /**
   * Get the controller registry for external access (e.g., Autoloader)
   * 
   * @return array<string, array{type: string, handle: string, traits: array<string>, actions: array<string>, file_path: string, file_mtime: int}>
   */
  public function getControllerRegistry(): array {
    return self::$controllerRegistry;
  }

  /**
   * Invalidate controller cache by removing the cache file
   */
  public function invalidateCache(): void {
    if (file_exists($this->cacheFilePath) && is_writable($this->cacheFilePath)) {
      unlink($this->cacheFilePath);
    }

    self::$controllerRegistry = [];
    self::$registryLoaded = false;

    // Clear runtime caches as well
    $this->reflectionCache = [];
    self::$controllerTypeCache = [];
  }

  /**
   * Register all admin menus from controllers
   */
  public function registerAdminMenus(): void {
    /** @var array<string, class-string<Controller>> $adminControllers */
    $adminControllers = $this->controllers[self::TYPE_ADMIN];

    foreach ($adminControllers as $controller) {
      $this->registerAdminMenu($controller);
    }
  }

  /**
   * Processes a single class to determine if it's a valid controller and registers it if so.
   *
   * @param class-string $className
   */
  public function processClass(string $className): void {
    if (!class_exists($className)) {
      return;
    }

    // Check if already cached
    if (isset($this->reflectionCache[$className])) {
      $reflection = $this->reflectionCache[$className];
    } else {
      $reflection = new ReflectionClass($className);

      // Early return if not a Controller
      if (!$reflection->implementsInterface(Controller::class)) {
        return;
      }

      /** @var ReflectionClass<object> $ref */
      $ref = $reflection;
      $this->reflectionCache[$className] = $ref;
    }

    /** @var ReflectionClass<Controller> $reflection */
    $this->validateControllerClass($reflection);

    $type = $this->determineControllerType($reflection);
    $handle = (string) $reflection->getProperty('handle')->getValue();

    $this->register($type, $handle, $reflection->getName());
  }

  /**
   * Register a controller.
   *
   * @param string                   $type       The type of the controller (view, admin, or default)
   * @param string                   $handle     The handle of the controller
   * @param class-string<Controller> $controller
   */
  public function register(string $type, string $handle, string $controller): void {
    if ($type === self::TYPE_DEFAULT) {
      /** @var class-string<Controller> $controller */
      $this->controllers[self::TYPE_DEFAULT] = $controller;

      return;
    }

    if ($type === self::TYPE_404) {
      /** @var class-string<Controller> $controller */
      $this->controllers[self::TYPE_404] = $controller;

      return;
    }

    if ($type === self::TYPE_VIEW || $type === self::TYPE_ADMIN) {
      $handle = self::PREFIX . $handle;
      /** @var array<string, class-string<Controller>> $typeControllers */
      $typeControllers = $this->controllers[$type];
      $typeControllers[$handle] = $controller;
      $this->controllers[$type] = $typeControllers;
    }
  }

  /**
   * Resolve a controller by its class name.
   *
   * @param string $type   The type of the controller (view, admin, or default)
   * @param string $handle The handle of the controller
   */
  public function resolve(string $type, string $handle): string|null {

    $handle = Filters::apply('fern:core:controller_resolve', $handle, $type);
    $handle = self::PREFIX . $handle;

    $controllerClass = $this->controllers[$type][$handle] ?? null;

    // Optimize controller loading: only check class existence in development
    if ($controllerClass) {
      if (Fern::isDev() && !class_exists($controllerClass, false)) {
        \Fern\Core\Utils\Autoloader::loadController($controllerClass);
      } elseif (!Fern::isDev()) {
        // In production, trust the autoloader and load if needed
        \Fern\Core\Utils\Autoloader::loadController($controllerClass);
      }
    }

    return $controllerClass;
  }

  /**
   * Get the default controller.
   */
  public function getDefaultController(): string|null {

    $default = $this->controllers[self::TYPE_DEFAULT];

    if (!$default) {
      throw new ControllerRegistration('No default controller registered. Please register a default controller in  /App/Controller with handle set to `_default`.');
    }

    // Optimize controller loading: only check class existence in development
    if (Fern::isDev() && !class_exists($default, false)) {
      \Fern\Core\Utils\Autoloader::loadController($default);
    } elseif (!Fern::isDev()) {
      // In production, trust the autoloader
      \Fern\Core\Utils\Autoloader::loadController($default);
    }

    return $default;
  }

  /**
   * Get the 404 controller.
   */
  public function get404Controller(): string|null {

    $notFound = $this->controllers[self::TYPE_404];

    if (!$notFound) {
      throw new ControllerRegistration('No NotFound controller registered. Please register a 404 controller in  /App/Controller with handle set to `_404`.');
    }

    // Optimize controller loading: only check class existence in development
    if (Fern::isDev() && !class_exists($notFound, false)) {
      \Fern\Core\Utils\Autoloader::loadController($notFound);
    } elseif (!Fern::isDev()) {
      // In production, trust the autoloader
      \Fern\Core\Utils\Autoloader::loadController($notFound);
    }

    return $notFound;
  }

  /**
   * Register admin menu for a specific controller
   *
   * @param class-string $controllerClass
   */
  private function registerAdminMenu(string $controllerClass): void {
    $controller = $controllerClass::getInstance();

    if (!method_exists($controller, 'configure')) {
      return;
    }

    /** @phpstan-ignore-next-line */
    $config = $controller->configure();

    // Validate required configuration
    if (!isset($config['page_title']) || !isset($config['menu_title'])) {
      throw new ControllerRegistration("Admin controller {$controllerClass} must provide 'page_title' and 'menu_title' in configure()");
    }

    // Set default values for optional parameters
    $defaults = [
      'capability' => 'manage_options',
      'menu_slug' => '',
      'icon' => '',
      'position' => null,
      'parent_slug' => null,
    ];

    $config = array_merge($defaults, $config);

    // Override the menu slug with the controller's handle
    $reflection = new ReflectionClass($controllerClass);
    $handle = $reflection->getProperty('handle')->getValue();
    $config['menu_slug'] = $handle;

    // Override the callback with the controller's handle
    $callback = function () use ($controllerClass): void {
      $controller = $controllerClass::getInstance();
      $reply = $controller->handle(Request::getCurrent());
      $reply->send();
    };

    // Register the menu based on whether it's a submenu or top-level menu
    if ($config['parent_slug']) {
      add_submenu_page(
        $config['parent_slug'],
        $config['page_title'],
        $config['menu_title'],
        $config['capability'],
        $config['menu_slug'],
        $callback,
      );
    } else {
      add_menu_page(
        $config['page_title'],
        $config['menu_title'],
        $config['capability'],
        $config['menu_slug'],
        $callback,
        $config['icon'],
        $config['position'],
      );

      // If there are submenu items defined, register them
      if (isset($config['submenu']) && is_array($config['submenu'])) {
        foreach ($config['submenu'] as $submenu) {
          // Ensure required submenu fields are present
          if (!isset($submenu['page_title']) || !isset($submenu['menu_title'])) {
            continue;
          }

          $submenu = array_merge([
            'capability' => $config['capability'],
            'menu_slug' => '',
            'callback' => $callback,
          ], $submenu);

          add_submenu_page(
            $config['menu_slug'],
            $submenu['page_title'],
            $submenu['menu_title'],
            $submenu['capability'],
            $submenu['menu_slug'],
            $submenu['callback'],
          );
        }
      }
    }
  }

  /**
   * Validates that a controller class has the required 'handle' property.
   *
   * @param ReflectionClass<Controller> $reflection The reflection class instance
   *
   * @throws ControllerRegistration<Controller> if the class doesn't meet the requirements
   */
  private function validateControllerClass(ReflectionClass $reflection): void {
    $className = $reflection->getName();

    if (!$reflection->hasProperty('handle') || !$reflection->getProperty('handle')->isPublic() || !$reflection->getProperty('handle')->isStatic()) {
      throw new ControllerRegistration("Controller {$className} must have a static public `handle` property.");
    }

    if (!$reflection->isSubclassOf(Singleton::class)) {
      throw new ControllerRegistration("Controller {$className} must extend \Fern\Core\Factory\Singleton class.");
    }
  }

  /**
   * Determines the type of a controller based on its properties.
   *
   * @param ReflectionClass<Controller> $reflection The reflection class instance
   *
   * @return string The determined controller type
   */
  private function determineControllerType(ReflectionClass $reflection): string {
    $className = $reflection->getName();

    // Fast path for production with type cache
    if (!Fern::isDev() && isset(self::$controllerTypeCache[$className])) {
      return self::$controllerTypeCache[$className];
    }

    $handleProperty = $reflection->getProperty('handle');
    $handleProperty->setAccessible(true);
    $handleValue = $handleProperty->getValue();

    if ($handleValue === '_default') {
      $type = self::TYPE_DEFAULT;
    } elseif ($handleValue === '_404') {
      $type = self::TYPE_404;
    } else {
      $traits = $reflection->getTraitNames();
      $type = in_array('Fern\Core\Services\Controller\AdminController', $traits, true)
        ? self::TYPE_ADMIN
        : self::TYPE_VIEW;
    }

    // Cache result in production
    if (!Fern::isDev()) {
      self::$controllerTypeCache[$className] = $type;
    }

    return $type;
  }
}
