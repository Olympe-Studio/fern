<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\Views\Engines\Vanilla;

beforeEach(function (): void {
  Functions\when('trailingslashit')->alias(static function (string $string): string {
    return rtrim($string, '/\\') . '/';
  });

  $this->root = sys_get_temp_dir() . '/fern-vanilla-' . uniqid();
  $this->templatesDir = $this->root . '/templates';
  $this->blocksDir = $this->root . '/blocks';

  mkdir($this->templatesDir, 0o777, true);
  mkdir($this->blocksDir, 0o777, true);

  $this->writeTemplate = function (string $dir, string $name, string $contents): string {
    $path = $dir . '/' . $name . '.php';
    file_put_contents($path, $contents);

    return $path;
  };

  $this->makeEngine = function (): Vanilla {
    return new Vanilla([
      'path' => $this->templatesDir,
      'blocks_path' => $this->blocksDir,
    ]);
  };
});

afterEach(function (): void {
  $remove = function (string $dir) use (&$remove): void {
    if (!is_dir($dir)) {
      return;
    }

    foreach (scandir($dir) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $path = $dir . '/' . $entry;
      is_dir($path) ? $remove($path) : unlink($path);
    }

    rmdir($dir);
  };

  $remove($this->root);
});

describe('boot', function (): void {
  it('boots when the configured path is a real directory', function (): void {
    $engine = ($this->makeEngine)();

    $engine->boot();

    expect(true)->toBeTrue();
  });

  it('throws when the configured path is not a directory', function (): void {
    $engine = new Vanilla([
      'path' => $this->root . '/does-not-exist',
      'blocks_path' => $this->blocksDir,
    ]);

    expect(fn (): null => $engine->boot() ?? null)
      ->toThrow(InvalidArgumentException::class, 'Invalid path. Must be a valid directory.');
  });
});

describe('render', function (): void {
  it('renders a template and returns its echoed output', function (): void {
    ($this->writeTemplate)($this->templatesDir, 'home', '<?php echo "hello world"; ?>');

    $engine = ($this->makeEngine)();

    expect($engine->render('home'))->toBe('hello world');
  });

  it('strips a trailing .php from the template name', function (): void {
    ($this->writeTemplate)($this->templatesDir, 'home', '<?php echo "static"; ?>');

    $engine = ($this->makeEngine)();

    expect($engine->render('home.php'))->toBe('static');
  });

  it('extracts the data array into the template scope', function (): void {
    ($this->writeTemplate)($this->templatesDir, 'greet', '<?php echo "Hi " . $name . " (" . $count . ")"; ?>');

    $engine = ($this->makeEngine)();

    expect($engine->render('greet', ['name' => 'Ada', 'count' => 3]))->toBe('Hi Ada (3)');
  });

  it('renders a template that produces no output as an empty string', function (): void {
    ($this->writeTemplate)($this->templatesDir, 'silent', '<?php $unused = 1; ?>');

    $engine = ($this->makeEngine)();

    expect($engine->render('silent'))->toBe('');
  });

  it('throws when the template file is missing', function (): void {
    $engine = ($this->makeEngine)();

    expect(fn (): string => $engine->render('missing'))
      ->toThrow(InvalidArgumentException::class, 'Template not found.');
  });
});

describe('renderBlock', function (): void {
  it('renders a block from the blocks path', function (): void {
    ($this->writeTemplate)($this->blocksDir, 'callout', '<?php echo "block:" . $label; ?>');

    $engine = ($this->makeEngine)();

    expect($engine->renderBlock('callout', ['label' => 'note']))->toBe('block:note');
  });

  it('strips a trailing .php from the block name', function (): void {
    ($this->writeTemplate)($this->blocksDir, 'hero', '<?php echo "hero-block"; ?>');

    $engine = ($this->makeEngine)();

    expect($engine->renderBlock('hero.php'))->toBe('hero-block');
  });

  it('throws when the block file is missing', function (): void {
    $engine = ($this->makeEngine)();

    expect(fn (): string => $engine->renderBlock('nope'))
      ->toThrow(InvalidArgumentException::class, 'Template not found.');
  });
});
