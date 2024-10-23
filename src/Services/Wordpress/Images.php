<?php

declare(strict_types=1);

namespace Fern\Core\Services\Wordpress;

use Fern\Core\Config;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use InvalidArgumentException;

/**
 * Images Service for WordPress
 *
 * This class manages image processing settings in WordPress based on the provided configuration.
 * It can disable various image processing features or set up custom image sizes.
 *
 * @package Fern\Core\Services\Wordpress
 *
 * @phpstan-type ImageSize array{width: int, height: int, crop?: bool, label?: string}
 * @phpstan-type ImageSettings array{
 *     disable_image_sizes: bool,
 *     disable_other_image_sizes: bool,
 *     disable_image_editing: bool,
 *     remove_default_image_sizes: bool,
 *     disable_responsive_images: bool,
 *     prevent_image_resizes_on_upload: bool,
 *     jpeg_quality: int,
 *     custom_sizes: array<string, ImageSize>
 * }
 * @phpstan-type ImagesConfig array{
 *     disabled: bool,
 *     settings: ImageSettings
 * }
 */
class Images {
  /** @var int The default JPEG quality */
  protected const DEFAULT_JPEG_QUALITY = 100;

  /** @phpstan-var ImageSettings Default settings when not specified */
  protected const DEFAULT_SETTINGS = [
    'disable_image_sizes' => false,
    'disable_other_image_sizes' => false,
    'disable_image_editing' => false,
    'remove_default_image_sizes' => false,
    'disable_responsive_images' => false,
    'prevent_image_resizes_on_upload' => false,
    'jpeg_quality' => self::DEFAULT_JPEG_QUALITY,
    'custom_sizes' => [],
  ];

  /** @phpstan-var ImagesConfig The configuration for image processing */
  protected array $config;

  /**
   * Images constructor.
   *
   * @param ImagesConfig $config The configuration array for image processing
   */
  public function __construct(array $config) {
    $this->config = $this->normalizeConfig($config);
    $this->validateConfig();
    $this->init();
  }

  /**
   * Boot the Images service
   */
  public static function boot(): void {
    $config = Config::get('core.images');
    new self($config);
  }

  /**
   * Disable generation of intermediate image sizes
   *
   * @return array<string> An empty array to prevent generation of intermediate image sizes
   */
  public function disableImageSizes(): array {
    return [];
  }

  /**
   * Disable other specific image sizes
   *
   * This method removes the '1536x1536' and '2048x2048' image sizes.
   */
  public function disableOtherImageSizes(): void {
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');
  }

  /**
   * Disable image editing
   *
   * @return array<string> An empty array to disable image editing
   */
  public function disableImageEditing(): array {
    return [];
  }

  /**
   * Remove default image sizes
   *
   * This method removes the 'thumbnail', 'medium', 'medium_large', and 'large' image sizes.
   *
   * @param array<string, mixed> $sizes The current image sizes
   *
   * @return array<string, mixed> The modified image sizes array
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
    return $this->config['settings']['jpeg_quality'] ?? self::DEFAULT_JPEG_QUALITY;
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
   * @param array<string, mixed> $metadata The attachment metadata
   *
   * @return array<string, mixed> The modified metadata
   */
  public function preventImageResizesOnUpload(array $metadata): array {
    $metadata['sizes'] = [];

    return $metadata;
  }

  /**
   * Add custom image sizes
   *
   * This method adds the custom image sizes defined in the configuration.
   */
  public function addCustomImageSizes(): void {
    if (!isset($this->config['settings']['custom_sizes'])) {
      return;
    }

    foreach ($this->config['settings']['custom_sizes'] as $name => $size) {
      if (!isset($size['width'], $size['height'])) {
        throw new InvalidArgumentException("Invalid custom size configuration for '{$name}', both width and height must be set.");
      }

      add_image_size($name, $size['width'], $size['height'], $size['crop'] ?? false);
    }
  }

  /**
   * Add custom image sizes to the editor
   *
   * This method adds the custom image sizes to the list of available sizes in the WordPress editor.
   *
   * @param array<string, mixed> $sizes The current list of image sizes
   *
   * @return array<string, mixed> The modified list of image sizes including custom sizes
   */
  public function addCustomImageSizesToEditor(array $sizes): array {
    foreach ($this->config['settings']['custom_sizes'] as $name => $size) {
      if (!isset($size['width'], $size['height'])) {
        throw new InvalidArgumentException("Invalid custom size configuration for '{$name}', both width and height must be set.");
      }

      $sizes[$name] = $size['label'] ?? ucfirst(str_replace('_', ' ', $name));
    }

    return $sizes;
  }

  /**
   * Initialize the Images service by applying all active settings
   */
  protected function init(): void {
    $settings = $this->config;

    // Always set JPEG quality
    Filters::add('jpeg_quality', [$this, 'setJpegQuality']);

    if ($settings['disabled']) {
      $this->disableImageProcessing();

      return;
    }

    $settings = $settings['settings'];

    if ($settings['disable_image_sizes']) {
      Filters::add('intermediate_image_sizes_advanced', [$this, 'disableImageSizes']);
      Filters::add('big_image_size_threshold', '__return_false');
    }

    if ($settings['disable_other_image_sizes']) {
      Events::addHandlers('init', [$this, 'disableOtherImageSizes']);
    }

    if ($settings['disable_image_editing']) {
      Filters::add('wp_image_editors', [$this, 'disableImageEditing']);
    }

    if ($settings['remove_default_image_sizes']) {
      Filters::add('intermediate_image_sizes_advanced', [$this, 'removeDefaultImageSizes']);
    }

    if ($settings['disable_responsive_images']) {
      Filters::add('max_srcset_image_width', [$this, 'disableResponsiveImages']);
    }

    if ($settings['prevent_image_resizes_on_upload']) {
      Filters::add('wp_generate_attachment_metadata', [$this, 'preventImageResizesOnUpload'], 10, 1);
    }

    if (!empty($settings['custom_sizes'])) {
      Events::addHandlers('after_setup_theme', [$this, 'addCustomImageSizes']);
      Filters::add('image_size_names_choose', [$this, 'addCustomImageSizesToEditor']);
    }
  }

  /**
   * Normalize the configuration by merging with defaults
   *
   * @param array<string, mixed> $config The configuration array
   *
   * @return ImagesConfig
   */
  protected function normalizeConfig(array $config): array {
    // If disabled is true, apply all disable settings
    if (!empty($config['disabled']) && $config['disabled'] === true) {
      return [
        'disabled' => true,
        'settings' => array_merge(self::DEFAULT_SETTINGS, [
          'disable_image_sizes' => true,
          'disable_other_image_sizes' => true,
          'disable_image_editing' => true,
          'remove_default_image_sizes' => true,
          'disable_responsive_images' => true,
          'prevent_image_resizes_on_upload' => true,
        ]),
      ];
    }

    // Merge with defaults while preserving custom settings
    return [
      'disabled' => $config['disabled'] ?? false,
      'settings' => array_merge(self::DEFAULT_SETTINGS, $config['settings'] ?? []),
    ];
  }

  /**
   * Validate the configuration
   *
   * @throws InvalidArgumentException
   */
  protected function validateConfig(): void {
    if (!is_bool($this->config['disabled'])) {
      throw new InvalidArgumentException("The 'disabled' option must be a boolean.");
    }

    $settings = $this->config['settings'];

    // Validate boolean settings
    $booleanSettings = [
      'disable_image_sizes',
      'disable_other_image_sizes',
      'disable_image_editing',
      'remove_default_image_sizes',
      'disable_responsive_images',
      'prevent_image_resizes_on_upload',
    ];

    foreach ($booleanSettings as $setting) {
      if (isset($settings[$setting]) && !is_bool($settings[$setting])) {
        throw new InvalidArgumentException("The '{$setting}' option must be a boolean.");
      }
    }

    // Validate JPEG quality
    if (isset($settings['jpeg_quality'])) {
      $quality = $settings['jpeg_quality'];

      if (!is_int($quality) || $quality < 0 || $quality > 100) {
        throw new InvalidArgumentException('JPEG quality must be an integer between 0 and 100.');
      }
    }

    // Validate custom sizes
    if (isset($settings['custom_sizes'])) {
      foreach ($settings['custom_sizes'] as $name => $size) {
        if (!isset($size['width'], $size['height'])) {
          throw new InvalidArgumentException("Invalid custom size configuration for '{$name}', both width and height must be set.");
        }

        if (isset($size['crop']) && !is_bool($size['crop'])) {
          throw new InvalidArgumentException("The 'crop' option for '{$name}' must be a boolean.");
        }
      }
    }
  }

  /**
   * Disable various image processing features
   *
   * This method adds filters to disable different aspects of WordPress image processing.
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
   */
  protected function setupCustomImageSizes(): void {
    if (!empty($this->config['settings']['custom_sizes'])) {
      Events::addHandlers('after_setup_theme', [$this, 'addCustomImageSizes']);
      Filters::add('image_size_names_choose', [$this, 'addCustomImageSizesToEditor']);
    }
  }
}
