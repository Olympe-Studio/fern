<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Errors\AttributeValidationException;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Services\Actions\Attributes\CacheReply;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Fern\Core\Services\Controller\AttributesHandler;
use Fern\Core\Services\Controller\AttributesManager;
use Fern\Core\Services\HTTP\Request;
use Tests\Fixtures\FakeRequest;

class AttributesManagerSubject {
  public function bare(): void {}

  #[Nonce('save_settings')]
  public function nonceGuarded(): void {}

  #[RequireCapabilities(['manage_options'])]
  public function capabilityGuarded(): void {}

  #[Nonce('save_settings')]
  #[RequireCapabilities(['manage_options'])]
  public function doubleGuarded(): void {}
}

class NotAHandler {
  public function method(): bool {
    return true;
  }
}

class AlwaysFailingHandler implements AttributesHandler {
  public function handle(
    ReflectionAttribute $attribute,
    object $controller,
    string $methodName,
    Request $request,
  ): bool|string {
    return 'nope';
  }
}

class NonBoolHandler implements AttributesHandler {
  public function handle(
    ReflectionAttribute $attribute,
    object $controller,
    string $methodName,
    Request $request,
  ): bool|string {
    return true;
  }

  /**
   * @param ReflectionAttribute<object> $attribute
   *
   * @return array<int, int>
   */
  public function asArray(
    ReflectionAttribute $attribute,
    object $controller,
    string $methodName,
    Request $request,
  ): array {
    return [1];
  }
}

function setManagerAction(array $body): void {
  $property = new ReflectionProperty(Action::class, 'current');
  $property->setValue(null, new Action(new FakeRequest($body)));
}

function bootManagerWithDefaults(): AttributesManager {
  Functions\when('apply_filters')->alias(fn (string $hook, mixed $value): mixed => $value);
  AttributesManager::boot();

  return AttributesManager::getInstance();
}

describe('boot and handler registration', function (): void {
  it('registers the built-in handlers for the core attributes', function (): void {
    $manager = bootManagerWithDefaults();

    $handlers = (new ReflectionProperty(AttributesManager::class, 'handlers'))->getValue($manager);

    expect($handlers)->toHaveKeys([
      RequireCapabilities::class,
      CacheReply::class,
      Nonce::class,
    ]);
  });

  it('ignores filtered handler entries that are not callable or string-keyed', function (): void {
    Functions\when('apply_filters')->justReturn([
      'Some\\Attribute' => 'not-callable',
      42 => [new AlwaysFailingHandler(), 'handle'],
    ]);

    AttributesManager::boot();
    $manager = AttributesManager::getInstance();
    $handlers = (new ReflectionProperty(AttributesManager::class, 'handlers'))->getValue($manager);

    expect($handlers)->toBe([]);
  });

  it('falls back to an empty handler set when the filter returns a non-array', function (): void {
    Functions\when('apply_filters')->justReturn('broken');

    AttributesManager::boot();
    $manager = AttributesManager::getInstance();

    expect((new ReflectionProperty(AttributesManager::class, 'handlers'))->getValue($manager))->toBe([]);
  });
});

describe('register validation', function (): void {
  it('accepts an array-style callable whose target implements AttributesHandler', function (): void {
    $manager = AttributesManager::getInstance();

    $manager->register('X', [new AlwaysFailingHandler(), 'handle']);

    expect((new ReflectionProperty(AttributesManager::class, 'handlers'))->getValue($manager))
      ->toHaveKey('X');
  });

  it('rejects an array-style callable whose target is not an AttributesHandler', function (): void {
    $manager = AttributesManager::getInstance();

    expect(fn (): never => $manager->register('X', [new NotAHandler(), 'method']))
      ->toThrow(InvalidArgumentException::class, 'must implement');
  });

  it('rejects a string callable that is not an AttributesHandler', function (): void {
    $manager = AttributesManager::getInstance();

    expect(fn (): never => $manager->register('X', 'strlen'))
      ->toThrow(InvalidArgumentException::class, 'must implement');
  });
});

describe('validateMethod success branches', function (): void {
  it('returns true for a method that carries no attributes', function (): void {
    $manager = bootManagerWithDefaults();

    expect($manager->validateMethod(new AttributesManagerSubject(), 'bare', new FakeRequest([])))
      ->toBeTrue();
  });

  it('passes a valid nonce and granted capability', function (): void {
    setManagerAction(['action' => 'x', 'args' => ['_nonce' => 'ok']]);
    Functions\when('wp_verify_nonce')->justReturn(1);
    Functions\when('current_user_can')->justReturn(true);

    $manager = bootManagerWithDefaults();

    expect($manager->validateMethod(new AttributesManagerSubject(), 'doubleGuarded', new FakeRequest([])))
      ->toBeTrue();
  });

  it('skips attributes that have no registered handler', function (): void {
    setManagerAction(['action' => 'x', 'args' => []]);
    Functions\when('apply_filters')->justReturn([]);
    AttributesManager::boot();
    $manager = AttributesManager::getInstance();

    expect($manager->validateMethod(new AttributesManagerSubject(), 'nonceGuarded', new FakeRequest([])))
      ->toBeTrue();
  });
});

describe('validateMethod failure branches', function (): void {
  it('throws when the nonce handler reports failure', function (): void {
    setManagerAction(['action' => 'x', 'args' => ['_nonce' => 'bad']]);
    Functions\when('wp_verify_nonce')->justReturn(false);
    Functions\when('current_user_can')->justReturn(true);

    $manager = bootManagerWithDefaults();

    expect(fn (): bool => $manager->validateMethod(new AttributesManagerSubject(), 'nonceGuarded', new FakeRequest([])))
      ->toThrow(AttributeValidationException::class, 'Validation failed for method');
  });

  it('aggregates the capability error message into the exception', function (): void {
    setManagerAction(['action' => 'x', 'args' => ['_nonce' => 'ok']]);
    Functions\when('wp_verify_nonce')->justReturn(1);
    Functions\when('current_user_can')->justReturn(false);

    $manager = bootManagerWithDefaults();

    expect(fn (): bool => $manager->validateMethod(new AttributesManagerSubject(), 'capabilityGuarded', new FakeRequest([])))
      ->toThrow(AttributeValidationException::class, 'Missing required capability: manage_options');
  });

  it('throws when the reflected method does not exist', function (): void {
    $manager = bootManagerWithDefaults();

    expect(fn (): bool => $manager->validateMethod(new AttributesManagerSubject(), 'missingMethod', new FakeRequest([])))
      ->toThrow(AttributeValidationException::class, 'Failed to validate method');
  });
});

describe('handleAttribute non-bool/string results', function (): void {
  it('treats a handler that returns a non-bool, non-string result as valid', function (): void {
    setManagerAction(['action' => 'x', 'args' => []]);
    $manager = AttributesManager::getInstance();
    $manager->register(Nonce::class, [new NonBoolHandler(), 'asArray']);

    expect($manager->validateMethod(new AttributesManagerSubject(), 'nonceGuarded', new FakeRequest([])))
      ->toBeTrue();
  });
});
