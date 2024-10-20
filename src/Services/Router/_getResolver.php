<?php

use Fern\Core\Wordpress\Filters;
use Fern\Services\Controller\ControllerResolver;

Filters::add('template_include', function() {
  /**
   * Boot the controller resolver.
   */
  ControllerResolver::boot();

  return __DIR__ . '/RouteResolver.php';
}, 9999, 0);