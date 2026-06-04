<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Fern\Core\Config;
use Fern\Core\Context;
use Fern\Core\Services\Views\Views;
use Tests\Fixtures\FakeRenderingEngine;

function resetViewsEngine(): void {
  $property = new ReflectionProperty(Views::class, 'engine');
  $property->setValue(null, null);
}

beforeEach(function (): void {
  resetViewsEngine();
});

afterEach(function (): void {
  resetViewsEngine();
});

describe('getEngine via Config', function (): void {
  it('resolves the engine from config, boots it and caches it', function (): void {
    $engine = new FakeRenderingEngine('out');
    Config::getInstance()->setConfig(['rendering_engine' => $engine]);

    expect(Views::render('home'))->toBe('out')
      ->and($engine->booted)->toBeTrue()
      ->and($engine->bootCount)->toBe(1);

    Views::render('about');

    expect($engine->bootCount)->toBe(1)
      ->and($engine->lastTemplate)->toBe('about');
  });

  it('throws when no rendering engine is configured', function (): void {
    Config::getInstance()->setConfig([]);

    expect(fn (): string => Views::render('home'))
      ->toThrow(InvalidArgumentException::class, 'Invalid rendering engine. Must implement RenderingEngine interface.');
  });

  it('throws when the configured engine does not implement the interface', function (): void {
    Config::getInstance()->setConfig(['rendering_engine' => new stdClass()]);

    expect(fn (): string => Views::render('home'))
      ->toThrow(InvalidArgumentException::class, 'Invalid rendering engine.');
  });

  it('reuses an already-set engine without touching config', function (): void {
    $engine = new FakeRenderingEngine('cached');
    $property = new ReflectionProperty(Views::class, 'engine');
    $property->setValue(null, $engine);
    Config::getInstance()->setConfig([]);

    expect(Views::render('page'))->toBe('cached')
      ->and($engine->bootCount)->toBe(0);
  });
});

describe('render delegation', function (): void {
  beforeEach(function (): void {
    $this->engine = new FakeRenderingEngine('engine-output');
    Config::getInstance()->setConfig(['rendering_engine' => $this->engine]);
  });

  it('delegates the template name to the engine and returns its output', function (): void {
    $result = Views::render('templates/home', ['title' => 'Hi']);

    expect($result)->toBe('engine-output')
      ->and($this->engine->lastTemplate)->toBe('templates/home');
  });

  it('merges the base context into the data passed to the engine', function (): void {
    Context::set(['locale' => 'fr', 'user' => 'a']);

    Views::render('home', ['ctx' => ['user' => 'override'], 'foo' => 'bar']);

    expect($this->engine->lastData['ctx'])->toBe(['locale' => 'fr', 'user' => 'override'])
      ->and($this->engine->lastData['foo'])->toBe('bar');
  });

  it('seeds an empty ctx when none is provided in data', function (): void {
    Views::render('home', ['foo' => 'bar']);

    expect($this->engine->lastData)->toHaveKey('ctx')
      ->and($this->engine->lastData['ctx'])->toBe([]);
  });
});

describe('filter hooks', function (): void {
  beforeEach(function (): void {
    $this->engine = new FakeRenderingEngine('raw');
    Config::getInstance()->setConfig(['rendering_engine' => $this->engine]);
  });

  it('applies the ctx filter and uses the returned context', function (): void {
    Filters\expectApplied('fern:core:views:ctx')
      ->once()
      ->andReturn(['injected' => true]);

    Views::render('home');

    expect($this->engine->lastData['ctx'])->toBe(['injected' => true]);
  });

  it('keeps the merged ctx when the ctx filter returns an empty array', function (): void {
    Context::set(['base' => 1]);

    Filters\expectApplied('fern:core:views:ctx')
      ->once()
      ->andReturn([]);

    Views::render('home');

    expect($this->engine->lastData['ctx'])->toBe(['base' => 1]);
  });

  it('keeps the merged ctx when the ctx filter returns null', function (): void {
    Context::set(['base' => 1]);

    Filters\expectApplied('fern:core:views:ctx')
      ->once()
      ->andReturn(null);

    Views::render('home');

    expect($this->engine->lastData['ctx'])->toBe(['base' => 1]);
  });

  it('does not apply the ctx filter when rendering a block', function (): void {
    Filters\expectApplied('fern:core:views:ctx')->never();

    Views::render('home', [], true);

    expect($this->engine->lastTemplate)->toBe('home');
  });

  it('applies the data filter and passes the filtered data to the engine', function (): void {
    Filters\expectApplied('fern:core:views:data')
      ->once()
      ->andReturnUsing(static function (array $data): array {
        $data['injectedData'] = 'yes';

        return $data;
      });

    Views::render('home');

    expect($this->engine->lastData['injectedData'])->toBe('yes');
  });

  it('throws when the data filter returns a non-array', function (): void {
    Filters\expectApplied('fern:core:views:data')
      ->once()
      ->andReturn('not-an-array');

    expect(fn (): string => Views::render('home'))
      ->toThrow(InvalidArgumentException::class, 'Invalid data. Views data must be an array, received: string.');
  });

  it('applies the result filter to the engine output', function (): void {
    Filters\expectApplied('fern:core:views:result')
      ->once()
      ->andReturn('filtered-result');

    expect(Views::render('home'))->toBe('filtered-result');
  });

  it('coerces a non-string result filter return to a safe string', function (): void {
    Filters\expectApplied('fern:core:views:result')
      ->once()
      ->andReturn(123);

    expect(Views::render('home'))->toBe('123');
  });
});
