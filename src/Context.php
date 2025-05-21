<?php

namespace Fern\Core;

use Fern\Core\Factory\Singleton;
use Fern\Core\Wordpress\Filters;

class Context extends Singleton {
  /**
   * @var array
   */
  public $context;

  public function __construct() {
    $this->context = [];
  }

  public static function boot() {
    self::getInstance()->set(Filters::apply('fern:core:ctx', []));
  }

  /**
   * @param array $ctx
   */
  public static function set($ctx) {
    self::getInstance()->context = $ctx;
  }

  /**
   * @return array
   */
  public static function get() {
    return self::getInstance()->context;
  }
}
