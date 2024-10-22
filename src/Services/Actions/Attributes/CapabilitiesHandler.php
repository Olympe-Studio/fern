<?php

namespace Fern\Core\Services\Actions\Attributes;

use Fern\Core\Services\Controller\AttributesHandler;
use ReflectionAttribute;
use Fern\Core\Services\HTTP\Request;

/**
 * Handler for RequireCapabilities attribute
 */
class CapabilitiesHandler implements AttributesHandler {
  /**
   * Handle the RequireCapabilities attribute
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
  ): bool|string {
    if (!isset($attribute->newInstance()->capabilities)) {
      // No capabilities required
      return true;
    }

    foreach ($attribute->newInstance()->capabilities as $capability) {
      if (!current_user_can($capability)) {
        return "Missing required capability: $capability";
      }
    }

    return true;
  }
}