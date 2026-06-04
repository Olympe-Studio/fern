<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Context;
use Fern\Core\Errors\FernConfigurationExceptions;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
use Fern\Core\Services\Router\Router;

/**
 * Reflect-sets the cached development flag on Fern.
 */
function setFernIsDev(?bool $value): void {
  $property = new ReflectionProperty(Fern::class, 'isDev');
  $property->setValue(null, $value);
}

/**
 * Stubs add_action so the after_setup_theme callback registered by
 * bootThemeSupport can be captured and invoked directly, returning an accessor
 * to the captured callback.
 *
 * @return callable(): ?callable
 */
function captureThemeHook(): callable {
  $box = new stdClass();
  $box->hook = null;

  Functions\when('add_action')->alias(
    static function (string $hook, callable $cb) use ($box): bool {
      if ($hook === 'after_setup_theme') {
        $box->hook = $cb;
      }

      return true;
    },
  );

  return static fn (): ?callable => $box->hook;
}

/**
 * Injects a pre-built Router instance into the Singleton registry so that
 * Router::getInstance() does not run its heavy constructor.
 */
function injectRouterStub(bool $didPass): void {
  $router = (new ReflectionClass(Router::class))->newInstanceWithoutConstructor();
  $didPassProp = new ReflectionProperty(Router::class, 'didPass');
  $didPassProp->setValue($router, $didPass);

  $instances = new ReflectionProperty(Singleton::class, '_instances');
  /** @var array<class-string, object> $current */
  $current = $instances->getValue();
  $current[Router::class] = $router;
  $instances->setValue(null, $current);
}

describe('getVersion', function (): void {
  it('returns the version constant', function (): void {
    expect(Fern::getVersion())->toBe('0.1.0')
      ->and(Fern::getVersion())->toBe(Fern::VERSION);
  });
});

describe('isDev / isNotDev', function (): void {
  it('returns the cached value without recomputing when already true', function (): void {
    setFernIsDev(true);

    expect(Fern::isDev())->toBeTrue()
      ->and(Fern::isNotDev())->toBeFalse();
  });

  it('returns the cached value without recomputing when already false', function (): void {
    setFernIsDev(false);

    expect(Fern::isDev())->toBeFalse()
      ->and(Fern::isNotDev())->toBeTrue();
  });

  it('resolves to false on first call when WP_ENV is not development', function (): void {
    setFernIsDev(null);

    expect(Fern::isDev())->toBeFalse();
  });

  it('caches the resolved value after the first call', function (): void {
    setFernIsDev(null);

    expect(Fern::isDev())->toBeFalse();

    $property = new ReflectionProperty(Fern::class, 'isDev');
    expect($property->getValue())->toBeFalse();
  });
});

describe('context', function (): void {
  it('delegates to Context::get', function (): void {
    Context::set(['locale' => 'fr', 'theme' => 'plastiform']);

    expect(Fern::context())->toBe(['locale' => 'fr', 'theme' => 'plastiform']);
  });

  it('returns an empty array when the context is unset', function (): void {
    expect(Fern::context())->toBe([]);
  });
});

describe('getRoot', function (): void {
  it('delegates to Config::get(root) and coerces to a string', function (): void {
    Config::getInstance()->setConfig(['root' => '/var/www/app']);

    expect(Fern::getRoot())->toBe('/var/www/app');
  });

  it('returns an empty string when no root is configured', function (): void {
    Config::getInstance()->setConfig([]);

    expect(Fern::getRoot())->toBe('');
  });
});

describe('passed', function (): void {
  it('delegates to Router::passed and reports true when the router passed', function (): void {
    injectRouterStub(true);

    expect(Fern::passed())->toBeTrue();
  });

  it('reports false when the router did not pass', function (): void {
    injectRouterStub(false);

    expect(Fern::passed())->toBeFalse();
  });
});

describe('defineConfig', function (): void {
  it('triggers the before_boot event then validates the config root', function (): void {
    Actions\expectDone('fern:core:before_boot')->once();

    expect(fn (): mixed => Fern::defineConfig([]))
      ->toThrow(FernConfigurationExceptions::class, 'Root path is required.');
  });
});

describe('bootThemeSupport', function (): void {
  it('registers theme support and nav menus on after_setup_theme', function (): void {
    Config::getInstance()->setConfig([
      'theme' => [
        'support' => [
          'title-tag' => true,
          'disabled-feature' => false,
          'post-thumbnails' => ['post', 'page'],
        ],
        'menus' => ['primary' => 'Primary Menu'],
      ],
    ]);

    $captured = captureThemeHook();

    Functions\expect('add_theme_support')->once()->with('title-tag');
    Functions\expect('add_theme_support')->once()->with('post-thumbnails', ['post', 'page']);
    Functions\expect('register_nav_menus')->once()->with(['primary' => 'Primary Menu']);

    $method = new ReflectionMethod(Fern::class, 'bootThemeSupport');
    $method->invoke(null);

    expect($captured())->toBeCallable();
    ($captured())();
  });

  it('falls back to empty arrays when theme config is missing', function (): void {
    Config::getInstance()->setConfig([]);

    $captured = captureThemeHook();

    Functions\expect('register_nav_menus')->once()->with([]);
    Functions\when('add_theme_support')->justReturn(true);

    $method = new ReflectionMethod(Fern::class, 'bootThemeSupport');
    $method->invoke(null);

    ($captured())();
  });
});
