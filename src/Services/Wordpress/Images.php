<?php

declare(strict_types=1);

namespace Fern\Core\Services\Wordpress;

use Fern\Core\Config;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

/**
 * Images Service for WordPress
 *
 * This class manages image processing settings in WordPress based on the provided configuration.
 * It can disable various image processing features or set up custom image sizes.
 *
 * @package Fern\Core\Services\Wordpress
 */
class Images {
  /** @var array The configuration for image processing */
  protected array $config;
  /** @var int The default JPEG quality is 100 to prevent lossy compression */
  protected const DEFAULT_JPEG_QUALITY = 100;


  /**
   * Images constructor.
   *
   * @param array $config The configuration array for image processing
   */
  public function __construct(array $config) {
    $this->config = $config;
    $this->validateConfig();
    $this->init();
  }

  /**
   * Boot the Images service
   *
   * @return void
   */
  public static function boot(): void {
    $config = Config::get('core.images');
    new self($config);
  }

  /**
   * Initialize the Images service
   *
   * This method determines whether to disable image processing or set up custom image sizes based on the configuration.
   *
   * @return void
   */
  protected function init(): void {
    if ($this->config['disabled']) {
      $this->disableImageProcessing();
    } else {
      $this->setupCustomImageSizes();
    }
  }

  /**
   * Validate the configuration
   *
   * @return void
   */
  protected function validateConfig(): void {
    if (!is_bool($this->config['disabled'])) {
      throw new \InvalidArgumentException("The 'disabled' option must be a boolean.");
    }

    if (isset($this->config['settings']['jpegQuality'])) {
      $quality = $this->config['settings']['jpegQuality'];
      if (!is_int($quality) || $quality < 0 || $quality > 100) {
        throw new \InvalidArgumentException("JPEG quality must be an integer between 0 and 100.");
      }
    }
  }

  /**
   * Disable various image processing features
   *
   * This method adds filters to disable different aspects of WordPress image processing.
   *
   * @return void
   */
  protected function disableImageProcessing(): void {
    Filters::add('intermediate_image_sizes_advanced', [$this, 'disableImageSizes']);
    Filters::add('big_image_size_threshold', '__return_false');
    Filters::add('init', [$this, 'disableOtherImageSizes']);
    Filters::add('wp_image_editors', [$this, 'disableImageEditing']);
    Filters::add('intermediate_image_sizes_advanced', [$this, 'removeDefaultImageSizes']);
    Filters::add('jpeg_quality', [$this, 'setJpegQuality']);
    Filters::add('max_srcset_image_width', [$this, 'disableResponsiveImages']);
    Filters::add('wp_generate_attachment_metadata', [$this, 'preventImageResizesOnUpload'], 10, 2);
  }

  /**
   * Set up custom image sizes
   *
   * This method adds actions and filters to set up custom image sizes if they are defined in the configuration.
   *
   * @return void
   */
  protected function setupCustomImageSizes(): void {
    if (!empty($this->config['settings']['customSizes'])) {
      Events::addHandlers('after_setup_theme', [$this, 'addCustomImageSizes']);
      Filters::add('image_size_names_choose', [$this, 'addCustomImageSizesToEditor']);
    }
  }

  /**
   * Disable generation of intermediate image sizes
   *
   * @return array An empty array to prevent generation of intermediate image sizes
   */
  public function disableImageSizes(): array {
    return [];
  }

  /**
   * Disable other specific image sizes
   *
   * This method removes the '1536x1536' and '2048x2048' image sizes.
   *
   * @return void
   */
  public function disableOtherImageSizes(): void {
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');
  }

  /**
   * Disable image editing
   *
   * @return array An empty array to disable image editing
   */
  public function disableImageEditing(): array {
    return [];
  }

  /**
   * Remove default image sizes
   *
   * This method removes the 'thumbnail', 'medium', 'medium_large', and 'large' image sizes.
   *
   * @param array $sizes The current image sizes
   * @return array The modified image sizes array
   */
  public function removeDefaultImageSizes(array $sizes): array {
    unset($sizes['thumbnail']);
    unset($sizes['medium']);
    unset($sizes['medium_large']);
    unset($sizes['large']);
    return $sizes;
  }

  /**
   * Set JPEG quality
   *
   * @return int The JPEG quality setting from the configuration, or 100 if not set
   */
  public function setJpegQuality(): int {
    return $this->config['settings']['jpegQuality'] ?? self::DEFAULT_JPEG_QUALITY;
  }

  /**
   * Disable responsive images
   *
   * @return int Returns 1 to disable responsive images
   */
  public function disableResponsiveImages(): int {
    return 1;
  }

  /**
   * Prevent image resizes on upload
   *
   * This method empties the 'sizes' array in the attachment metadata to prevent resizes.
   *
   * @param array $metadata The attachment metadata
   * @return array The modified metadata
   */
  public function preventImageResizesOnUpload(array $metadata): array {
    $metadata['sizes'] = [];

    return $metadata;
  }

  /**
   * Add custom image sizes
   *
   * This method adds the custom image sizes defined in the configuration.
   *
   * @return void
   */
  public function addCustomImageSizes(): void {
    if (!isset($this->config['settings']['customSizes'])) {
      return;
    }

    foreach ($this->config['settings']['customSizes'] as $name => $size) {
      if (!isset($size['width'], $size['height'])) {
        throw new \InvalidArgumentException("Invalid custom size configuration for '$name', both width and height must be set.");
      }

      add_image_size($name, $size['width'], $size['height'], $size['crop'] ?? false);
    }
  }

  /**
   * Add custom image sizes to the editor
   *
   * This method adds the custom image sizes to the list of available sizes in the WordPress editor.
   *
   * @param array $sizes The current list of image sizes
   * @return array The modified list of image sizes including custom sizes
   */
  public function addCustomImageSizesToEditor(array $sizes): array {
    foreach ($this->config['settings']['customSizes'] as $name => $size) {
      if (!isset($size['width'], $size['height'])) {
        throw new \InvalidArgumentException("Invalid custom size configuration for '$name', both width and height must be set.");
      }

      $sizes[$name] = $size['label'] ?? ucfirst(str_replace('_', ' ', $name));
    }

    return $sizes;
  }
}
