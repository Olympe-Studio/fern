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

/**
 * Forces Fern's dev/prod mode for the current test. FlushesSingletons resets
 * Fern::$isDev back to null after every test, so this stays test-local.
 */
function setResolverDev(bool $isDev): void {
  $property = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
  $property->setValue(null, $isDev);
}

/**
 * Writes a well-formed routes cache file from raw controller entries.
 *
 * @param array<string, mixed> $controllers Raw controller registry entries.
 */
function writeCacheFile(string $root, array $controllers, ?string $version = null): string {
  $data = [
    'metadata' => [
      'generated_at' => date('Y-m-d H:i:s'),
      'timestamp' => time(),
      'version' => $version ?? \Fern\Core\Fern::VERSION,
      'controller_count' => count($controllers),
    ],
    'controllers' => $controllers,
  ];

  $path = $root . '/App/_routes_cache.fern.php';
  file_put_contents($path, '<?php return ' . var_export($data, true) . ';');

  return $path;
}

/**
 * Writes an arbitrary cache-file body (for malformed / corrupt cache cases).
 */
function writeRawCacheFile(string $root, string $body): string {
  $path = $root . '/App/_routes_cache.fern.php';
  file_put_contents($path, $body);

  return $path;
}

/**
 * Writes and loads an admin controller whose handle() returns a hijacked Reply,
 * so the generated admin-menu callback can be invoked without emitting output.
 */
function writeHijackAdminController(string $root, string $className, string $handle): string {
  $source = <<<PHP
<?php declare(strict_types=1);

namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

class {$className} extends Singleton implements Controller {
  use AdminController;

  public static string \$handle = '{$handle}';

  public function handle(Request \$request): Reply {
    \$GLOBALS['__fern_admin_cb'] = (\$GLOBALS['__fern_admin_cb'] ?? 0) + 1;

    return (new Reply(200, 'ok'))->hijack();
  }

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return ['page_title' => 'Cb Page', 'menu_title' => 'Cb Menu'];
  }
}
PHP;

  $path = $root . '/App/Controllers/' . $className . '.php';
  file_put_contents($path, $source);
  require_once $path;

  return 'App\\Controllers\\' . $className;
}

/**
 * Writes and loads an admin controller whose configure() declares submenu items.
 */
function writeSubmenuConfigAdminController(string $root, string $className, string $handle): string {
  $source = <<<PHP
<?php declare(strict_types=1);

namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;

class {$className} extends Singleton implements Controller {
  use AdminController;

  public static string \$handle = '{$handle}';

  public function handle(Request \$request): Reply {
    return (new Reply(200, 'ok'))->hijack();
  }

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return [
      'page_title' => 'Parent Page',
      'menu_title' => 'Parent Menu',
      'submenu' => [
        'not-a-valid-entry',
        ['page_title' => 'Sub Page', 'menu_title' => 'Sub Menu'],
      ],
    ];
  }
}
PHP;

  $path = $root . '/App/Controllers/' . $className . '.php';
  file_put_contents($path, $source);
  require_once $path;

  return 'App\\Controllers\\' . $className;
}

/**
 * Binds a constructor-less Request instance as the current Request singleton so
 * Request::getCurrent() does not touch WordPress globals.
 */
function bindFakeRequest(): void {
  $request = (new ReflectionClass(\Fern\Core\Services\HTTP\Request::class))->newInstanceWithoutConstructor();

  $property = new ReflectionProperty(\Fern\Core\Factory\Singleton::class, '_instances');
  $instances = $property->getValue();
  $instances = is_array($instances) ? $instances : [];
  $instances[\Fern\Core\Services\HTTP\Request::class] = $request;
  $property->setValue(null, $instances);
}

afterEach(function (): void {
  if (isset($this->resolverRoot) && is_string($this->resolverRoot)) {
    removeResolverRoot($this->resolverRoot);
  }
});

describe('boot', function (): void {
  it('scans declared classes and registers controllers in development', function (): void {
    // Runs first (before the invalid fixtures are ever loaded) so boot()'s
    // get_declared_classes() loop only sees valid controllers.
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    // Guarantee a valid controller is among the declared classes for the scan loop.
    class_exists(\Tests\Fixtures\Controllers\GoodViewController::class);

    ControllerResolver::boot();

    $resolver = ControllerResolver::getInstance();

    expect($resolver->resolve('view', 'good-view'))
      ->toBe(\Tests\Fixtures\Controllers\GoodViewController::class);
  });
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
      ->and($data['metadata']['version'])->toBe(\Fern\Core\Fern::VERSION)
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

  it('skips admin registration when the controller has no configure method', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    // GoodViewController is a Singleton Controller but exposes no configure().
    $resolver->register('admin', 'noconf', \Tests\Fixtures\Controllers\GoodViewController::class);

    Functions\expect('add_menu_page')->never();

    expect(fn (): null => $resolver->registerAdminMenus() ?? null)
      ->not->toThrow(Throwable::class);
  });

  it('invokes the generated admin menu callback to dispatch the controller', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $unique = 'Cb' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeHijackAdminController($this->resolverRoot, $unique, 'cb-admin');

    $resolver = makeResolver();
    $resolver->processClass($class);

    $captured = null;
    Functions\when('add_menu_page')->alias(function (...$args) use (&$captured): string {
      $captured = $args[4];

      return 'toplevel_page_cb-admin';
    });

    $resolver->registerAdminMenus();

    expect($captured)->toBeCallable();

    // Invoke the generated callback: it must dispatch the controller's handle().
    bindFakeRequest();
    $GLOBALS['__fern_admin_cb'] = 0;
    ($captured)();

    expect($GLOBALS['__fern_admin_cb'])->toBe(1);

    unset($GLOBALS['__fern_admin_cb']);
  });

  it('registers submenu pages declared in configure()', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    Functions\when('add_menu_page')->justReturn('toplevel_page_parent-admin');
    $unique = 'Sub' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeSubmenuConfigAdminController($this->resolverRoot, $unique, 'parent-admin');

    $resolver = makeResolver();
    $resolver->processClass($class);

    // The invalid submenu entry is skipped; the valid one registers a submenu page.
    Functions\expect('add_submenu_page')->once()
      ->with('parent-admin', 'Sub Page', 'Sub Menu', 'manage_options', '', Mockery::type('callable'));

    $resolver->registerAdminMenus();

    expect(true)->toBeTrue();
  });
});

describe('development cache freshness', function (): void {
  it('loads a fresh cache without rescanning in development', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'DevFresh' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'devfresh', ['ping']);
    $controllerPath = $this->resolverRoot . '/App/Controllers/' . $unique . 'View.php';

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver();

    // Make the cache unambiguously newer than the framework and controller files
    // so the development freshness scan returns true.
    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    $resolverFile = (new ReflectionClass(ControllerResolver::class))->getFileName();
    touch($cacheFile, max(filemtime($resolverFile), filemtime($controllerPath), time()) + 100);

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);

    $reloaded = ControllerResolver::getInstance();

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeTrue()
      ->and($reloaded->getControllerActions($class))->toBe(['ping'])
      ->and($reloaded->resolve('view', 'devfresh'))->toBe($class);
  });

  it('treats the cache as fresh when there is no controllers directory in development', function (): void {
    setResolverDev(true);
    $root = sys_get_temp_dir() . '/fern-resolver-' . uniqid('', true);
    mkdir($root . '/App', 0777, true);
    Config::getInstance()->setConfig(['root' => $root]);
    $this->resolverRoot = $root;

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver();

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $root]);

    $reloaded = ControllerResolver::getInstance();

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeTrue()
      ->and($reloaded->getControllerRegistry())->toBe([]);
  });

  it('rejects the cache when a framework file is newer in development', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'DevCrit' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'devcrit');

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver();

    // Make the cache older than the real ControllerResolver.php source file.
    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    $resolverFile = (new ReflectionClass(ControllerResolver::class))->getFileName();
    touch($cacheFile, filemtime($resolverFile) - 100);

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);
    resetResolverStatics();

    $reloaded = ControllerResolver::getInstance();

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($reloaded->getControllerRegistry())->toHaveKey($class);
  });

  it('rejects the cache when a controller file is newer in development', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'DevCtrl' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'devctrl');
    $controllerPath = $this->resolverRoot . '/App/Controllers/' . $unique . 'View.php';

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver();

    // Cache newer than framework files, but the controller newer than the cache.
    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    $resolverFile = (new ReflectionClass(ControllerResolver::class))->getFileName();
    $base = max(filemtime($resolverFile), time()) + 100;
    touch($cacheFile, $base);
    touch($controllerPath, $base + 100);

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);
    resetResolverStatics();

    $reloaded = ControllerResolver::getInstance();

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($reloaded->getControllerRegistry())->toHaveKey($class);
  });
});

describe('cache file validation', function (): void {
  it('returns from the cache when the registry is already loaded', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Loaded' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'loaded');

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver(); // scans + generates; registryLoaded stays false

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);
    ControllerResolver::getInstance(); // loads from cache; registryLoaded becomes true

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);
    $third = ControllerResolver::getInstance(); // short-circuits on the loaded registry

    expect($third->getCacheStats()['registry_loaded'])->toBeTrue()
      ->and($third->getControllerRegistry())->toHaveKey($class);
  });

  it('rejects a cache with a malformed structure', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Malf' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'malf');

    Functions\when('wp_mkdir_p')->justReturn(true);

    writeRawCacheFile($this->resolverRoot, '<?php return ' . var_export([
      'metadata' => ['version' => \Fern\Core\Fern::VERSION, 'timestamp' => time()],
      'controllers' => 'not-an-array',
    ], true) . ';');

    $resolver = ControllerResolver::getInstance();

    expect($resolver->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($resolver->getControllerRegistry())->toHaveKey($class);
  });

  it('rejects a cache generated by a different framework version', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Ver' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'ver');

    Functions\when('wp_mkdir_p')->justReturn(true);

    writeCacheFile($this->resolverRoot, [
      'App\\Controllers\\PhantomVer' => [
        'type' => 'view', 'handle' => 'phantom', 'traits' => [], 'actions' => [],
        'file_path' => '/nope', 'file_mtime' => 1,
      ],
    ], '0.0.0-stale');

    $resolver = ControllerResolver::getInstance();

    expect($resolver->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($resolver->getControllerRegistry())->toHaveKey($class)
      ->and($resolver->getControllerRegistry())->not->toHaveKey('App\\Controllers\\PhantomVer');
  });

  it('rejects a cache entry that is missing its file mtime', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Mtime' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'mtime');

    Functions\when('wp_mkdir_p')->justReturn(true);

    writeCacheFile($this->resolverRoot, [
      'App\\Controllers\\PhantomMtime' => [
        'type' => 'view', 'handle' => 'phantom', 'traits' => [], 'actions' => [],
        'file_path' => '/nope',
      ],
    ]);

    $resolver = ControllerResolver::getInstance();

    expect($resolver->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($resolver->getControllerRegistry())->toHaveKey($class);
  });

  it('resolves a cached controller path by convention when file_path is empty', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Conv' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'conv');
    $controllerPath = $this->resolverRoot . '/App/Controllers/' . $unique . 'View.php';

    Functions\when('wp_mkdir_p')->justReturn(true);

    writeCacheFile($this->resolverRoot, [
      $class => [
        'type' => 'view', 'handle' => 'conv', 'traits' => [], 'actions' => ['ping'],
        'file_path' => '',
        'file_mtime' => filemtime($controllerPath),
      ],
    ]);

    $resolver = ControllerResolver::getInstance();

    expect($resolver->getCacheStats()['registry_loaded'])->toBeTrue()
      ->and($resolver->resolve('view', 'conv'))->toBe($class);
  });

  it('deletes and ignores a corrupt cache that throws on include', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();

    Functions\when('wp_mkdir_p')->justReturn(true);

    $cacheFile = writeRawCacheFile($this->resolverRoot, "<?php throw new \\RuntimeException('corrupt cache');");

    $resolver = ControllerResolver::getInstance();

    expect($resolver->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($cacheFile)->toBeFile()
      ->and(file_get_contents($cacheFile))->not->toContain('throw new');
  });

  it('ignores an unreadable cache file', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $unique = 'Unread' . substr(md5(uniqid('', true)), 0, 8);
    $class = writeControllerFile($this->resolverRoot, $unique . 'View', 'unread');

    Functions\when('wp_mkdir_p')->justReturn(true);
    makeResolver();

    $cacheFile = $this->resolverRoot . '/App/_routes_cache.fern.php';
    chmod($cacheFile, 0222); // write-only: is_readable() is false, is_writable() is true

    \Fern\Core\Factory\Singleton::flushInstances();
    Config::getInstance()->setConfig(['root' => $this->resolverRoot]);
    resetResolverStatics();

    $reloaded = ControllerResolver::getInstance();
    chmod($cacheFile, 0644);

    expect($reloaded->getCacheStats()['registry_loaded'])->toBeFalse()
      ->and($reloaded->getControllerRegistry())->toHaveKey($class);
  });

  it('drops a stale cache when a cached class no longer exists in development', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();

    Functions\when('wp_mkdir_p')->justReturn(true);

    $marker = $this->resolverRoot . '/App/Controllers/_ghost_marker.php';
    file_put_contents($marker, '<?php // marker');

    $ghostClass = 'App\\Controllers\\GhostNeverDefined' . substr(md5(uniqid('', true)), 0, 8);
    $cacheFile = writeCacheFile($this->resolverRoot, [
      $ghostClass => [
        'type' => 'view', 'handle' => 'ghost', 'traits' => [], 'actions' => [],
        'file_path' => $marker,
        'file_mtime' => filemtime($marker),
      ],
    ]);

    // Cache newer than every scanned file so the dev freshness scan passes.
    $resolverFile = (new ReflectionClass(ControllerResolver::class))->getFileName();
    touch($cacheFile, max(filemtime($marker), filemtime($resolverFile)) + 100);

    $resolver = ControllerResolver::getInstance();

    expect($cacheFile)->not->toBeFile()
      ->and($resolver->getCacheStats()['registry_loaded'])->toBeFalse();
  });
});

describe('generation edge cases', function (): void {
  it('creates the App directory when it does not exist', function (): void {
    setResolverDev(false);
    $root = sys_get_temp_dir() . '/fern-resolver-' . uniqid('', true);
    mkdir($root, 0777, true);
    Config::getInstance()->setConfig(['root' => $root]);
    $this->resolverRoot = $root;

    Functions\when('wp_mkdir_p')->alias(fn (string $dir): bool => is_dir($dir) || mkdir($dir, 0777, true));

    makeResolver();

    expect($root . '/App/_routes_cache.fern.php')->toBeFile();
  });

  it('skips a scanned file whose class is not a Controller', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    $className = 'PlainScan' . substr(md5(uniqid('', true)), 0, 8);
    $path = $this->resolverRoot . '/App/Controllers/' . $className . '.php';
    file_put_contents($path, "<?php declare(strict_types=1);\nnamespace App\\Controllers;\nclass {$className} {}\n");

    Functions\when('wp_mkdir_p')->justReturn(true);

    $resolver = makeResolver();

    expect($resolver->getControllerRegistry())->toBe([]);
  });
});

describe('lazy controller loading in development', function (): void {
  it('lazily loads an unregistered controller class on resolve', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    $resolver->register('view', 'lazyview', 'App\\Controllers\\LazyViewGhost');

    expect($resolver->resolve('view', 'lazyview'))->toBe('App\\Controllers\\LazyViewGhost');
  });

  it('lazily loads the default controller class', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    $resolver->register('default', '_default', 'App\\Controllers\\DefaultGhost');

    expect($resolver->getDefaultController())->toBe('App\\Controllers\\DefaultGhost');
  });

  it('lazily loads the 404 controller class', function (): void {
    setResolverDev(true);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    $resolver->register('_404', '_404', 'App\\Controllers\\NotFoundGhost');

    expect($resolver->get404Controller())->toBe('App\\Controllers\\NotFoundGhost');
  });
});

describe('reflection and type caches', function (): void {
  it('reuses cached reflection and controller type on repeated processClass in production', function (): void {
    setResolverDev(false);
    $this->resolverRoot = makeResolverRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);
    $resolver = makeResolver();

    $resolver->processClass(\Tests\Fixtures\Controllers\GoodViewController::class);
    $resolver->processClass(\Tests\Fixtures\Controllers\GoodViewController::class);

    expect($resolver->resolve('view', 'good-view'))
      ->toBe(\Tests\Fixtures\Controllers\GoodViewController::class);
  });
});
