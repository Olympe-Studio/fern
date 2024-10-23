<?php

namespace Fern\Core\Services\Actions\Attributes;

use Attribute;

/**
 * Attribute for defining required WordPress capabilities
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireCapabilities {
  /**
   * @param array $capabilities Array of user capabilities required to access the action
   */
  public function __construct(public array $capabilities = []) {
  }
}
