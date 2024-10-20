<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views\Engines;

use Fern\Core\Services\Views\RenderingEngine;

class Vanilla implements RenderingEngine {
  /**
   * @var string
   */
  private string $path;

  /**
   * @var string
   */
  private string $blocksPath;

  public function __construct(array $config) {
    $this->path = trailingslashit($config['path']);
    $this->blocksPath = trailingslashit($config['blocks_path']);
  }

  /**
   * Boot the rendering engine
   *
   * @return void
   * @throws \InvalidArgumentException
   */
  public function boot(): void {
    if (!is_dir($this->path)) {
      throw new \InvalidArgumentException('Invalid path. Must be a valid directory. Check that you have set the correct path in your config.');
    }
  }

  /**
   * Render a block
   *
   * @param string $block
   * @param array $data
   *
   * @return string
   */
  public function renderBlock(string $block, array $data = []): string {
    $block = str_replace('.php', '', $block);
    $path = $this->blocksPath . $block . '.php';
    return $this->renderTemplate($path, $data);
  }

  /**
   * Render a template
   *
   * @param string $template
   * @param array $data
   *
   * @return string
   * @throws \InvalidArgumentException
   */
  public function render(string $template, array $data = []): string {
    $template = str_replace('.php', '', $template);
    $path = $this->path . $template . '.php';
    return $this->renderTemplate($path, $data);
  }

  /**
   * Render a template
   *
   * @param string $path
   * @param array $data
   *
   * @return string
   * @throws \InvalidArgumentException
   */
  private function renderTemplate(string $path, array $data = []): string {
    if (!file_exists($path)) {
      throw new \InvalidArgumentException('Template not found. Check that you have set the correct path in your config.');
    }

    ob_start();

    extract($data, EXTR_SKIP);
    include $path;

    return ob_get_clean();
  }
}
