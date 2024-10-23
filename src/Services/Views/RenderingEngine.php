<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views;

interface RenderingEngine {
  /**
   * Render a template
   *
   * @param string               $template The template name
   * @param array<string, mixed> $data     The data to pass to the template
   */
  public function render(string $template, array $data = []): string;

  /**
   * Render a block
   *
   * @param string               $block The block name
   * @param array<string, mixed> $data  The data to pass to the block
   */
  public function renderBlock(string $block, array $data = []): string;

  /**
   * Boot the rendering engine
   */
  public function boot(): void;
}
