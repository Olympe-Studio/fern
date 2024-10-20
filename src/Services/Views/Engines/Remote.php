<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views\Engines;

use Fern\Core\Services\Views\RenderingEngine;


class Remote implements RenderingEngine {
  /**
   * @var string
   */
  private string $url;

  public function __construct(array $config) {
    $this->url = $config['protocol'] . '://' . $config['host'] . ':' . $config['port'];
  }

  /**
   * Boot the rendering engine
   *
   * @param array $config
   * @return void
   */
  public function boot(): void {

  }

  /**
   * Render a block
   *
   * @param string $template  The name of the template to render
   * @param array $data       The data to pass to the template
   *
   * @return string
   */
  public function renderBlock(string $template, array $data = []): string {
    return $this->render($template, $data);
  }

  /**
   * Render a template
   *
   * @param string $template  The name of the template to render
   * @param array $data       The data to pass to the template
   *
   * @return string
   * @throws \InvalidArgumentException
   */
  public function render(string $template, array $data = []): string {
    $url = $this->url . '/' . $template;
    $response = wp_remote_post($url, [
      'body' => json_encode($data),
      'timeout' => 2.5,
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      // We should not verify SSL for rendering servers
      'sslverify' => false,
    ]);

    if (is_wp_error($response)) {
      throw new \InvalidArgumentException('Failed to fetch template from remote server. Check that the URL is correct. WP Error: ' . $response->get_error_message());
    }

    return wp_remote_retrieve_body($response);
  }
}
