<?php
declare(strict_types=1);
namespace Fern\Core\Services\Wordpress;

use Fern\Core\Config;
use Fern\Core\Wordpress\Filters;

class Wordpress {

  /**
   * Boot the Wordpress service
   *
   * @return void
   */
  public static function boot(): void {
    $config = Config::get('core.excerpt');

    if (isset($config['length'])) {
      Filters::add('excerpt_length', function () use ($config) {
        return $config['length'];
      });
    }

    if (isset($config['more'])) {
      Filters::add('excerpt_more', function () use ($config) {
        return $config['more'];
      });
    }
  }
}
