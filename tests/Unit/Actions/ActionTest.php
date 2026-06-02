<?php declare(strict_types=1);

use Fern\Core\Services\Actions\Action;
use Tests\Fixtures\FakeRequest;

/**
 * @param array<string, mixed> $body
 */
function makeAction(array $body, string $contentType = 'application/json'): Action {
  return new Action(new FakeRequest($body, $contentType));
}

describe('name resolution', function (): void {
  it('reads the action name from the body', function (): void {
    $action = makeAction(['action' => 'doThing']);

    expect($action->getName())->toBe('doThing')
      ->and($action->isBadRequest())->toBeFalse();
  });

  it('is a bad request when the action is missing', function (): void {
    $action = makeAction(['args' => ['a' => 1]]);

    expect($action->getName())->toBeNull()
      ->and($action->isBadRequest())->toBeTrue();
  });

  it('is a bad request when the action is not a string', function (): void {
    $action = makeAction(['action' => 123]);

    expect($action->isBadRequest())->toBeTrue();
  });

  it('overrides the name via setName', function (): void {
    $action = makeAction(['action' => 'old']);

    expect($action->setName('new'))->toBeInstanceOf(Action::class)
      ->and($action->getName())->toBe('new');
  });
});

describe('argument parsing', function (): void {
  it('reads args from the body for non form-data requests', function (): void {
    $action = makeAction(['action' => 'x', 'args' => ['a' => 1, 'b' => 2]]);

    expect($action->getRawArgs())->toBe(['a' => 1, 'b' => 2]);
  });

  it('falls back to an empty array when args is not an array', function (): void {
    $action = makeAction(['action' => 'x', 'args' => 'nope']);

    expect($action->getRawArgs())->toBe([]);
  });

  it('uses the body minus the action key for form-data requests', function (): void {
    $action = makeAction(
      ['action' => 'submit', 'name' => 'John', 'email' => 'j@x.test'],
      'form-data',
    );

    expect($action->getRawArgs())->toBe(['name' => 'John', 'email' => 'j@x.test']);
  });

  it('has no args when neither args nor form-data are present', function (): void {
    $action = makeAction(['action' => 'x']);

    expect($action->getRawArgs())->toBe([]);
  });
});

describe('argument access and mutation', function (): void {
  it('gets an argument or a default', function (): void {
    $action = makeAction(['action' => 'x', 'args' => ['a' => 1]]);

    expect($action->get('a'))->toBe(1)
      ->and($action->get('missing'))->toBeNull()
      ->and($action->get('missing', 'fallback'))->toBe('fallback');
  });

  it('adds and updates arguments fluently', function (): void {
    $action = makeAction(['action' => 'x']);

    expect($action->add('a', 1))->toBeInstanceOf(Action::class);
    $action->update('a', 2);

    expect($action->get('a'))->toBe(2);
  });

  it('removes arguments', function (): void {
    $action = makeAction(['action' => 'x', 'args' => ['a' => 1]]);

    $action->remove('a');

    expect($action->has('a'))->toBeFalse();
  });

  it('merges new arguments, overriding existing keys', function (): void {
    $action = makeAction(['action' => 'x', 'args' => ['a' => 1, 'b' => 2]]);

    $action->merge(['b' => 20, 'c' => 30]);

    expect($action->getRawArgs())->toBe(['a' => 1, 'b' => 20, 'c' => 30]);
  });

  it('reports presence with has and hasNot', function (): void {
    $action = makeAction(['action' => 'x', 'args' => ['a' => 1]]);

    expect($action->has('a'))->toBeTrue()
      ->and($action->hasNot('a'))->toBeFalse()
      ->and($action->has('b'))->toBeFalse()
      ->and($action->hasNot('b'))->toBeTrue();
  });
});

describe('getCurrent', function (): void {
  it('returns the memoized current action', function (): void {
    $existing = makeAction(['action' => 'memoized']);

    $property = new ReflectionProperty(Action::class, 'current');
    $property->setValue(null, $existing);

    expect(Action::getCurrent())->toBe($existing);
  });
});
