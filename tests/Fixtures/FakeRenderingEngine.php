<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Fern\Core\Services\Views\RenderingEngine;

/**
 * A minimal RenderingEngine double that records the arguments passed to each
 * method so delegation from Views can be asserted, and returns a configurable
 * output. Avoids Mockery because the interface methods are WP-free but the
 * fixture is reused across delegation assertions.
 */
class FakeRenderingEngine implements RenderingEngine {
  public bool $booted = false;

  public int $bootCount = 0;

  public ?string $lastTemplate = null;

  public ?string $lastBlock = null;

  /**
   * @var array<string, mixed>|null
   */
  public ?array $lastData = null;

  public function __construct(
    private string $output = 'rendered',
  ) {}

  /**
   * @param array<string, mixed> $data
   */
  public function render(string $template, array $data = []): string {
    $this->lastTemplate = $template;
    $this->lastData = $data;

    return $this->output;
  }

  /**
   * @param array<string, mixed> $data
   */
  public function renderBlock(string $block, array $data = []): string {
    $this->lastBlock = $block;
    $this->lastData = $data;

    return $this->output;
  }

  public function boot(): void {
    $this->booted = true;
    $this->bootCount++;
  }
}
