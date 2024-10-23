<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views;

interface RenderingEngine {
  /**
   * Render a template
   */
  public function render(string $template, array $data = []): string;

  /**
   * Render a block
   */
  public function renderBlock(string $block, array $data = []): string;

  /**
   * Boot the rendering engine
   */
  public function boot(): void;
}
