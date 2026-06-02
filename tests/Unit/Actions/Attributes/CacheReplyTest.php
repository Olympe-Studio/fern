<?php declare(strict_types=1);

use Fern\Core\Services\Actions\Attributes\CacheReply;

describe('construction', function (): void {
  it('applies the documented defaults', function (): void {
    $attribute = new CacheReply();

    expect($attribute->ttl)->toBe(3600)
      ->and($attribute->key)->toBeNull()
      ->and($attribute->varyBy)->toBe([]);
  });

  it('exposes every constructor argument', function (): void {
    $attribute = new CacheReply(120, 'custom:key', ['id', 'lang']);

    expect($attribute->ttl)->toBe(120)
      ->and($attribute->key)->toBe('custom:key')
      ->and($attribute->varyBy)->toBe(['id', 'lang']);
  });

  it('accepts named arguments and keeps the other defaults', function (): void {
    $attribute = new CacheReply(varyBy: ['slug']);

    expect($attribute->ttl)->toBe(3600)
      ->and($attribute->key)->toBeNull()
      ->and($attribute->varyBy)->toBe(['slug']);
  });
});
