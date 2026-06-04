<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\Actions\Attributes\CapabilitiesHandler;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Tests\Fixtures\FakeRequest;

class CapabilitiesHandlerSubject {
  #[RequireCapabilities(['edit_posts', 'manage_options'])]
  #[Nonce('save')]
  public function method(): void {}
}

class CapabilitiesHandlerEmptySubject {
  #[RequireCapabilities()]
  public function method(): void {}
}

/**
 * @param class-string $subject
 *
 * @return ReflectionAttribute<object>
 */
function capabilityAttribute(string $subject, string $attributeClass): ReflectionAttribute {
  $reflection = new ReflectionMethod($subject, 'method');

  return $reflection->getAttributes($attributeClass)[0];
}

describe('handle', function (): void {
  it('returns true when every required capability is granted', function (): void {
    Functions\expect('current_user_can')
      ->twice()
      ->andReturn(true);

    $result = (new CapabilitiesHandler())->handle(
      capabilityAttribute(CapabilitiesHandlerSubject::class, RequireCapabilities::class),
      new CapabilitiesHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue();
  });

  it('returns the missing-capability message on the first failing capability', function (): void {
    Functions\when('current_user_can')->justReturn(false);

    $result = (new CapabilitiesHandler())->handle(
      capabilityAttribute(CapabilitiesHandlerSubject::class, RequireCapabilities::class),
      new CapabilitiesHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBe('Missing required capability: edit_posts');
  });

  it('stops at the first missing capability', function (): void {
    Functions\expect('current_user_can')
      ->once()
      ->with('edit_posts')
      ->andReturn(true);
    Functions\expect('current_user_can')
      ->once()
      ->with('manage_options')
      ->andReturn(false);

    $result = (new CapabilitiesHandler())->handle(
      capabilityAttribute(CapabilitiesHandlerSubject::class, RequireCapabilities::class),
      new CapabilitiesHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBe('Missing required capability: manage_options');
  });

  it('returns true when no capabilities are required', function (): void {
    Functions\expect('current_user_can')->never();

    $result = (new CapabilitiesHandler())->handle(
      capabilityAttribute(CapabilitiesHandlerEmptySubject::class, RequireCapabilities::class),
      new CapabilitiesHandlerEmptySubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue();
  });

  it('short-circuits to true when the attribute is not a RequireCapabilities', function (): void {
    Functions\expect('current_user_can')->never();

    $result = (new CapabilitiesHandler())->handle(
      capabilityAttribute(CapabilitiesHandlerSubject::class, Nonce::class),
      new CapabilitiesHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue();
  });
});
