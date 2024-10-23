<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views;

use Fern\Core\Config;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use InvalidArgumentException;

class Views {
  /**
   */
  private static ?RenderingEngine $engine = null;

  /**
   * Render a template
   *
   *
   * @throws InvalidArgumentException
   */
  public static function render(string $template, array $data = []): string {
    Events::trigger('qm/start', 'fern:render_view');
    $engine = self::getEngine();

    if (isset($data['ctx'])) {
      throw new InvalidArgumentException('The `ctx` key is reserved for context injection. Please use `fern:core:views:ctx` filter to inject context.');
    }

    /**
     * Allow context injection for views. It won't override existing ctx.
     *
     * @param array $ctx
     *
     * @return array
     */
    $ctx = Filters::apply('fern:core:views:ctx', []);

    if ($ctx !== [] && !is_null($ctx)) {
      $data['ctx'] = $ctx;
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
    $result = Filters::apply('fern:core:views:result', $result);
    Events::trigger('qm/stop', 'fern:render_view');

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

    return $engine;
  }
}
