<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views;

interface RenderingEngine {
  /**
   * Render a template
   *
   * @param string $template
   * @param array $data
   *
   * @return string
   */
  public function render(string $template, array $data = []): string;

  /**
   * Render a block
   *
   * @param string $block
   * @param array $data
   *
   * @return string
   */
  public function renderBlock(string $block, array $data = []): string;

  /**
   * Boot the rendering engine
   *
   * @return void
   */
  public function boot(): void;
}
