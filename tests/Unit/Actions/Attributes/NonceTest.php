<?php declare(strict_types=1);

use Fern\Core\Services\Actions\Attributes\Nonce;

describe('construction', function (): void {
  it('exposes the action name passed to the constructor', function (): void {
    $nonce = new Nonce('save_settings');

    expect($nonce->actionName)->toBe('save_settings');
  });

  it('accepts an empty action name', function (): void {
    $nonce = new Nonce('');

    expect($nonce->actionName)->toBe('');
  });
});
