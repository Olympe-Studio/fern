<?php

declare(strict_types=1);

namespace Fern\Core\Services\Controller;

use Fern\Core\Services\HTTP\Request;
use ReflectionAttribute;

interface AttributesHandler {
  /**
   * Handle the attribute
   *
   * @param ReflectionAttribute $attribute The attribute instance
   * @param object $controller The controller instance
   * @param string $methodName The method name
   * @param Request $request The current request
   *
   * @return bool|string Returns true if the attribute is valid, or an error message
   */
  public function handle(
    ReflectionAttribute $attribute,
    object $controller,
    string $methodName,
    Request $request
  ): bool|string;
}
