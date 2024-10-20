<?php
declare(strict_types=1);

use Fern\Core\Fern;
use Fern\Core\Services\Router\Router;

if (Fern::isDev()) {
  wp_head();
}
if (Fern::isDev()) {
  wp_footer();
}

// Resolve the routes
$router = Router::getInstance();
$router->resolve();
