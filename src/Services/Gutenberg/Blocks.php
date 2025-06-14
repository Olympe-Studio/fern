<?php

declare(strict_types=1);

namespace Fern\Core\Services\Gutenberg;

use Exception;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
use Fern\Core\Logger\Logger;
use Fern\Core\Services\Views\Views;
use Fern\Core\Utils\JSON;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

/**
 * Gutenberg Blocks helper service.
 *
 * This service centralises registration, data handling and asset
 * management for Gutenberg blocks defined in the theme.
 * All the Astro-related rendering logic has been purposefully left out
 * to keep this class single-responsibility and framework-agnostic.
 */
final class Blocks extends Singleton {
  /**
   * Default relative path to the build manifest. Can be overridden via the
   * `fern:gutenberg:manifest_path` filter.
   */
  private const DEFAULT_MANIFEST_PATH = '/public/assets/assets-manifest.json';

  /**
   * Default cache/transient key for the manifest. Can be overridden via the
   * `fern:gutenberg:manifest_cache_key` filter.
   */
  private const DEFAULT_MANIFEST_CACHE_KEY = '_fern_blocks_manifest';

  /**
   * Registered per-block custom data store.
   *
   * @var array<string, array<string,mixed>>
   */
  protected array $dataStore = [];

  /**
   * Track blocks that already had their assets processed.
   * The key is the block path, the value is just a boolean flag.
   *
   * @var array<string,bool>
   */
  protected array $processedBlocks = [];

  /**
   * Queue of blocks that need asset processing.
   *
   * @var array<string>
   */
  protected array $queuedBlocks = [];

  /**
   * Final list of CSS / JS urls to enqueue on the front-end.
   *
   * @var array<string, array<string>> {css:[], js:[]}
   */
  protected array $assets = [
    'css' => [],
    'js'  => [],
  ];

  /**
   * Absolute path to the manifest file.
   */
  protected string $manifestPath;

  /**
   * Transient key used for caching the manifest.
   */
  protected string $cacheKey;

  /**
   * Cached manifest content.
   *
   * @var array<string,mixed>|null
   */
  protected ?array $manifest = null;

  public function __construct() {
    // Allow consumers to override where the build manifest is located.
    $defaultPath = untrailingslashit(Fern::getRoot()) . self::DEFAULT_MANIFEST_PATH;
    $this->manifestPath = Filters::apply('fern:gutenberg:manifest_path', $defaultPath);

    // Allow consumers to define their own cache key.
    $this->cacheKey = Filters::apply('fern:gutenberg:manifest_cache_key', self::DEFAULT_MANIFEST_CACHE_KEY);
  }

  /* --------------------------------------------------------------------- */
  /* Bootstrapping                                                         */
  /* --------------------------------------------------------------------- */

  /**
   * Register WP hooks to bootstrap the blocks logic.
   */
  public static function boot(): void {
    $self = self::getInstance();

    // Block categories.
    Filters::on('block_categories_all', [$self, 'addBlockCategories'], 10, 1);

    // Disable font library in editor.
    Filters::on('block_editor_settings_all', [$self, 'disableFontLibrary'], 10, 1);

    // Register blocks on init.
    Events::on('init', [$self, 'registerBlocks'], 10, 0);

    // Misc. filters.
    Filters::on('render_block', [$self, 'filterRenderBlock'], 10, 2);

    // Collect assets for active blocks & inject them into Timber context.
    Filters::on('render_block', [$self, 'collectAssetsOnRender'], 50, 3);
    Filters::on('fern:core:views:ctx', [$self, 'addAssetsToContext'], 50, 1);
  }

  /* --------------------------------------------------------------------- */
  /* Editor utilities                                                      */
  /* --------------------------------------------------------------------- */

  /**
   * Disable the Gutenberg font library.
   *
   * @param array<string,mixed> $settings Block editor settings.
   * @return array<string,mixed>
   */
  public function disableFontLibrary(array $settings): array {
    $settings['fontLibraryEnabled'] = false;

    return $settings;
  }

  /**
   * Add the custom block categories used by the theme.
   *
   * @param array<int, array<string,string>> $categories Existing categories.
   * @return array<int, array<string,string>>
   */
  public function addBlockCategories(array $categories): array {
    /**
     * Filter: fern:gutenberg:block_categories
     *
     * Allows themes/apps to inject additional Gutenberg block categories.
     *
     * @param array<int, array<string,string>> $categories Current list.
     */
    return Filters::apply('fern:gutenberg:block_categories', $categories);
  }

  /* --------------------------------------------------------------------- */
  /* Block registration                                                    */
  /* --------------------------------------------------------------------- */

  /**
   * Register all theme blocks.
   */
  public function registerBlocks(): void {
    // Base directory can be altered via filter.
    $basePath = untrailingslashit(
      Filters::apply(
        'fern:gutenberg:blocks_base_path',
        trailingslashit(Fern::getRoot()) . 'App/Blocks'
      )
    );

    /**
     * Filter: fern:gutenberg:blocks_register
     *
     * Return an array of block directories to register. Each entry can be
     *  – A path relative to the base directory defined above.
     *  – An absolute path to the block folder.
     *
     * @param array<int,string> $paths
     */
    $paths = Filters::apply('fern:gutenberg:blocks_register', []);

    foreach ($paths as $dir) {
      $dir = str_starts_with($dir, '/') ? $dir : trailingslashit($basePath) . ltrim($dir, '/');
      $this->registerBlockPath($dir);
    }
  }

  /**
   * Helper to register a block from its metadata folder.
   */
  protected function registerBlockPath(string $dir): void {
    $jsonPath = trailingslashit($dir) . 'block.json';

    if (!file_exists($jsonPath)) {
      return;
    }

    $jsonContent = file_get_contents($jsonPath);

    if ($jsonContent === false) {
      return;
    }

    // Fallback: parse JSON and register manually.
    $settings = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
    if (is_array($settings)) {
      $settings['render_callback'] = [self::class, 'renderBlock'];
      register_block_type($dir, $settings);
    }
  }

  /* --------------------------------------------------------------------- */
  /* Render helpers                                                        */
  /* --------------------------------------------------------------------- */

  /**
   * Inject custom CSS class to core/group blocks when rendered.
   *
   * @param string $html  Rendered HTML.
   * @param array<string,mixed> $block Block data.
   * @return string
   */
  public function filterRenderBlock(string $html, array $block): string {
    /**
     * Filter: fern:gutenberg:render_block:html
     *
     * Gives the application a chance to alter the rendered HTML before it is
     * returned to WordPress.
     */
    return Filters::apply('fern:gutenberg:render_block:html', $html, $block);
  }

  /* --------------------------------------------------------------------- */
  /* Public API – data helpers                                             */
  /* --------------------------------------------------------------------- */

  /**
   * Register custom data to pass to a block during render.
   *
   * @param array<string,mixed> $data
   */
  public function registerBlockData(string $name, array $data): void {
    $current = $this->dataStore[$name] ?? [];
    $this->dataStore[$name] = [
      ...$current,
      ...$data,
    ];
  }

  /**
   * Retrieve the data registered for a given block.
   *
   * @return array<string,mixed>
   */
  public function getBlockData(string $name): array {
    return $this->dataStore[$name] ?? [];
  }

  /**
   * Proxy helper hooked via Filters to register contextual data for a block before render.
   *
   * @param array<string,mixed> $data
   * @return array<string,mixed>
   */
  public static function preRenderBlock(string $name, array $data): array {
    self::getInstance()->registerBlockData($name, $data);

    return self::getInstance()->getBlockData($name);
  }

  /* --------------------------------------------------------------------- */
  /* Asset handling                                                        */
  /* --------------------------------------------------------------------- */

  /**
   * Queue a block so that its assets can later be collected via handleQueue().
   */
  public function queueBlock(string $name): void {
    $name = strtolower($name);

    if (!in_array($name, $this->queuedBlocks, true)) {
      $this->queuedBlocks[] = $name;
    }
  }

  /**
   * Process the queued blocks and return the consolidated asset list.
   *
   * @return array<string, array<string>>
   */
  public function handleQueue(): array {
    foreach ($this->queuedBlocks as $name) {
      $this->addBlockAssets($name);
    }

    return $this->assets;
  }

  /**
   * Add an asset URL to the collection.
   */
  public function addAssets(string $type, string $url): void {
    if (!in_array($type, ['css', 'js'], true)) {
      throw new Exception('Invalid asset type. Must be css or js.');
    }

    if (!in_array($url, $this->assets[$type], true)) {
      $this->assets[$type][] = $url;
    }
  }

  /**
   * Retrieve the collected assets list.
   *
   * @return array<string, array<string>>
   */
  public function getAssets(): array {
    return $this->assets;
  }

  /**
   * Return the manifest, loading it if needed.
   *
   * @return array<string,mixed>
   */
  public function getManifest(): array {
    if (is_null($this->manifest)) {
      $this->loadManifest();
    }

    return $this->manifest ?? [];
  }

  /**
   * Load the manifest from disk or cache.
   */
  protected function loadManifest(): void {
    $cached = get_transient($this->cacheKey);

    if ($cached !== false) {
      $this->manifest = $cached;

      return;
    }

    if (!file_exists($this->manifestPath)) {
      throw new Exception('Manifest file not found. Has Astro been built?');
    }

    $manifestContent = file_get_contents($this->manifestPath);

    if ($manifestContent === false) {
      throw new Exception('Unable to read manifest file.');
    }

    $this->manifest = JSON::decode($manifestContent, true);

    // Cache for 12 hours.
    set_transient($this->cacheKey, $this->manifest, 12 * HOUR_IN_SECONDS);
  }

  /**
   * Add CSS / JS assets for a given block based on the manifest.
   */
  public function addBlockAssets(string $blockName): void {
    $name     = strtolower($blockName);
    $path     = '/blocks/' . $name;
    $manifest = $this->getManifest();

    // Skip if already processed.
    if (isset($this->processedBlocks[$path])) {
      return;
    }

    if (!isset($manifest['pages'][$path])) {
      Logger::info('Block not found in manifest: ' . $path);
      return;
    }

    $blockData = $manifest['pages'][$path];
    $this->processedBlocks[$path] = true;

    $this->extractAssets($blockData['styles'] ?? [], 'css');
    $this->extractAssets($blockData['scripts'] ?? [], 'js');
  }

  /**
   * Extracts external assets of the given type and add them to the list.
   *
   * @param array<int, array<string,string>> $items
   */
  protected function extractAssets(array $items, string $type): void {
    // The first element is the shared asset, we skip it.
    if (count($items) <= 1) {
      return;
    }

    array_shift($items);

    foreach ($items as $item) {
      $key = $type === 'css' ? 'src' : 'value';

      if (($item['type'] ?? '') === 'external' && isset($item[$key])) {
        $this->addAssets($type, $item[$key]);
      }
    }
  }

  /* --------------------------------------------------------------------- */
  /* Misc helpers                                                          */
  /* --------------------------------------------------------------------- */

  /**
   * Retrieve the block name from Gutenberg attributes.
   *
   * @param array<string,mixed> $attributes
   */
  public static function getBlockName(array $attributes): string {
    if (!isset($attributes['view'])) {
      if (!isset($attributes['name'])) {
        return '';
      }

      $namespaced = (string) $attributes['name'];
      $parts      = array_map('ucfirst', explode('/', $namespaced));

      return count($parts) === 1 ? $parts[0] : $parts[1];
    }

    return (string) $attributes['view'];
  }

  /**
   * Hooked on `render_block` to queue assets for blocks actually present on the page.
   *
   * @param string                      $html  Original HTML.
   * @param array<string,mixed>         $block Block array.
   * @return string                     Unchanged HTML.
   */
  public function collectAssetsOnRender(string $html, array $block): string {
    if (Fern::isDev()) {
      return $html;
    }

    $blockName = $block['blockName'] ?? '';

    // Give apps a chance to skip / transform the collection logic.
    $shouldQueue = Filters::apply('fern:gutenberg:assets:should_queue', true, $blockName, $block);

    if (!$shouldQueue) {
      return $html;
    }

    $name = self::getBlockName($block['attrs'] ?? []);

    /** @var string $name */
    $name = Filters::apply('fern:gutenberg:assets:block_name', $name, $block);

    if ($name !== '') {
      $this->queueBlock($name);
    }

    return $html;
  }

  /**
   * Inject collected CSS/JS URLs into the Timber context so views can enqueue them.
   *
   * @param array<string,mixed> $ctx Existing context.
   * @return array<string,mixed>
   */
  public function addAssetsToContext(array $ctx): array {
    if (Fern::isDev()) {
      return $ctx;
    }

    $ctx['_assets'] = $this->handleQueue();

    return $ctx;
  }

  /* --------------------------------------------------------------------- */
  /* Rendering for ACF blocks                                             */
  /* --------------------------------------------------------------------- */

  /**
   * Default ACF render_callback referenced by block.json files. Outputs HTML.
   * Applications can completely override the rendering by hooking
   * `fern:gutenberg:render_block_override` and returning a non-null string.
   *
   * @param array<string,mixed>|null $blockData Data provided by ACF.
   */
  public static function renderBlock($blockData = null): void {
    if (!is_array($blockData)) {
      return;
    }

    $blockName = $blockData['name'] ?? '';
    // Run pre-render hook on-the-fly so consumers can inject extra data.
    $hookAttributes = [
      'blockName' => $blockName,
      'attrs'     => [
        'data' => $blockData['data'] ?? [],
      ],
    ];

    $preData = Filters::apply('fern:gutenberg:pre_render_block', [], $blockName, $hookAttributes);

    if (!empty($preData)) {
      $blockData['data'] = [
        ...($blockData['data'] ?? []),
        ...$preData,
      ];
    }

    $attrs = $blockData['data'] ?? [];

    // Always expose ACF field values under a `fields` key for consistency.
    if (!array_key_exists('fields', $attrs)) {
      $blockData['data'] = ['fields' => $attrs];
      $attrs = $blockData['data'];
    }

    // Derive view name using helper, but let apps override completely.
    $defaultView = ucfirst(self::getBlockName(['name' => $blockName] + $attrs));
    $view        = Filters::apply('fern:gutenberg:render_block_view', $defaultView, $blockData);

    /** @var string|null $html */
    $html = Filters::apply('fern:gutenberg:render_block_override', null, $defaultView, $blockData);

    $data = Filters::apply('fern:gutenberg:render_block_data', $attrs, $blockData, $view);

    if (!is_string($html)) {
      $html = Views::render($view, $data, true);
    }

    // Allow last-minute modification of produced HTML.
    $html = Filters::apply('fern:gutenberg:render_block_html', $html, $blockData);

    echo $html;
  }
}
