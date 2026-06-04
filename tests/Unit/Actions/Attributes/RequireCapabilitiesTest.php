<?php declare(strict_types=1);

use Fern\Core\Services\Actions\Attributes\RequireCapabilities;

describe('construction', function (): void {
  it('defaults to an empty capabilities array', function (): void {
    $attribute = new RequireCapabilities();

    expect($attribute->capabilities)->toBe([]);
  });

  it('exposes the capabilities passed to the constructor', function (): void {
    $attribute = new RequireCapabilities(['edit_posts', 'manage_options']);

    expect($attribute->capabilities)->toBe(['edit_posts', 'manage_options']);
  });

  it('accepts a single-capability array', function (): void {
    $attribute = new RequireCapabilities(['read']);

    expect($attribute->capabilities)->toBe(['read']);
  });
});
