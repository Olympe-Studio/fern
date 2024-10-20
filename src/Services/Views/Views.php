<?php

declare(strict_types=1);

namespace Fern\Core\Services\Views;

use Fern\Core\Config;
use Fern\Core\Wordpress\Events;

class Views {
  /**
   * @var RenderingEngine
   */
  private static ?RenderingEngine $engine = null;

  /**
   * Render a template
   *
   * @param string $template
   * @param array $data
   *
   * @return string
   * @throws \InvalidArgumentException
   */
  public static function render(string $template, array $data = []): string {
    Events::trigger('qm/start', 'fern:render_view');
    $engine = self::getEngine();
    $result = $engine->render($template, $data);
    Events::trigger('qm/stop', 'fern:render_view');
    return $result;
  }

  /**
   * Get the rendering engine
   *
   * @return RenderingEngine
   * @throws \InvalidArgumentException
   */
  private static function getEngine(): RenderingEngine {
    if (self::$engine !== null) {
      return self::$engine;
    }

    $engine = Config::get('rendering_engine');

    if (!($engine instanceof RenderingEngine)) {
      throw new \InvalidArgumentException('Invalid rendering engine. Must implement RenderingEngine interface.');
    }

    $engine->boot();
    return $engine;
  }
}


