<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views\Engines;

use Fern\Core\Services\Views\RenderingEngine;
use Fern\Core\Utils\JSON;
use InvalidArgumentException;

class Remote implements RenderingEngine {
  /**
   */
  private string $url;

  /**
   */
  private bool $sslverify;

  public function __construct(array $config) {
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
   * @param string $template The name of the template to render
   * @param array  $data     The data to pass to the template
   */
  public function renderBlock(string $template, array $data = []): string {
    return $this->render($template, $data);
  }

  /**
   * Render a template
   *
   * @param string $template The name of the template to render
   * @param array  $data     The data to pass to the template
   *
   * @throws InvalidArgumentException
   */
  public function render(string $template, array $data = []): string {
    $url = $this->url . '/' . $template;
    $response = wp_remote_post($url, [
      'body' => JSON::encode($data),
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
}
