<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\CLI\FernControllerCommand;
use Fern\Core\Config;

if (!class_exists('WP_CLI')) {
  /**
   * Recording stub for the WP_CLI facade. Mirrors the one in FernCliTest so the
   * suite works regardless of which Cli test file Pest loads first. The guard
   * keeps a single definition alive across both files.
   */
  class WP_CLI {
    /** @var array<int, string> */
    public static array $errors = [];

    /** @var array<int, string> */
    public static array $success = [];

    /** @var array<int, string> */
    public static array $lines = [];

    /** @var array<int, string> */
    public static array $warnings = [];

    /** @var array<int, array{0: string, 1: mixed}> */
    public static array $commands = [];

    public static bool $confirmReturn = true;

    public static function reset(): void {
      self::$errors = [];
      self::$success = [];
      self::$lines = [];
      self::$warnings = [];
      self::$commands = [];
      self::$confirmReturn = true;
    }

    public static function error(string $message): void {
      self::$errors[] = $message;
    }

    public static function success(string $message): void {
      self::$success[] = $message;
    }

    public static function line(string $message = ''): void {
      self::$lines[] = $message;
    }

    public static function warning(string $message): void {
      self::$warnings[] = $message;
    }

    public static function confirm(string $question): bool {
      return self::$confirmReturn;
    }

    public static function add_command(string $name, mixed $callable): void {
      self::$commands[] = [$name, $callable];
    }
  }
}

/**
 * Subclass that turns the fatal terminate() seam into a catchable exception so
 * the early-exit branches can be asserted instead of killing the test process.
 */
class TerminatingControllerCommand extends FernControllerCommand {
  protected function terminate(): never {
    throw new RuntimeException('terminated');
  }
}

/**
 * Points Fern's root at a throwaway directory laid out the way the command
 * expects: real templates under fern/src/CLI/templates and a writable
 * App/Controllers output dir. Returns the root path.
 */
function makeCliRoot(bool $withTemplates = true): string {
  $root = sys_get_temp_dir() . '/fern-cli-' . uniqid('', true);
  mkdir($root . '/App/Controllers', 0777, true);

  if ($withTemplates) {
    $templateDir = $root . '/fern/src/CLI/templates';
    mkdir($templateDir, 0777, true);
    $src = dirname(__DIR__, 3) . '/src/CLI/templates';
    copy($src . '/Controller.php', $templateDir . '/Controller.php');
    copy($src . '/LightController.php', $templateDir . '/LightController.php');
  }

  Config::getInstance()->setConfig(['root' => $root]);

  return $root;
}

/**
 * Recursively deletes a directory tree.
 */
function removeCliRoot(string $root): void {
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

beforeEach(function (): void {
  WP_CLI::reset();
  Functions\when('trailingslashit')->alias(fn (string $v): string => rtrim($v, '/') . '/');
});

afterEach(function (): void {
  if (isset($this->cliRoot) && is_string($this->cliRoot)) {
    removeCliRoot($this->cliRoot);
  }
});

describe('argument validation', function (): void {
  it('records an error and returns when the argument count is wrong', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $command->create(['OnlyOne'], []);

    expect(WP_CLI::$errors)->toBe(['This command requires exactly two arguments: <name> and <handle>.'])
      ->and(WP_CLI::$success)->toBe([]);
  });
});

describe('create happy path', function (): void {
  it('writes a controller file with namespace, class, view and handle substituted', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $command->create(['Gallery', 'gallery'], []);

    $file = $this->cliRoot . '/App/Controllers/GalleryController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain('namespace App\\Controllers;')
      ->and($contents)->toContain('class GalleryController extends Singleton')
      ->and($contents)->toContain("public static string \$handle = 'gallery';")
      ->and($contents)->toContain("Views::render('Gallery', [])")
      ->and($contents)->not->toContain('NameController')
      ->and($contents)->not->toContain('NameView')
      ->and($contents)->not->toContain('id_or_post_type_or_taxonomy')
      ->and(WP_CLI::$success)->toHaveCount(1)
      ->and(WP_CLI::$success[0])->toContain('Controller Gallery created successfully');
  });

  it('strips a Controller suffix from the supplied name via cleanControllerName', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $command->create(['ProductController', 'product'], []);

    $file = $this->cliRoot . '/App/Controllers/ProductController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain('class ProductController extends Singleton')
      ->and($contents)->toContain("Views::render('Product', [])");
  });

  it('lowercases nothing but ucfirsts a lowercase name for the view and class', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $command->create(['blog', 'post'], []);

    $file = $this->cliRoot . '/App/Controllers/blogController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain('class BlogController extends Singleton')
      ->and($contents)->toContain("Views::render('Blog', [])");
  });
});

describe('create with options', function (): void {
  it('places the controller in a subdirectory with a namespaced declaration', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $command->create(['Dashboard', 'dashboard'], ['subdir' => 'admin']);

    $file = $this->cliRoot . '/App/Controllers/Admin/DashboardController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain('namespace App\\Controllers\\Admin;');
  });

  it('uses the light template when --light is set', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $command->create(['Faq', 'faq'], ['light' => true]);

    $file = $this->cliRoot . '/App/Controllers/FaqController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain('class FaqController extends Singleton')
      ->and($contents)->not->toContain('RequireCapabilities')
      ->and($contents)->not->toContain('sayHelloWorld');
  });
});

describe('already exists guard', function (): void {
  it('records an error and does not overwrite when a controller file already exists', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    $existing = $this->cliRoot . '/App/Controllers/DupController.php';
    file_put_contents($existing, '<?php // original');

    $command->create(['Dup', 'dup'], []);

    expect(WP_CLI::$errors)->toBe(['A controller named Dup already exists.'])
      ->and(file_get_contents($existing))->toBe('<?php // original')
      ->and(WP_CLI::$success)->toBe([]);
  });
});

describe('terminate branches', function (): void {
  it('errors and terminates when the template file is missing', function (): void {
    $this->cliRoot = makeCliRoot(false);
    $command = new TerminatingControllerCommand();

    expect(fn (): mixed => $command->create(['Ghost', 'ghost'], []))
      ->toThrow(RuntimeException::class, 'terminated');

    expect(WP_CLI::$errors)->toHaveCount(1)
      ->and(WP_CLI::$errors[0])->toContain('Template file not found at');
  });
});

describe('page handle creation', function (): void {
  it('creates a page via wp_insert_post and uses its ID as the handle when --create-page is set', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    Functions\expect('wp_insert_post')
      ->once()
      ->with(Mockery::on(static function (array $args): bool {
        return $args['post_type'] === 'page'
          && $args['post_title'] === 'Landing'
          && $args['post_status'] === 'publish';
      }), true)
      ->andReturn(42);
    Functions\when('get_edit_post_link')->justReturn('http://example.test/edit/42');

    $command->create(['Landing', 'page'], ['create-page' => true]);

    $file = $this->cliRoot . '/App/Controllers/LandingController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain("public static string \$handle = '42';")
      ->and(WP_CLI::$success)->toContain("Page 'Landing' created with ID: 42");
  });

  it('prompts via WP_CLI::confirm and uses the literal page handle when confirmation is declined', function (): void {
    $this->cliRoot = makeCliRoot();
    WP_CLI::$confirmReturn = false;
    $command = new FernControllerCommand();

    Functions\expect('wp_insert_post')->never();

    $command->create(['Generic', 'page'], []);

    $file = $this->cliRoot . '/App/Controllers/GenericController.php';
    expect($file)->toBeFile();

    $contents = file_get_contents($file);
    expect($contents)->toContain("public static string \$handle = 'page';");
  });

  it('records an error and aborts when wp_insert_post returns a WP_Error', function (): void {
    $this->cliRoot = makeCliRoot();
    $command = new FernControllerCommand();

    Functions\when('wp_insert_post')->justReturn(new WP_Error('db_fail', 'Could not insert'));

    $command->create(['Broken', 'page'], ['create-page' => true]);

    expect(WP_CLI::$errors)->toBe(['Failed to create the page: Could not insert'])
      ->and($this->cliRoot . '/App/Controllers/BrokenController.php')->not->toBeFile();
  });
});
