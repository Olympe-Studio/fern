<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Errors\ControllerRegistration;
use Fern\Core\Services\Controller\ControllerResolver;

if (!defined('ABSPATH')) {
  define('ABSPATH', '/tmp/fern-test-abspath/');
}

/**
 * Points the framework root at a throwaway directory and returns it.
 */
function makeResolverRoot(): string {
  $root = sys_get_temp_dir() . '/fern-resolver-' . uniqid('', true);
  mkdir($root . '/App/Controllers', 0777, true);
  Config::getInstance()->setConfig(['root' => $root]);

  return $root;
}

/**
 * Recursively deletes a directory tree.
 */
function removeResolverRoot(string $root): void {
  if (!is_dir($root)) {
    return;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
  );

  foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
  }

  rmdir($root);
}

/**
 * Writes a controller PHP file under App/Controllers and returns its class name.
 *
 * @param array<int, string> $methods Extra public action methods to declare.
 */
function writeControllerFile(
  string $root,
  string $className,
  string $handle,
  array $methods = [],
  string $extra = '',
): string {
  $methodSource = '';
  foreach ($methods as $method) {
    $methodSource .= "  public function {$method}(): int { return 1; }\n";
  }

  $source = <<<PHP
<?php declare(strict_types=1);

namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

class {$className} extends Singleton implements Controller {
  public static string \$handle = '{$handle}';
{$extra}
  public function handle(Request \$request): Reply {
    return new Reply(200, '');
  }

{$methodSource}}
PHP;

  $path = $root . '/App/Controllers/' . $className . '.php';
  file_put_contents($path, $source);

  return 'App\\Controllers\\' . $className;
}

/**
 * Builds a fresh resolver instance against the current root (singletons are
 * flushed between tests by WPTestCase).
 */
function makeResolver(): ControllerResolver {
  return ControllerResolver::getInstance();
}

/**
 * Resets the resolver's private static caches so a new instance reloads from
 * scratch within a single test.
 */
function resetResolverStatics(): void {
  foreach (['controllerTypeCache' => [], 'controllerRegistry' => [], 'registryLoaded' => false] as $name => $value) {
    $property = new ReflectionProperty(ControllerResolver::class, $name);
    $property->setValue(null, $value);
  }
}

afterEach(function (): void {
  if (isset($this->resolverRoot) && is_string($this->resolverRoot)) {
    removeResolverRoot($this->resolverRoot);
  }
});

describe('cache file generation', function (): void {
  it('scans controllers and writes the routes cache file', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Gen' . substr(md5(uniqid('', true)), 0, 8);
    writeControllerFile($this->resolverRoot, $unique . 'View', 'product', ['sayHello']);

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    expect($cacheFile)->toBeFile();

    $stats = $resolver->getCacheStats();
    expect($stats['cache_file_exists'])->toBeTrue()
      ->and($stats['cache_file_path'])->toBe($cacheFile)
      ->and($stats['registry_size'])->toBe(1);
  });

  it('stores controller metadata (type, handle, actions) in the registry', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Meta' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'kit', ['addToCart', 'removeFromCart']);

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();
    $registry = $resolver->getControllerRegistry();

    expect($registry)->toHaveKey($class)
      ->and($registry[$class]['type'])->toBe('view')
      ->and($registry[$class]['handle'])->toBe('kit')
      ->and($registry[$class]['actions'])->toBe(['addToCart', 'removeFromCart'])
      ->and($registry[$class]['file_mtime'])->toBeGreaterThan(0);
  });

  it('produces a cache file that returns the expected structure', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Struct' . substr(md5(uniqid('', true)), 0, 8);
    writeControllerFile($this->resolverRoot, $unique . 'View', 'page');

    Functions\when('wp_mkdir_p')->justReturn(true);

    makeResolver();

    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    $data = include $cacheFile;

    expect($data)->toBeArray()
      ->and($data)->toHaveKeys(['metadata', 'controllers'])
      ->and($data['metadata']['version'])->toBe('0.1.0')
      ->and($data['metadata']['controller_count'])->toBe(1)
      ->and($data['controllers'])->toHaveCount(1);
  });

  it('writes an empty registry when there is no controllers directory', function (): void {
    $root = sys_get_temp_dir() . '/fern-resolver-' . uniqid('', true);
    mkdir($root . '/App', 0777, true);
    Config::getInstance()->setConfig(['root' => $root]);
    $this->resolverRoot = $root;

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerRegistry())->toBe([])
      ->and($root . '/App/_routes_cache.fern.php')->toBeFile();
  });
});

describe('controller type detection', function (): void {
  it('detects the default controller from the _default handle', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Def' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique, '_default');

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerRegistry()[$class]['type'])->toBe('default')
      ->and($resolver->getDefaultController())->toBe($class);
  });

  it('detects the 404 controller from the _404 handle', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'NotFound' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique, '_404');

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerRegistry()[$class]['type'])->toBe('_404')
      ->and($resolver->get404Controller())->toBe($class);
  });

  it('detects an admin controller from the AdminController trait', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Admin' . substr(md5(uniqid('', true)), 0, 8);
    $extra = "  use \\Fern\\Core\\Services\\Controller\\AdminController;\n  public function configure(): array { return []; }\n";
    $class = writeControllerFile($this->resolverRoot, $unique, 'settings', [], $extra);

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerRegistry()[$class]['type'])->toBe('admin');
  });

  it('treats a plain controller with a normal handle as a view', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Plain' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique, 'blog');

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerRegistry()[$class]['type'])->toBe('view');
  });
});

describe('resolution by handle and type', function (): void {
  it('resolves a registered view controller by handle', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Resolve' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique, 'product');

    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    expect($resolver->resolve('view', 'product'))->toBe($class);
  });

  it('returns null for an unknown handle', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    expect($resolver->resolve('view', 'does-not-exist'))->toBeNull();
  });

  it('throws when no default controller is registered', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect(fn (): mixed => $resolver->getDefaultController())
      ->toThrow(ControllerRegistration::class, 'No default controller registered');
  });

  it('throws when no 404 controller is registered', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect(fn (): mixed => $resolver->get404Controller())
      ->toThrow(ControllerRegistration::class, 'No NotFound controller registered');
  });
});

describe('action lookups', function (): void {
  it('returns the cached actions for a controller via reflection fallback', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Act' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'cart', ['addToCart', 'clearCart']);

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerActions($class))->toBe(['addToCart', 'clearCart'])
      ->and($resolver->hasAction($class, 'addToCart'))->toBeTrue()
      ->and($resolver->hasAction($class, 'missing'))->toBeFalse();
  });

  it('returns an empty action list for an unknown class', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerActions('App\\Controllers\\Nope'))->toBe([]);
  });

  it('finds the controller that owns a given action', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Owner' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'checkout', ['placeOrder']);

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->findControllerWithAction('placeOrder'))->toBe($class)
      ->and($resolver->findControllerWithAction('nope'))->toBeNull();
  });
});

describe('processClass validation', function (): void {
  it('ignores a class that does not implement Controller', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();
    $resolver->processClass(\Tests\Fixtures\NotAController::class);

    expect($resolver->getControllerActions(\Tests\Fixtures\NotAController::class))->toBe([]);
  });

  it('registers a valid Controller passed directly to processClass', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();
    $resolver->processClass(\Tests\Fixtures\Controllers\GoodViewController::class);

    expect($resolver->resolve('view', 'good-view'))
      ->toBe(\Tests\Fixtures\Controllers\GoodViewController::class);
  });

  it('rejects a Controller that lacks a static handle property', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect(fn (): mixed => $resolver->processClass(\Tests\Fixtures\Controllers\NoHandleController::class))
      ->toThrow(ControllerRegistration::class, 'static public `handle` property');
  });

  it('rejects a Controller that does not extend Singleton', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect(fn (): mixed => $resolver->processClass(\Tests\Fixtures\Controllers\NonSingletonController::class))
      ->toThrow(ControllerRegistration::class, 'must extend');
  });

  it('does nothing for a class that does not exist', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();
    $resolver->processClass('App\\Controllers\\DoesNotExist');

    expect($resolver->getControllerRegistry())->toBe([]);
  });
});

describe('cache freshness and loading', function (): void {
  it('loads the registry from a fresh cache file without rescanning', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Fresh' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'reviews', ['listReviews']);

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver();

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);

    $reloaded = ControllerResolver::getInstance();

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeTrue()
      ->and($reloaded->getControllerActions($class))->toBe(['listReviews'])
      ->and($reloaded->resolve('view', 'reviews'))->toBe($class);
  });

  it('regenerates the cache when a controller file is newer than the cache', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Stale' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'aboutus');
    $controllerPath = $this->resolverRoot . '/App/Controllers/' . $unique . 'View.php';

    Functions\when('wp_mkdir_p')->justReturn(true);

    makeResolver();

    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    $firstCacheMtime = filemtime($cacheFile);

    touch($controllerPath, time() + 100);

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);
    resetResolverStatics();

    touch($cacheFile, time() - 50);

    $reloaded = ControllerResolver::getInstance();

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($reloaded->getControllerRegistry())->toHaveKey($class)
      ->and(filemtime($cacheFile))->toBeGreaterThanOrEqual($firstCacheMtime);
  });

  it('reports cache stats reflecting a loaded registry', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Stats' . substr(md5(uniqid('', true)), 0, 8);
    writeControllerFile($this->resolverRoot, $unique . 'View', 'gallery');

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();
    $stats = $resolver->getCacheStats();

    expect($stats)->toHaveKeys([
      'registry_loaded',
      'registry_size',
      'cache_file_exists',
      'cache_file_path',
    ])->and($stats['registry_size'])->toBe(1);
  });
});

describe('cache invalidation', function (): void {
  it('removes the cache file and clears the registry', function (): void {
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Inv' . substr(md5(uniqid('', true)), 0, 8);
    writeControllerFile($this->resolverRoot, $unique . 'View', 'faq');

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();
    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    expect($cacheFile)->toBeFile();

    $resolver->invalidateCache();

    expect($cacheFile)->not->toBeFile()
      ->and($resolver->getControllerRegistry())->toBe([]);
  });
});

describe('admin menu registration', function (): void {
  it('registers a top-level admin menu for an admin controller', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();
    $resolver->processClass(\Tests\Fixtures\Controllers\TopLevelAdminController::class);

    Functions\expect('add_menu_page')->once()
      ->with('Top Page', 'Top Menu', 'manage_options', 'top-admin', Mockery::type('callable'), 'dashicons-admin', null);

    $resolver->registerAdminMenus();
  });

  it('registers a submenu page when parent_slug is provided', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();
    $resolver->processClass(\Tests\Fixtures\Controllers\SubmenuAdminController::class);

    Functions\expect('add_submenu_page')->once()
      ->with('parent-page', 'Sub Page', 'Sub Menu', 'manage_options', 'sub-admin', Mockery::type('callable'));

    $resolver->registerAdminMenus();
  });

  it('throws when an admin controller configure() lacks required keys', function (): void {
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();
    $resolver->processClass(\Tests\Fixtures\Controllers\InvalidConfigAdminController::class);

    expect(fn (): mixed => $resolver->registerAdminMenus())
      ->toThrow(ControllerRegistration::class, "must provide 'page_title' and 'menu_title'");
  });
});
