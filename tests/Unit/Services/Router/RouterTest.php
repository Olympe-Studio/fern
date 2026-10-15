<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Errors\RouterException;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Services\Controller\ControllerResolver;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Router\Router;
use Tests\Fixtures\FakeRequest;

if (!defined('ABSPATH')) {
  define('ABSPATH', '/tmp/fern-test-abspath/');
}

/**
 * Configurable Request double used to drive the Router through every branch.
 * Every predicate the Router consults is overridable per test, with neutral
 * defaults (plain GET page request, no stop conditions, not a 404).
 */
final class RouterFakeRequest extends FakeRequest {
  /** @var array<string, mixed> */
  public array $opts;

  /**
   * @param array<string, mixed> $opts
   */
  public function __construct(array $opts = []) {
    $this->opts = array_merge([
      'method' => 'GET',
      'action' => false,
      'rest' => false,
      'ajax' => false,
      'cron' => false,
      'cli' => false,
      'xmlrpc' => false,
      'autosave' => false,
      'sitemap' => false,
      'is404' => false,
      'attachment' => false,
      'author' => false,
      'tag' => false,
      'category' => false,
      'date' => false,
      'feed' => false,
      'search' => false,
      'term' => false,
      'archive' => false,
      'currentId' => -1,
      'postType' => null,
      'taxonomy' => null,
      'urlParams' => [],
      'body' => [],
      'contentType' => 'application/json',
    ], $opts);
  }

  public function getBody(): mixed {
    return $this->opts['body'];
  }

  public function getContentType(): string {
    return (string) $this->opts['contentType'];
  }

  public function getMethod(): string {
    return (string) $this->opts['method'];
  }

  public function isGet(): bool {
    return $this->getMethod() === 'GET';
  }

  public function isPost(): bool {
    return $this->getMethod() === 'POST';
  }

  public function isAction(): bool {
    return (bool) $this->opts['action'];
  }

  public function isREST(): bool {
    return (bool) $this->opts['rest'];
  }

  public function isAjax(): bool {
    return (bool) $this->opts['ajax'];
  }

  public function isCRON(): bool {
    return (bool) $this->opts['cron'];
  }

  public function isCLI(): bool {
    return (bool) $this->opts['cli'];
  }

  public function isXMLRPC(): bool {
    return (bool) $this->opts['xmlrpc'];
  }

  public function isAutoSave(): bool {
    return (bool) $this->opts['autosave'];
  }

  public function isSitemap(): bool {
    return (bool) $this->opts['sitemap'];
  }

  public function is404(): bool {
    return (bool) $this->opts['is404'];
  }

  public function isAttachment(): bool {
    return (bool) $this->opts['attachment'];
  }

  public function isAuthor(): bool {
    return (bool) $this->opts['author'];
  }

  public function isTag(): bool {
    return (bool) $this->opts['tag'];
  }

  public function isCategory(): bool {
    return (bool) $this->opts['category'];
  }

  public function isDate(): bool {
    return (bool) $this->opts['date'];
  }

  public function isFeed(): bool {
    return (bool) $this->opts['feed'];
  }

  public function isSearch(): bool {
    return (bool) $this->opts['search'];
  }

  public function isTerm(): bool {
    return (bool) $this->opts['term'];
  }

  public function isArchive(): bool {
    return (bool) $this->opts['archive'];
  }

  public function getCurrentId(): int {
    return (int) $this->opts['currentId'];
  }

  public function getPostType(): ?string {
    $value = $this->opts['postType'];

    return is_string($value) ? $value : null;
  }

  public function getTaxonomy(): ?string {
    $value = $this->opts['taxonomy'];

    return is_string($value) ? $value : null;
  }

  public function getUrlParam(string $key): mixed {
    return $this->opts['urlParams'][$key] ?? null;
  }

  public function getAction(): Action {
    return Action::getCurrent();
  }
}

/**
 * Points the framework root at a throwaway directory with an App/Controllers
 * tree and binds it as the active Config root.
 */
function makeRouterRoot(): string {
  $root = sys_get_temp_dir() . '/fern-router-' . uniqid('', true);
  mkdir($root . '/App/Controllers', 0777, true);
  Config::getInstance()->setConfig(['root' => $root]);

  return $root;
}

/**
 * Recursively deletes a directory tree.
 */
function removeRouterRoot(string $root): void {
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
 * The handle() method returns a hijacked Reply so send() is a no-op.
 *
 * @param array<int, string> $methods Extra public action methods to declare.
 */
function writeRouterController(
  string $root,
  string $className,
  string $handle,
  array $methods = [],
  string $extra = '',
): string {
  $methodSource = '';
  foreach ($methods as $method) {
    $methodSource .= "  public function {$method}(\\Fern\\Core\\Services\\HTTP\\Request \$request, \\Fern\\Core\\Services\\Actions\\Action \$action): \\Fern\\Core\\Services\\HTTP\\Reply { return (new Reply(200, 'ok'))->hijack(); }\n";
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
    return (new Reply(200, 'handled'))->hijack();
  }

{$methodSource}}
PHP;

  $path = $root . '/App/Controllers/' . $className . '.php';
  file_put_contents($path, $source);

  return 'App\\Controllers\\' . $className;
}

/**
 * Injects the given request double as the current Request singleton.
 */
function bindRouterRequest(RouterFakeRequest $request): void {
  $property = new ReflectionProperty(Singleton::class, '_instances');
  $instances = $property->getValue();
  $instances = is_array($instances) ? $instances : [];
  $instances[Request::class] = $request;
  $property->setValue(null, $instances);
}

/**
 * Builds the Router after the controllers have been scanned and the request
 * double bound. Returns the Router instance.
 */
function makeRouter(): Router {
  return Router::getInstance();
}

/**
 * Marker exception thrown from a `fern:core:reply:headers` filter so that the
 * Router's internal Reply::send() calls (400/500) abort before hitting the
 * process-terminating exit() seam, letting the test observe the reply.
 */
final class RouterHaltSend extends RuntimeException {}

/**
 * A controller-like Singleton with no handle() method, used to exercise the
 * Router's defensive "instance has no handle" branches.
 */
final class RouterNoHandleController extends Singleton {}

/**
 * Configurable ControllerResolver double. The real resolver never returns null
 * (it throws), so this stub is used to drive the Router's documented null
 * contracts (pass back to WordPress / let WordPress render the 404).
 */
final class RouterStubResolver extends ControllerResolver {
  public ?string $notFoundController = null;

  public ?string $defaultController = null;

  public ?string $resolvedController = null;

  public function get404Controller(): ?string {
    return $this->notFoundController;
  }

  public function getDefaultController(): ?string {
    return $this->defaultController;
  }

  public function resolve(string $type, string $handle): ?string {
    return $this->resolvedController;
  }

  public function findControllerWithAction(string $actionName): ?string {
    return null;
  }

  public function hasAction(string $controllerClass, string $actionName): bool {
    return false;
  }
}

/**
 * Injects a ControllerResolver double as the active singleton. Must run before
 * the Router is instantiated (the Router caches its resolver in the constructor).
 */
function bindRouterResolver(ControllerResolver $resolver): void {
  $property = new ReflectionProperty(Singleton::class, '_instances');
  $instances = $property->getValue();
  $instances = is_array($instances) ? $instances : [];
  $instances[ControllerResolver::class] = $resolver;
  $property->setValue(null, $instances);
}

/**
 * Instantiates a RouterStubResolver without running the scanning constructor.
 */
function makeStubResolver(): RouterStubResolver {
  /** @var RouterStubResolver $resolver */
  $resolver = (new ReflectionClass(RouterStubResolver::class))->newInstanceWithoutConstructor();

  return $resolver;
}

/**
 * Sets the current Action singleton. A null name leaves the action a "bad
 * request"; any other value (including '') is applied via setName().
 */
function setRouterAction(RouterFakeRequest $request, ?string $name): Action {
  $action = new Action($request);

  if ($name !== null) {
    $action->setName($name);
  }

  (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

  return $action;
}

/**
 * Registers a `fern:core:reply:headers` filter that captures the reply then
 * throws RouterHaltSend, so the Router's non-hijacked Reply::send() calls abort
 * before exit(). Returns a holder whose ->reply is the captured Reply.
 */
function haltReplySend(): stdClass {
  $capture = new stdClass();
  $capture->reply = null;

  Filters\expectApplied('fern:core:reply:headers')
    ->zeroOrMoreTimes()
    ->andReturnUsing(function ($reply) use ($capture) {
      $capture->reply = $reply;

      throw new RouterHaltSend('halted');
    });

  return $capture;
}

/**
 * Invokes the private Router::handleActionRequest() with the given controller.
 */
function invokeHandleActionRequest(Router $router, string $controller): void {
  (new ReflectionMethod(Router::class, 'handleActionRequest'))->invoke($router, $controller);
}

/**
 * Brain Monkey cannot undefine wp_generate_uuid4 once another test file (e.g.
 * ApiTest) has stubbed it. When it is already defined we must provide an
 * expectation so createErrorId() does not raise MissingFunctionExpectations;
 * when it is not (this file in isolation) we leave it undefined so createErrorId
 * exercises its uniqid() fallback branch.
 */
function guardUuidLeak(): void {
  if (function_exists('wp_generate_uuid4')) {
    Functions\when('wp_generate_uuid4')->justReturn('router-guard-uuid');
  }
}

afterEach(function (): void {
  if (isset($this->routerRoot) && is_string($this->routerRoot)) {
    removeRouterRoot($this->routerRoot);
  }
});

beforeEach(function (): void {
  Functions\when('wp_mkdir_p')->justReturn(true);
  Functions\when('get_option')->justReturn('UTF-8');
});

describe('boot', function (): void {
  it('boots the resolver and hooks template_include, admin_ and admin_menu', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest());

    $filters = [];
    $actions = [];
    Functions\when('add_filter')->alias(function (string $hook) use (&$filters): bool {
      $filters[] = $hook;
      return true;
    });
    Functions\when('add_action')->alias(function (string $hook) use (&$actions): bool {
      $actions[] = $hook;
      return true;
    });

    Router::boot();

    expect($filters)->toContain('template_include')
      ->and($filters)->toContain('admin_')
      ->and($actions)->toContain('admin_menu')
      ->and($filters)->not->toContain('admin_init');
  });

  it('registers the admin_init hook when the request is an action', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['action' => true]));

    $filters = [];
    Functions\when('add_filter')->alias(function (string $hook, $cb, int $priority = 10) use (&$filters): bool {
      $filters[$hook] = $priority;
      return true;
    });
    Functions\when('add_action')->justReturn(true);

    Router::boot();

    expect($filters)->toHaveKey('admin_init')
      ->and($filters['admin_init'])->toBe(99)
      ->and($filters)->toHaveKey('template_include');
  });
});

describe('passed and didPass', function (): void {
  it('defaults didPass to false on a fresh Router', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest());

    expect(makeRouter()->didPass)->toBeFalse()
      ->and(Router::passed())->toBeFalse();
  });

  it('flips passed() to true when shouldStop short-circuits resolve', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['rest' => true]));

    $router = makeRouter();
    $router->resolve();

    expect(Router::passed())->toBeTrue()
      ->and($router->didPass)->toBeTrue();
  });
});

describe('shouldStop branches', function (): void {
  it('stops on a REST request and passes back to WordPress', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['rest' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('stops on an AJAX request that is not an action', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['ajax' => true, 'action' => false]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('does not stop on an AJAX request that is an action', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'AjaxActionView', 'widget', ['ajaxThing']);
    writeRouterController($this->routerRoot, 'AjaxDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'ajax' => true,
      'action' => true,
      'method' => 'POST',
      'postType' => 'widget',
      'body' => ['action' => 'ajaxThing'],
    ]);
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('ajaxThing');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    Filters\expectApplied('fern:core:action:can_run')->andReturn(true);

    $router = makeRouter();

    expect($router->resolveController())->toBe('App\\Controllers\\AjaxActionView');

    $router->resolve();

    expect($router->didPass)->toBeFalse();
  });

  it('stops on a CRON request', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['cron' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('stops on a CLI request', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['cli' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('stops on an XML-RPC request', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['xmlrpc' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('stops on an autosave request', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['autosave' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('stops on a sitemap request', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['sitemap' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });

  it('stops when there is no queried object and it is neither an action nor a 404', function (): void {
    $this->routerRoot = makeRouterRoot();
    Functions\when('get_queried_object')->justReturn(null);
    bindRouterRequest(new RouterFakeRequest(['action' => false, 'is404' => false]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });
});

describe('should404 branches', function (): void {
  it('renders the 404 controller when the request is a 404', function (): void {
    $this->routerRoot = makeRouterRoot();
    $notFound = writeRouterController($this->routerRoot, 'NotFoundView', '_404');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest(['is404' => true]));

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeFalse();
  });

  it('treats an attachment request as a 404', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'AttachNotFound', '_404');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest(['attachment' => true]));

    $router = makeRouter();

    expect(fn (): mixed => $router->resolve())->not->toThrow(Exception::class);
  });

  it('treats an author archive as a 404 by default', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'AuthorNotFound', '_404');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest(['author' => true]));

    $router = makeRouter();
    $router->handle404();

    expect(true)->toBeTrue();
  });

  it('does not 404 on a tag archive when tag_archive is disabled in config', function (): void {
    $root = makeRouterRoot();
    Config::getInstance()->setConfig([
      'root' => $root,
      'core' => ['routes' => ['disable' => ['tag_archive' => false]]],
    ]);
    $this->routerRoot = $root;
    writeRouterController($root, 'TagView', 'page');
    writeRouterController($root, 'TagDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest([
      'tag' => true,
      'archive' => false,
      'postType' => 'page',
    ]));

    $router = makeRouter();

    expect($router->getConfig())->toBe(['disable' => ['tag_archive' => false]]);

    expect(fn (): mixed => $router->resolve())
      ->toThrow(RouterException::class, 'Controller handle method must return a Reply object');
  });

  it('does not 404 for an action request even with 404 flags set', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ActionNo404View', 'doodad', ['stillRuns']);
    writeRouterController($this->routerRoot, 'ActionNo404Default', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'is404' => true,
      'method' => 'POST',
      'postType' => 'doodad',
      'body' => ['action' => 'stillRuns'],
    ]);
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('stillRuns');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    Filters\expectApplied('fern:core:action:can_run')->andReturn(true);

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);

    expect(fn (): mixed => $router->resolve())->not->toThrow(Exception::class);
  });
});

describe('handle404', function (): void {
  it('throws ControllerRegistration when no 404 controller is registered', function (): void {
    $this->routerRoot = makeRouterRoot();
    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest());

    $router = makeRouter();

    expect(fn (): mixed => $router->handle404())
      ->toThrow(\Fern\Core\Errors\ControllerRegistration::class, 'No NotFound controller registered');
  });

  it('invokes the registered 404 controller and sends a hijacked reply', function (): void {
    $this->routerRoot = makeRouterRoot();
    $notFound = writeRouterController($this->routerRoot, 'Real404View', '_404');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest());

    $router = makeRouter();

    expect($router->handle404())->toBeNull()
      ->and(ControllerResolver::getInstance()->get404Controller())->toBe($notFound);
  });
});

describe('handleGetRequest', function (): void {
  it('dispatches a GET request to the matching view controller', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ProductView', 'product');
    writeRouterController($this->routerRoot, 'GetDefault', '_default');

    $post = new stdClass();
    Functions\when('get_queried_object')->justReturn($post);

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => 42,
      'postType' => 'product',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);

    expect(fn (): mixed => $router->resolve())
      ->toThrow(RouterException::class, 'Controller handle method must return a Reply object');
  });

  it('falls back to the default controller for an unhandled page type', function (): void {
    $this->routerRoot = makeRouterRoot();
    $default = writeRouterController($this->routerRoot, 'PageDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'page',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($default);

    expect(fn (): mixed => $router->resolve())
      ->toThrow(RouterException::class, 'Controller handle method must return a Reply object');
  });
});

describe('resolveController', function (): void {
  it('resolves by ID first when a controller is registered for that ID', function (): void {
    $this->routerRoot = makeRouterRoot();
    $idController = writeRouterController($this->routerRoot, 'Id99View', '99');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => 99,
      'postType' => 'product',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($idController);
  });

  it('caches the resolution result for repeated calls', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'CachedView', 'cachedtype');
    writeRouterController($this->routerRoot, 'CachedDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'cachedtype',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class)
      ->and($router->resolveController())->toBe($class);
  });

  it('resolves a registered controller by post type when no ID match exists', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'KitView', 'kit');
    writeRouterController($this->routerRoot, 'KitDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'kit',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);
  });

  it('falls back to the default controller for an unhandled post type', function (): void {
    $this->routerRoot = makeRouterRoot();
    $default = writeRouterController($this->routerRoot, 'UnhandledDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'ghost',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($default);
  });

  it('resolves a term controller by taxonomy', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'CatTaxView', 'product_cat');
    writeRouterController($this->routerRoot, 'TaxDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'term' => true,
      'taxonomy' => 'product_cat',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);
  });

  it('throws a RouterException when the resolve_id filter yields an invalid id', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'BadIdDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    Filters\expectApplied('fern:core:router:resolve_id')->andReturn(-5);

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => 7,
      'postType' => 'product',
    ]));

    $router = makeRouter();

    expect(fn (): mixed => $router->resolveController())
      ->toThrow(RouterException::class, 'Invalid ID');
  });

  it('falls back to taxonomy resolution when resolve_id returns null', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'NullIdTaxView', 'brand');
    writeRouterController($this->routerRoot, 'NullIdDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    Filters\expectApplied('fern:core:router:resolve_id')->andReturn(null);

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => 7,
      'term' => true,
      'taxonomy' => 'brand',
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);
  });
});

describe('archive resolution', function (): void {
  it('resolves an archive controller via the archive_{type} handle', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ArchiveBookView', 'archive_book');
    writeRouterController($this->routerRoot, 'ArchiveDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    Functions\when('is_home')->justReturn(false);
    Functions\when('get_option')->justReturn(0);

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'book',
      'archive' => true,
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);
  });

  it('resolves the archive page by id for the blog (is_home) page', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'BlogPageView', '77');
    writeRouterController($this->routerRoot, 'BlogDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    Functions\when('is_home')->justReturn(true);
    Functions\when('get_option')->justReturn(77);

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'post',
      'archive' => true,
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);
  });
});

describe('handleActionRequest', function (): void {
  it('invokes the matching action method on a POST action request', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ActionableView', 'gizmo', ['doThing']);
    writeRouterController($this->routerRoot, 'ActionableDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
      'postType' => 'gizmo',
    ]);
    $request->opts['body'] = ['action' => 'doThing'];
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('doThing');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    Filters\expectApplied('fern:core:action:can_run')->andReturn(true);

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);

    expect(fn (): mixed => $router->resolve())->not->toThrow(Exception::class);
  });

  it('recognises a bad action request (no action name) as such', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'BadActionView', 'page');
    writeRouterController($this->routerRoot, 'BadActionDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
      'postType' => 'page',
    ]);
    bindRouterRequest($request);

    $action = new Action($request);
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    expect($action->isBadRequest())->toBeTrue();
  });

  it('marks reserved and underscore-prefixed names as reserved/magic methods', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'ReservedView', 'page');
    bindRouterRequest(new RouterFakeRequest());

    $router = makeRouter();

    $method = new ReflectionMethod(Router::class, 'isReservedOrMagicMethod');

    expect($method->invoke($router, 'handle'))->toBeTrue()
      ->and($method->invoke($router, 'init'))->toBeTrue()
      ->and($method->invoke($router, 'configure'))->toBeTrue()
      ->and($method->invoke($router, '_private'))->toBeTrue()
      ->and($method->invoke($router, 'doThing'))->toBeFalse();
  });
});

describe('resolveAdminActions', function (): void {
  it('returns early when shouldStop is true', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest(['rest' => true]));

    $router = makeRouter();

    expect(fn (): mixed => $router->resolveAdminActions())->not->toThrow(Exception::class);
  });

  it('resolves the admin controller from the page url param and dispatches the action', function (): void {
    $this->routerRoot = makeRouterRoot();
    $extra = "  use \\Fern\\Core\\Services\\Controller\\AdminController;\n  public function configure(): array { return ['page_title' => 'P', 'menu_title' => 'M']; }\n  public function adminAction(\\Fern\\Core\\Services\\HTTP\\Request \$request, \\Fern\\Core\\Services\\Actions\\Action \$action): \\Fern\\Core\\Services\\HTTP\\Reply { return (new Reply(200, 'ok'))->hijack(); }\n";
    $class = writeRouterController($this->routerRoot, 'SettingsAdmin', 'fern-settings', [], $extra);

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
      'urlParams' => ['page' => 'fern-settings'],
    ]);
    $request->opts['body'] = ['action' => 'adminAction'];
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('adminAction');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    Filters\expectApplied('fern:core:action:can_run')->andReturn(true);

    $router = makeRouter();

    expect(ControllerResolver::getInstance()->resolve('admin', 'fern-settings'))->toBe($class);

    expect(fn (): mixed => $router->resolveAdminActions())->not->toThrow(Exception::class);
  });

  it('infers the admin controller from the action name when no page param is present', function (): void {
    $this->routerRoot = makeRouterRoot();
    $extra = "  use \\Fern\\Core\\Services\\Controller\\AdminController;\n  public function configure(): array { return ['page_title' => 'P', 'menu_title' => 'M']; }\n  public function ajaxOnlyAction(\\Fern\\Core\\Services\\HTTP\\Request \$request, \\Fern\\Core\\Services\\Actions\\Action \$action): \\Fern\\Core\\Services\\HTTP\\Reply { return (new Reply(200, 'ok'))->hijack(); }\n";
    $class = writeRouterController($this->routerRoot, 'HeadlessAdmin', 'headless-admin', [], $extra);

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
    ]);
    $request->opts['body'] = ['action' => 'ajaxOnlyAction'];
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('ajaxOnlyAction');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    $router = makeRouter();

    expect(ControllerResolver::getInstance()->findControllerWithAction('ajaxOnlyAction'))->toBe($class);
  });

  it('resolves a null admin controller when no page param and no controller owns the action', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'EmptyAdminDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
    ]);
    $request->opts['body'] = ['action' => 'orphanAction'];
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('orphanAction');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    $router = makeRouter();

    expect($router->resolveController('admin'))->toBeNull();
  });

  it('passes the unresolved (null) admin controller down to handleActionRequest, which type-errors', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'TypeErrAdminDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
    ]);
    $request->opts['body'] = ['action' => 'orphanAction'];
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('orphanAction');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    $router = makeRouter();

    expect(fn (): mixed => $router->resolveAdminActions())->toThrow(TypeError::class);
  });

  it('logs a missing action in dev mode and still resolves a null admin controller', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'DevMissDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
    ]);
    $request->opts['body'] = ['action' => 'devMissingAction'];
    bindRouterRequest($request);

    $action = new Action($request);
    $action->setName('devMissingAction');
    (new ReflectionProperty(Action::class, 'current'))->setValue(null, $action);

    $devProp = new ReflectionProperty(Fern::class, 'isDev');
    $devProp->setValue(null, true);

    $router = makeRouter();

    try {
      expect($router->resolveController('admin'))->toBeNull();
    } finally {
      $devProp->setValue(null, null);
    }
  });
});

describe('boot closures', function (): void {
  it('runs the template_include and admin_init closures registered during boot', function (): void {
    $this->routerRoot = makeRouterRoot();
    // rest => true makes resolve()/resolveAdminActions() short-circuit via
    // shouldStop() (no controllers, no exiting Reply::send()); action => true
    // makes boot() register the admin_init hook.
    bindRouterRequest(new RouterFakeRequest(['action' => true, 'rest' => true]));

    $callbacks = [];
    Functions\when('add_filter')->alias(function (string $hook, $cb = null) use (&$callbacks): bool {
      $callbacks[$hook] = $cb;
      return true;
    });
    Functions\when('add_action')->justReturn(true);

    Router::boot();

    expect($callbacks)->toHaveKey('template_include')
      ->and($callbacks)->toHaveKey('admin_init');

    // Execute the closure bodies that boot() only registered.
    ($callbacks['template_include'])(null);
    ($callbacks['admin_init'])();

    expect(Router::getInstance()->didPass)->toBeTrue();
  });
});

describe('resolve pass-through', function (): void {
  it('sets didPass when the resolver yields no controller for the view', function (): void {
    $this->routerRoot = makeRouterRoot();

    Functions\when('get_queried_object')->justReturn(new stdClass());

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'ghost',
    ]));

    $resolver = makeStubResolver();
    $resolver->defaultController = null;
    $resolver->resolvedController = null;
    bindRouterResolver($resolver);

    $router = makeRouter();
    $router->resolve();

    expect($router->didPass)->toBeTrue();
  });
});

describe('handle404 resolver contracts', function (): void {
  it('returns and lets WordPress render the 404 when the resolver has no 404 controller', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest());

    $resolver = makeStubResolver();
    $resolver->notFoundController = null;
    bindRouterResolver($resolver);

    $router = makeRouter();

    expect($router->handle404())->toBeNull();
  });

  it('throws when the 404 controller instance has no handle method', function (): void {
    $this->routerRoot = makeRouterRoot();
    bindRouterRequest(new RouterFakeRequest());

    $resolver = makeStubResolver();
    $resolver->notFoundController = RouterNoHandleController::class;
    bindRouterResolver($resolver);

    $router = makeRouter();

    expect(fn (): mixed => $router->handle404())
      ->toThrow(RouterException::class, 'Controller handle method must return a Reply object');
  });
});

describe('getArchivePageId WooCommerce branches', function (): void {
  it('derives the archive page id from the active WooCommerce page', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ShopArchiveView', '88');
    writeRouterController($this->routerRoot, 'ShopArchiveDefault', '_default');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    Functions\when('wc_get_page_id')->justReturn(88);
    Functions\when('is_shop')->justReturn(true);
    Functions\when('is_account_page')->justReturn(true);
    Functions\when('is_cart')->justReturn(true);
    Functions\when('is_checkout')->justReturn(true);
    Functions\when('is_home')->justReturn(false);

    bindRouterRequest(new RouterFakeRequest([
      'currentId' => -1,
      'postType' => 'product',
      'archive' => true,
    ]));

    $router = makeRouter();

    expect($router->resolveController())->toBe($class);
  });
});

describe('handleAdminController inference', function (): void {
  it('returns the controller inferred from the action name when no page param is present', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'InferredAdminView', 'inferred-admin', ['adminThing']);

    $request = new RouterFakeRequest([
      'action' => true,
      'method' => 'POST',
      'body' => ['action' => 'adminThing'],
    ]);
    bindRouterRequest($request);
    setRouterAction($request, 'adminThing');

    $router = makeRouter();

    expect($router->resolveController('admin'))->toBe($class);
  });
});

describe('handleGetRequest defensive branches', function (): void {
  it('falls back to handle404 when the instance has no handle and the request is a 404', function (): void {
    $this->routerRoot = makeRouterRoot();
    writeRouterController($this->routerRoot, 'GetFallback404', '_404');

    Functions\when('get_queried_object')->justReturn(new stdClass());
    bindRouterRequest(new RouterFakeRequest(['is404' => true]));

    $router = makeRouter();

    $handleGet = new ReflectionMethod(Router::class, 'handleGetRequest');

    expect(fn (): mixed => $handleGet->invoke($router, RouterNoHandleController::class))
      ->not->toThrow(Exception::class);
  });
});

describe('action error handling', function (): void {
  // NOTE ON ORDER: the single test that stubs wp_generate_uuid4 (to cover the
  // uuid branch of createErrorId) MUST run last. Brain Monkey cannot undefine a
  // function once stubbed, so any later createErrorId() call would invoke the
  // now-defined-but-unexpected function and throw MissingFunctionExpectations.
  it('sends a 400 reply for a bad action request', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'BadReqActionCtrl', 'badreq');

    $request = new RouterFakeRequest(['action' => true, 'method' => 'POST']);
    bindRouterRequest($request);
    setRouterAction($request, null); // null name => bad request

    $capture = haltReplySend();

    $router = makeRouter();

    expect(fn (): mixed => invokeHandleActionRequest($router, $class))
      ->toThrow(RouterHaltSend::class);

    expect($capture->reply)->toBeInstanceOf(Reply::class)
      ->and($capture->reply->getBody())->toBe('Bad Request');
  });

  it('returns a 500 (uniqid error id) when the action name is a reserved method', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ReservedNameActionCtrl', 'reservedname');

    $request = new RouterFakeRequest(['action' => true, 'method' => 'POST']);
    bindRouterRequest($request);
    setRouterAction($request, 'handle');

    guardUuidLeak();
    $capture = haltReplySend();

    $router = makeRouter();

    expect(fn (): mixed => invokeHandleActionRequest($router, $class))
      ->toThrow(RouterHaltSend::class);

    expect($capture->reply->getBody())->toContain('Internal Server Error');
  });

  it('returns a 500 when the action is not registered on the controller', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'UnknownActionCtrl', 'unknownaction');

    $request = new RouterFakeRequest(['action' => true, 'method' => 'POST']);
    bindRouterRequest($request);
    setRouterAction($request, 'ghostAction');

    guardUuidLeak();
    $capture = haltReplySend();

    $router = makeRouter();

    expect(fn (): mixed => invokeHandleActionRequest($router, $class))
      ->toThrow(RouterHaltSend::class);

    expect($capture->reply->getBody())->toContain('Internal Server Error');
  });

  it('returns a 500 when the resolved action method is static', function (): void {
    $this->routerRoot = makeRouterRoot();
    $extra = "  public static function staticAction(\\Fern\\Core\\Services\\HTTP\\Request \$request, \\Fern\\Core\\Services\\Actions\\Action \$action): \\Fern\\Core\\Services\\HTTP\\Reply { return (new Reply(200, 'ok'))->hijack(); }\n";
    $class = writeRouterController($this->routerRoot, 'StaticActionCtrl', 'staticaction', [], $extra);

    $request = new RouterFakeRequest(['action' => true, 'method' => 'POST']);
    bindRouterRequest($request);
    setRouterAction($request, 'staticAction');

    guardUuidLeak();
    $capture = haltReplySend();

    $router = makeRouter();

    // Simulate a stale cache that lists the static method as a runnable action.
    $registryProp = new ReflectionProperty(ControllerResolver::class, 'controllerRegistry');
    /** @var array<string, array{actions: array<int, string>}> $registry */
    $registry = $registryProp->getValue();
    $registry[$class]['actions'][] = 'staticAction';
    $registryProp->setValue(null, $registry);

    expect(fn (): mixed => invokeHandleActionRequest($router, $class))
      ->toThrow(RouterHaltSend::class);

    expect($capture->reply->getBody())->toContain('Internal Server Error');
  });

  it('returns a 500 when the can_run filter rejects the action', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'CanRunActionCtrl', 'canrun', ['blockedAction']);

    $request = new RouterFakeRequest(['action' => true, 'method' => 'POST']);
    bindRouterRequest($request);
    setRouterAction($request, 'blockedAction');

    guardUuidLeak();
    Filters\expectApplied('fern:core:action:can_run')->andReturn(false);
    $capture = haltReplySend();

    $router = makeRouter();

    expect(fn (): mixed => invokeHandleActionRequest($router, $class))
      ->toThrow(RouterHaltSend::class);

    expect($capture->reply->getBody())->toContain('Internal Server Error');
  });

  // MUST be the last createErrorId test: stubbing wp_generate_uuid4 leaks a
  // Brain Monkey function definition that would break any later createErrorId call.
  it('returns a 500 (uuid error id) when the action name is an empty string', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'EmptyNameActionCtrl', 'emptyname');

    $request = new RouterFakeRequest(['action' => true, 'method' => 'POST']);
    bindRouterRequest($request);
    setRouterAction($request, ''); // empty (non-null) name => reaches the guard

    Functions\when('wp_generate_uuid4')->justReturn('abcd-uuid');

    $capture = haltReplySend();

    $router = makeRouter();

    expect(fn (): mixed => invokeHandleActionRequest($router, $class))
      ->toThrow(RouterHaltSend::class);

    expect($capture->reply)->toBeInstanceOf(Reply::class)
      ->and($capture->reply->getBody())->toContain('Internal Server Error')
      ->and($capture->reply->getBody())->toContain('abcd-uuid');
  });
});

describe('canRunAction validation', function (): void {
  it('returns false and swallows the exception when attribute validation fails', function (): void {
    $this->routerRoot = makeRouterRoot();
    $class = writeRouterController($this->routerRoot, 'ValidatingCtrl', 'validating');

    bindRouterRequest(new RouterFakeRequest());

    $router = makeRouter();
    /** @var object $controller */
    $controller = $class::getInstance();

    $canRunAction = new ReflectionMethod(Router::class, 'canRunAction');

    // A non-existent method makes AttributesManager::validateMethod() throw an
    // AttributeValidationException, which canRunAction() must translate to false.
    expect($canRunAction->invoke($router, 'ghostMethod', $controller))->toBeFalse();
  });
});
