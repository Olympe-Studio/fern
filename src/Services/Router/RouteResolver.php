<?php
declare(strict_types=1);

use Fern\Core\Services\Router\Router;

// Resolve the routes
$router = Router::getInstance();
$router->resolve();
