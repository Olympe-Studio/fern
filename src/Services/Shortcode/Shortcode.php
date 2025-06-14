<?php

declare(strict_types=1);

namespace Fern\Core\Services\Shortcode;

use Fern\Core\Factory\Singleton;
use Fern\Core\Wordpress\Filters;
use Fern\Core\Services\Views\Views;
use Fern\Core\Wordpress\Events;

/**
 * Tiny helper to register WordPress shortcodes with a whitelist of attributes.
 */
final class Shortcode extends Singleton {
  /**
   * Register a shortcode.
   *
   * @param string                        $tag          Shortcode tag.
   * @param array<int,string>             $allowedArgs  Whitelisted attribute names.
   * @param string                        $viewTemplate View template.
   * @param null|callable(array<string,mixed>, string): array<string,mixed> $augment Optional callback to inject extra attributes before render.
   */
  public function register(string $tag, array $allowedArgs, string $viewTemplate, ?callable $augment = null): void {
    $callback = function (array $rawAttrs = [], string $content = '') use ($tag, $allowedArgs, $viewTemplate, $augment): string {
      return $this->renderShortcode($tag, $allowedArgs, $viewTemplate, $augment, $rawAttrs, $content);
    };

    add_shortcode($tag, $callback);
  }

  /**
   * Renders the shortcode using Views service and apply hooks.
   *
   * @param string                         $tag
   * @param array<int,string>              $allowed  Allowed attribute names.
   * @param string                         $template Template view name.
   * @param null|callable(array<string,mixed>, string): array<string,mixed> $augment Optional augment callback.
   * @param array<string,mixed>            $raw      Raw shortcode attributes.
   */
  private function renderShortcode(string $tag, array $allowed, string $template, ?callable $augment, array $raw, string $content): string {
    $attrs = array_intersect_key($raw, array_flip($allowed));
    $attrs = Filters::apply('fern:core:shortcode:attrs', $attrs, $tag, $content);

    if ($augment) {
      $attrs = [...$attrs, ...$augment($attrs, $content)];
    }

    $data = [
      'attrs' => $attrs,
      'content' => $content,
    ];

    $data = Filters::apply('fern:core:shortcode:data', $data, $tag);

    // Let devs render via hook. Capture echoed output.
    $html = Events::renderToString('fern:core:shortcode:render', [$tag, $data]);

    if ($html === '') {
      $html = Views::render($template, $data, true);
    }

    return Filters::apply('fern:core:shortcode:html', $html, $tag, $data);
  }
}
