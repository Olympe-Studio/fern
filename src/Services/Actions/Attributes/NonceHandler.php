<?php

declare(strict_types=1);

namespace Fern\Core\Services\Actions\Attributes;

use ReflectionAttribute;
use Fern\Core\Services\Controller\AttributesHandler;
use Fern\Core\Services\HTTP\Request;

/**
 * Handler for Nonce attribute
 */
class NonceHandler implements AttributesHandler {
  /**
   * Handle the Nonce attribute
   *
   * @param ReflectionAttribute $attribute The attribute instance
   * @param object $controller The controller instance
   * @param string $methodName The method name
   * @param Request $request The current request
   *
   * @return bool|string Returns true if the nonce is valid, or an error message
   */
  public function handle(
    ReflectionAttribute $attribute,
    object $controller,
    string $methodName,
    Request $request
  ): bool|string {
    $actionName = $attribute->newInstance()->actionName;
    $action = $request->getAction();
    $isValid = wp_verify_nonce($action->get('_nonce'), $actionName);

    if (!$isValid) {
      return false;
    }

    return true;
  }
}