<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views;

use Fern\Core\Config;
use Fern\Core\Context;
use Fern\Core\Wordpress\Filters;
use InvalidArgumentException;

class Views {
  /**
   */
  private static ?RenderingEngine $engine = null;

  /**
   * Render a template
   *
   * @param string               $template   The template to render
   * @param array<string, mixed> $data       The data to pass to the template
   * @param bool                 $doingBlock Whether the render is being done in a block context
   *
   * @throws InvalidArgumentException
   */
  public static function render(string $template, array $data = [], bool $doingBlock = false): string {
    $engine = self::getEngine();
    $baseCtx = Context::get();
    $existingCtx = isset($data['ctx']) && is_array($data['ctx']) ? $data['ctx'] : [];

    /** @var array<string,mixed> $mergedCtx */
    $mergedCtx = [
      ...$baseCtx,
      ...$existingCtx,
    ];

    $data['ctx'] = $mergedCtx;

    if (!$doingBlock) {
      /**
       * Allow context injection for views. It won't override existing ctx.
       *
       * @param array $ctx
       *
       * @return array
       */
      $ctx = Filters::apply('fern:core:views:ctx', $data['ctx']);

      if ($ctx !== [] && !is_null($ctx)) {
        $data['ctx'] = $ctx;
      }
    }

    /**
     * Allow data injection for views like global data
     *
     * @param array $data
     *
     * @return array
     */
    $data = Filters::apply('fern:core:views:data', $data);

    if (!is_array($data)) {
      throw new InvalidArgumentException('Invalid data. Views data must be an array, received: ' . gettype($data) . '.');
    }

    $result = $engine->render($template, $data);

    /**
     * Allow result modification for views
     *
     * @param string $result
     *
     * @return string
     */
    $result = Filters::apply('fern:core:views:result', $result);

    return $result;
  }

  /**
   * Get the rendering engine
   *
   * @throws InvalidArgumentException
   */
  private static function getEngine(): RenderingEngine {
    if (self::$engine !== null) {
      return self::$engine;
    }

    $engine = Config::get('rendering_engine');

    if (!($engine instanceof RenderingEngine)) {
      throw new InvalidArgumentException('Invalid rendering engine. Must implement RenderingEngine interface.');
    }

    $engine->boot();
    self::$engine = $engine;

    return $engine;
  }
}
