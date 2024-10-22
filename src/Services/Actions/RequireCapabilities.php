<?php

namespace Fern\Core\Services\Actions;

use Attribute;

/**
 * Attribute for defining required WordPress capabilities
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireCapabilities {
  public function __construct(public array $capabilities = []) {
  }
}
