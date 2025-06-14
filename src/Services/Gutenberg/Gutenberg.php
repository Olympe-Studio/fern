<?php

declare(strict_types=1);

namespace Fern\Core\Services\Gutenberg;

use Fern\Core\Config;
use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Wordpress\Filters;
use WP_Block_Type;
use WP_Block_Type_Registry;
use Fern\Core\Services\Gutenberg\Blocks;

class Gutenberg extends Singleton {
  /**
   * @var array<string|int>
   */ protected $showOnPostTypes;

  /**
   * @var array<string|int, string>
   */
  protected $blockExclude;

  /**
   * @var array<string>|null
   */
  protected $blockInclude;

  public function __construct() {
    $config = Config::get('theme.gutenberg') ?: [];
    $this->showOnPostTypes = $config['show_on_post_types'] ?? [];
    $this->blockExclude = $config['core_block_exclude'] ?? [];
    $this->blockInclude = $config['core_block_include'] ?? null;
  }

  /**
   * Boot the Gutenberg service
   */
  public static function boot(): void {
    $instance = self::getInstance();
    $req = Request::getInstance();

    // Initialise block related hooks on every request.
    Blocks::boot();

    if ($req->isAdmin()) {
      Filters::on('use_block_editor_for_post_type', [$instance, 'explicitlyShowOnPostTypes'], 10, 2);
      Filters::on('allowed_block_types_all', [$instance, 'filterAllowedBlocks'], 2, 1);
    }
  }

  /**
   * Get the post types explicitly shown on
   *
   * @param bool   $_  Unused parameter (block editor state)
   * @param string $pt The post type to check
   */
  public function explicitlyShowOnPostTypes(bool $_, string $pt): bool {
    global $post;

    if ($pt === 'page') {
      if (!$post) {
        return false;
      }

      $postId = (int) $post->ID;
      $pageIds = array_filter($this->showOnPostTypes, function ($value) {
        return is_numeric($value) && (int) $value > 0;
      });

      return in_array((string)$postId, $pageIds, true);
    }

    return in_array($pt, $this->showOnPostTypes, true);
  }

  /**
   * Filter the allowed blocks based on include/exclude configuration
   *
   * @param bool|array<string> $allowedBlocks Allowed blocks array or boolean
   *
   * @return bool|array<string>
   */
  public function filterAllowedBlocks($allowedBlocks): array|bool {
    // If core_block_include is defined, only allow those blocks
    if (is_array($this->blockInclude)) {
      return $this->blockInclude;
    }

    // If core_block_exclude is defined, get all blocks and remove excluded ones
    if (!empty($this->blockExclude)) {
      /** @var array<string, WP_Block_Type> */
      $registeredBlocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

      $filteredBlocks = [];

      foreach (array_keys($registeredBlocks) as $blockName) {
        $shouldExclude = false;

        foreach ($this->blockExclude as $key => $pattern) {
          // If key is numeric, treat as direct block/namespace pattern
          if (is_numeric($key)) {
            if ($this->matchesNamespacePattern($blockName, $pattern)) {
              $shouldExclude = true;
              break;
            }
          }
          // If key is string, treat as category exclusion
          else {
            $blockCategory = $this->getBlockCategory($blockName);

            if ($blockCategory === $key && $this->matchesNamespacePattern($blockName, $pattern)) {
              $shouldExclude = true;
              break;
            }
          }
        }

        if (!$shouldExclude) {
          $filteredBlocks[] = $blockName;
        }
      }

      return $filteredBlocks;
    }

    return $allowedBlocks;
  }

  /**
   * Checks if a block matches a namespace pattern
   *
   * @param string $blockName The block name to check
   * @param string $pattern   The pattern to match against
   */
  protected function matchesNamespacePattern(string $blockName, string $pattern): bool {
    if (substr($pattern, -2) === '/*') {
      $namespace = substr($pattern, 0, -2);

      return str_starts_with($blockName, $namespace);
    }

    return $pattern === $blockName;
  }

  /**
   * Get block category
   *
   * @param string $blockName The block name
   */
  protected function getBlockCategory(string $blockName): ?string {
    $registry = WP_Block_Type_Registry::get_instance();
    $block = $registry->get_registered($blockName);

    if ($block && isset($block->category)) {
      return $block->category;
    }

    return null;
  }
}
