<?php

declare(strict_types=1);

namespace Fern\Core;

use Fern\Core\Factory\Singleton;
use Fern\Core\Wordpress\Filters;

class Context extends Singleton {
  /**
   * Application context data.
   *
   * @var array<string,mixed>
   */
  public array $context;

  public function __construct() {
    parent::__construct();

    $this->context = [];
  }

  /**
   * Boot the context singleton.
   */
  public static function boot(): void {
    $ctx = Filters::apply('fern:core:ctx', []);

    if (!is_array($ctx)) {
      $ctx = [];
    }

    /** @var array<string, mixed> $ctx */
    self::set($ctx);
  }

  /**
   * Override the whole application context.
   *
   * @param array<string,mixed> $ctx
   */
  public static function set(array $ctx): void {
    self::getInstance()->context = $ctx;
  }

  /**
   * Retrieve the application context.
   *
   * @return array<string,mixed>
   */
  public static function get(): array {
    return self::getInstance()->context;
  }
}
