<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Factory\Singleton;
use Fern\Core\Utils\Autoloader;

/**
 * Creates a throwaway framework root with an App directory and points the
 * config at it.
 */
function makeAutoloaderRoot(): string {
  $root = sys_get_temp_dir() . '/fern-autoloader-' . uniqid('', true);
  mkdir($root . '/App', 0777, true);
  Config::getInstance()->setConfig(['root' => $root]);

  return $root;
}

/**
 * Recursively deletes a directory tree.
 */
function removeAutoloaderRoot(string $root): void {
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
 * Writes a file under the given root, creating intermediate directories.
 */
function writeFile(string $root, string $relativePath, string $contents): string {
  $path = $root . '/' . ltrim($relativePath, '/');
  $dir = dirname($path);

  if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
  }

  file_put_contents($path, $contents);

  return $path;
}

/**
 * Clears the Autoloader's process-wide directory scan cache.
 */
function resetAutoloaderCache(): void {
  $property = new ReflectionProperty(Autoloader::class, 'filePathCache');
  $property->setValue(null, []);
}

beforeEach(function (): void {
  resetAutoloaderCache();
});

afterEach(function (): void {
  resetAutoloaderCache();

  if (isset($this->autoloaderRoot) && is_string($this->autoloaderRoot)) {
    removeAutoloaderRoot($this->autoloaderRoot);
  }
});

describe('includes path resolution', function (): void {
  it('builds the includes path from the configured root', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();

    expect(Autoloader::getIncludesPath())->toBe($this->autoloaderRoot . '/includes.php');
  });

  it('normalises a trailing slash on the root', function (): void {
    $root = makeAutoloaderRoot();
    Config::getInstance()->setConfig(['root' => $root . '/']);
    $this->autoloaderRoot = $root;

    expect(Autoloader::getIncludesPath())->toBe($root . '/includes.php');
  });
});

describe('load in development', function (): void {
  it('generates includes.php from underscore files and requires it', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, true);

    $marker = 'FERN_AUTOLOAD_' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    writeFile($this->autoloaderRoot, 'App/_bootstrap.php', "<?php define('{$marker}', true);\n");
    writeFile($this->autoloaderRoot, 'App/Controllers/HomeController.php', "<?php\n");

    Autoloader::load();

    $includes = $this->autoloaderRoot . '/includes.php';
    expect($includes)->toBeFile()
      ->and(defined($marker))->toBeTrue();

    $generated = file_get_contents($includes);
    expect($generated)->toContain("require_once __DIR__ . '/App/_bootstrap.php';")
      ->and($generated)->toContain('Environment: development')
      ->and($generated)->not->toContain('HomeController.php');
  });

  it('regenerates includes.php even when one already exists', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, true);

    $includes = $this->autoloaderRoot . '/includes.php';
    file_put_contents($includes, "<?php // stale\n");

    writeFile($this->autoloaderRoot, 'App/_init.php', "<?php\n");

    Autoloader::load();

    $generated = file_get_contents($includes);
    expect($generated)->not->toContain('// stale')
      ->and($generated)->toContain("require_once __DIR__ . '/App/_init.php';");
  });
});

describe('load in production', function (): void {
  it('requires an existing includes.php without regenerating it', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, false);

    $marker = 'FERN_PROD_' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $includes = $this->autoloaderRoot . '/includes.php';
    file_put_contents($includes, "<?php define('{$marker}', true);\n");

    writeFile($this->autoloaderRoot, 'App/_should_not_be_scanned.php', "<?php\n");

    Autoloader::load();

    expect(defined($marker))->toBeTrue();

    $contents = file_get_contents($includes);
    expect($contents)->toBe("<?php define('{$marker}', true);\n")
      ->and($contents)->not->toContain('_should_not_be_scanned');
  });

  it('generates includes.php when none exists in production', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, false);

    writeFile($this->autoloaderRoot, 'App/_prodgen.php', "<?php\n");

    Autoloader::load();

    $includes = $this->autoloaderRoot . '/includes.php';
    expect($includes)->toBeFile();

    $generated = file_get_contents($includes);
    expect($generated)->toContain('Environment: production')
      ->and($generated)->toContain("require_once __DIR__ . '/App/_prodgen.php';");
  });
});

describe('underscore file discovery', function (): void {
  it('collects only underscore-prefixed php files recursively', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, true);

    writeFile($this->autoloaderRoot, 'App/_top.php', "<?php\n");
    writeFile($this->autoloaderRoot, 'App/Hooks/_nested.php', "<?php\n");
    writeFile($this->autoloaderRoot, 'App/Hooks/Regular.php', "<?php\n");
    writeFile($this->autoloaderRoot, 'App/notes/_ignore.txt', "text\n");

    Autoloader::load();

    $generated = file_get_contents($this->autoloaderRoot . '/includes.php');

    expect($generated)->toContain("require_once __DIR__ . '/App/_top.php';")
      ->and($generated)->toContain("require_once __DIR__ . '/App/Hooks/_nested.php';")
      ->and($generated)->not->toContain('Regular.php')
      ->and($generated)->not->toContain('_ignore.txt');
  });

  it('reports zero files when there are no underscore files', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, true);

    writeFile($this->autoloaderRoot, 'App/Plain.php', "<?php\n");

    Autoloader::load();

    $generated = file_get_contents($this->autoloaderRoot . '/includes.php');

    expect($generated)->toContain('Files: 0 (underscore files only)');
  });
});

describe('getFilesRecursively caching and guards', function (): void {
  it('returns an empty list for a missing directory', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();

    $method = new ReflectionMethod(Autoloader::class, 'getFilesRecursively');

    expect($method->invoke(null, $this->autoloaderRoot . '/does-not-exist'))->toBe([]);
  });

  it('caches the scan result for a directory and filter pair', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    writeFile($this->autoloaderRoot, 'App/_one.php', "<?php\n");

    $method = new ReflectionMethod(Autoloader::class, 'getFilesRecursively');
    $appDir = $this->autoloaderRoot . '/App';

    $first = $method->invoke(null, $appDir);
    expect($first)->toContain($this->autoloaderRoot . '/App/_one.php');

    writeFile($this->autoloaderRoot, 'App/_two.php', "<?php\n");

    $second = $method->invoke(null, $appDir);
    expect($second)->toBe($first)
      ->and($second)->not->toContain($this->autoloaderRoot . '/App/_two.php');
  });
});

describe('loadController', function (): void {
  it('returns true immediately when the class is already loaded', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    Functions\when('wp_mkdir_p')->justReturn(true);

    expect(Autoloader::loadController(Singleton::class))->toBeTrue();
  });

  it('loads a controller by convention when not in the registry', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, false);
    Functions\when('wp_mkdir_p')->justReturn(true);

    $class = 'ConventionLoaded' . substr(md5(uniqid('', true)), 0, 8);
    $fqcn = 'App\\Controllers\\' . $class;
    writeFile(
      $this->autoloaderRoot,
      'App/Controllers/' . $class . '.php',
      "<?php\nnamespace App\\Controllers;\nclass {$class} {}\n",
    );

    expect(Autoloader::loadController($fqcn))->toBeTrue()
      ->and(class_exists($fqcn, false))->toBeTrue();
  });

  it('returns false when neither the registry nor convention resolves the class', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, false);
    Functions\when('wp_mkdir_p')->justReturn(true);

    expect(Autoloader::loadController('App\\Controllers\\TotallyMissing'))->toBeFalse();
  });

  it('returns false when a convention file exists but defines the wrong class', function (): void {
    $this->autoloaderRoot = makeAutoloaderRoot();
    $devProp = new ReflectionProperty(\Fern\Core\Fern::class, 'isDev');
    $devProp->setValue(null, false);
    Functions\when('wp_mkdir_p')->justReturn(true);

    $class = 'WrongName' . substr(md5(uniqid('', true)), 0, 8);
    $fqcn = 'App\\Controllers\\' . $class;
    writeFile(
      $this->autoloaderRoot,
      'App/Controllers/' . $class . '.php',
      "<?php\n",
    );

    expect(Autoloader::loadController($fqcn))->toBeFalse();
  });
});
