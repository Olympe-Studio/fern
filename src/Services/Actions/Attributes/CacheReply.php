<?php

namespace Fern\Core\Services\Actions\Attributes;

use Attribute;

/**
 * Attribute for defining cache settings for action responses
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CacheReply {
  /**
   * @param int         $ttl      Time to live in seconds
   * @param string|null $key      Custom cache key. If null, one will be generated automatically
   * @param array       $varyBy   Array of action parameters to vary the cache by
   */
  public function __construct(
    public readonly int $ttl      = 3600,
    public readonly ?string $key  = null,
    public readonly array $varyBy = [],
  ) {
  }
}
