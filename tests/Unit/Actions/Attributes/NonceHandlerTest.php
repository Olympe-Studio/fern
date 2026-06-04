<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Actions\Attributes\NonceHandler;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Tests\Fixtures\FakeRequest;

class NonceHandlerSubject {
  #[Nonce('save_settings')]
  #[RequireCapabilities(['read'])]
  public function method(): void {}
}

/**
 * @return ReflectionAttribute<object>
 */
function nonceAttribute(string $attributeClass): ReflectionAttribute {
  $reflection = new ReflectionMethod(NonceHandlerSubject::class, 'method');
  $attributes = $reflection->getAttributes($attributeClass);

  return $attributes[0];
}

function setCurrentAction(array $body): void {
  $property = new ReflectionProperty(Action::class, 'current');
  $property->setValue(null, new Action(new FakeRequest($body)));
}

describe('handle', function (): void {
  it('returns true when wp_verify_nonce validates the nonce', function (): void {
    setCurrentAction(['action' => 'x', 'args' => ['_nonce' => 'abc123']]);

    Functions\expect('wp_verify_nonce')
      ->once()
      ->with('abc123', 'save_settings')
      ->andReturn(1);

    $result = (new NonceHandler())->handle(
      nonceAttribute(Nonce::class),
      new NonceHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue();
  });

  it('returns false when wp_verify_nonce rejects the nonce', function (): void {
    setCurrentAction(['action' => 'x', 'args' => ['_nonce' => 'bad']]);

    Functions\when('wp_verify_nonce')->justReturn(false);

    $result = (new NonceHandler())->handle(
      nonceAttribute(Nonce::class),
      new NonceHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeFalse();
  });

  it('coerces a missing nonce to an empty string before verifying', function (): void {
    setCurrentAction(['action' => 'x', 'args' => []]);

    Functions\expect('wp_verify_nonce')
      ->once()
      ->with('', 'save_settings')
      ->andReturn(false);

    $result = (new NonceHandler())->handle(
      nonceAttribute(Nonce::class),
      new NonceHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeFalse();
  });

  it('short-circuits to true when the attribute is not a Nonce', function (): void {
    Functions\expect('wp_verify_nonce')->never();

    $result = (new NonceHandler())->handle(
      nonceAttribute(RequireCapabilities::class),
      new NonceHandlerSubject(),
      'method',
      new FakeRequest([]),
    );

    expect($result)->toBeTrue();
  });
});
