<?php declare(strict_types=1);

namespace Fern\Core\Services\Actions\Attributes;

use Attribute;

/**
 * Attribute for validating a nonce for an action
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Nonce {
  /**
   * @param string $actionName The name of the action to validate the nonce for
   */
  public function __construct(public string $actionName) {}
}
