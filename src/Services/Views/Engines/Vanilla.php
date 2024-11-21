<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views\Engines;

use Fern\Core\Services\Views\RenderingEngine;
use InvalidArgumentException;

class Vanilla implements RenderingEngine {
  /**
   */
  private string $path;

  /**
   */
  private string $blocksPath;

  /**
   * @param array{path: string, blocks_path: string} $config
   */
  public function __construct(array $config) {
    $this->path = trailingslashit($config['path']);
    $this->blocksPath = trailingslashit($config['blocks_path']);
  }

  /**
   * Boot the rendering engine
   *
   * @throws InvalidArgumentException
   */
  public function boot(): void {
    if (!is_dir($this->path)) {
      throw new InvalidArgumentException('Invalid path. Must be a valid directory. Check that you have set the correct path in your config.');
    }
  }

  /**
   * Render a block
   *
   * @param string               $block The block name
   * @param array<string, mixed> $data  The data to pass to the block
   *
   * @throws InvalidArgumentException
   */
  public function renderBlock(string $block, array $data = []): string {
    $block = str_replace('.php', '', $block);
    $path = $this->blocksPath . $block . '.php';

    return $this->renderTemplate($path, $data);
  }

  /**
   * Render a template
   *
   * @param string               $template The template name
   * @param array<string, mixed> $data     The data to pass to the template
   *
   * @throws InvalidArgumentException
   */
  public function render(string $template, array $data = []): string {
    $template = str_replace('.php', '', $template);
    $path = $this->path . $template . '.php';

    return $this->renderTemplate($path, $data);
  }

  /**
   * Render a template
   *
   * @param string               $path The path to the template
   * @param array<string, mixed> $data The data to pass to the template
   *
   * @throws InvalidArgumentException
   */
  private function renderTemplate(string $path, array $data = []): string {
    if (!file_exists($path)) {
      throw new InvalidArgumentException('Template not found. Check that you have set the correct path in your config.');
    }

    ob_start();

    extract($data, EXTR_SKIP);
    include $path;

    return ob_get_clean() ?: '';
  }
}
