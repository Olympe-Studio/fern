<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views\Engines;

use Fern\Core\Services\Views\RenderingEngine;
use Fern\Core\Utils\JSON;
use InvalidArgumentException;

/**
 * The Remote rendering engine
 *
 * @phpstan-type RemoteConfig array{
 *   protocol?: string,
 *   host?: string,
 *   port?: int,
 *   sslverify?: bool
 * }
 */
class Remote implements RenderingEngine {
  /**
   */
  private string $url;

  /**
   */
  private bool $sslverify;

  /**
   * @param RemoteConfig $config
   */
  public function __construct(array $config) {
    $this->validateConfig($config);

    /** @phpstan-ignore-next-line */
    $this->url = $config['protocol'] . '://' . $config['host'] . ':' . $config['port'];
    $this->sslverify = $config['sslverify'] ?? false;
  }

  /**
   * Boot the rendering engine
   */
  public function boot(): void {
  }

  /**
   * Render a block
   *
   * @param string               $template The name of the template to render
   * @param array<string, mixed> $data     The data to pass to the template
   */
  public function renderBlock(string $template, array $data = []): string {
    return $this->render($template, $data);
  }

  /**
   * Render a template
   *
   * @param string               $template The name of the template to render
   * @param array<string, mixed> $data     The data to pass to the template
   *
   * @throws InvalidArgumentException
   */
  public function render(string $template, array $data = []): string {
    $url = $this->url . '/' . $template;
    $body = JSON::encode($data);

    if (!$body) {
      throw new InvalidArgumentException('Failed to encode data to JSON');
    }

    $response = wp_remote_post($url, [
      'body' => $body,
      'timeout' => 2.5,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'sslverify' => $this->sslverify,
    ]);

    if (is_wp_error($response)) {
      throw new InvalidArgumentException('Failed to fetch template from remote server. Check that the URL is correct. WP Error: ' . $response->get_error_message());
    }

    return wp_remote_retrieve_body($response);
  }

  /**
   * Validate the configuration
   *
   * @param RemoteConfig $config
   *
   * @throws InvalidArgumentException
   */
  private function validateConfig(array $config): void {
    if (!isset($config['protocol']) || !isset($config['host']) || !isset($config['port'])) {
      throw new InvalidArgumentException('Invalid configuration for remote rendering engine');
    }
  }
}
