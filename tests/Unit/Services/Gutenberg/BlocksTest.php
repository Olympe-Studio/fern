<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Fern;
use Fern\Core\Services\Gutenberg\Blocks;

/**
 * Resets the cached Fern dev flag between tests.
 */
function blocksResetFernDev(): void {
  $prop = new ReflectionProperty(Fern::class, 'isDev');
  $prop->setValue(null, null);
}

/**
 * @param mixed $value
 */
function blocksSetFernDev(mixed $value): void {
  $prop = new ReflectionProperty(Fern::class, 'isDev');
  $prop->setValue(null, $value);
}

beforeEach(function (): void {
  Functions\when('untrailingslashit')->alias(fn (string $v): string => rtrim($v, '/'));
  Functions\when('trailingslashit')->alias(fn (string $v): string => rtrim($v, '/') . '/');
  Config::getInstance()->setConfig(['root' => '/tmp/fern-blocks-root']);
  blocksResetFernDev();
});

afterEach(function (): void {
  blocksResetFernDev();
});

describe('boot', function (): void {
  it('wires categories, font library, init registration, render filters and context', function (): void {
    Filters\expectAdded('block_categories_all')->once();
    Filters\expectAdded('block_editor_settings_all')->once();
    Actions\expectAdded('init')->once();
    Filters\expectAdded('render_block')->twice();
    Filters\expectAdded('fern:core:views:ctx')->once();

    Blocks::boot();
  });
});

describe('manifest path overrides', function (): void {
  it('honours the manifest_path and cache_key filters', function (): void {
    Filters\expectApplied('fern:gutenberg:manifest_path')->once()->andReturn('/custom/manifest.json');
    Filters\expectApplied('fern:gutenberg:manifest_cache_key')->once()->andReturn('_custom_key');
    Functions\when('get_transient')->justReturn(false);

    $blocks = Blocks::getInstance();

    expect($blocks)->toBeInstanceOf(Blocks::class);
  });
});

describe('editor utilities', function (): void {
  it('disables the font library setting', function (): void {
    $settings = Blocks::getInstance()->disableFontLibrary(['existing' => 1]);

    expect($settings)->toBe(['existing' => 1, 'fontLibraryEnabled' => false]);
  });

  it('passes categories through the block_categories filter', function (): void {
    $existing = [['slug' => 'common', 'title' => 'Common']];

    Filters\expectApplied('fern:gutenberg:block_categories')
      ->once()
      ->andReturn([['slug' => 'fern', 'title' => 'Fern']]);

    expect(Blocks::getInstance()->addBlockCategories($existing))
      ->toBe([['slug' => 'fern', 'title' => 'Fern']]);
  });

  it('falls back to the existing categories when the filter returns a non-array', function (): void {
    $existing = [['slug' => 'common', 'title' => 'Common']];

    Filters\expectApplied('fern:gutenberg:block_categories')->once()->andReturn('nope');

    expect(Blocks::getInstance()->addBlockCategories($existing))->toBe($existing);
  });
});

describe('block data store', function (): void {
  it('registers and merges block data', function (): void {
    $blocks = Blocks::getInstance();
    $blocks->registerBlockData('Hero', ['title' => 'A']);
    $blocks->registerBlockData('Hero', ['subtitle' => 'B']);

    expect($blocks->getBlockData('Hero'))->toBe(['title' => 'A', 'subtitle' => 'B']);
  });

  it('returns an empty array for an unknown block', function (): void {
    expect(Blocks::getInstance()->getBlockData('Missing'))->toBe([]);
  });

  it('preRenderBlock registers then returns the stored data', function (): void {
    expect(Blocks::preRenderBlock('Card', ['x' => 1]))->toBe(['x' => 1]);
  });
});

describe('asset collection', function (): void {
  it('queues a block once, lower-cased', function (): void {
    $blocks = Blocks::getInstance();
    $blocks->queueBlock('Hero');
    $blocks->queueBlock('hero');

    $reflection = new ReflectionProperty($blocks, 'queuedBlocks');

    expect($reflection->getValue($blocks))->toBe(['hero']);
  });

  it('adds unique css and js assets and rejects an invalid type', function (): void {
    $blocks = Blocks::getInstance();
    $blocks->addAssets('css', '/a.css');
    $blocks->addAssets('css', '/a.css');
    $blocks->addAssets('js', '/a.js');

    expect($blocks->getAssets())->toBe(['css' => ['/a.css'], 'js' => ['/a.js']]);
    expect(fn () => $blocks->addAssets('font', '/a.woff'))
      ->toThrow(Exception::class, 'Invalid asset type. Must be css or js.');
  });
});

describe('manifest loading', function (): void {
  it('returns the cached manifest from the transient', function (): void {
    Functions\when('get_transient')->justReturn(['pages' => ['cached' => true]]);

    expect(Blocks::getInstance()->getManifest())->toBe(['pages' => ['cached' => true]]);
  });

  it('reads, decodes and caches the manifest from disk', function (): void {
    $dir = sys_get_temp_dir() . '/fern-manifest-' . uniqid('', true);
    mkdir($dir, 0777, true);
    $file = $dir . '/manifest.json';
    file_put_contents($file, json_encode(['pages' => ['/blocks/hero' => []]]));

    if (!defined('HOUR_IN_SECONDS')) {
      define('HOUR_IN_SECONDS', 3600);
    }

    Functions\when('get_transient')->justReturn(false);
    Filters\expectApplied('fern:gutenberg:manifest_path')->andReturn($file);
    Functions\expect('set_transient')->once();

    $manifest = Blocks::getInstance()->getManifest();

    expect($manifest)->toBe(['pages' => ['/blocks/hero' => []]]);

    unlink($file);
    rmdir($dir);
  });

  it('throws when the manifest file is missing', function (): void {
    Functions\when('get_transient')->justReturn(false);
    Filters\expectApplied('fern:gutenberg:manifest_path')->andReturn('/nope/missing-manifest.json');

    expect(fn () => Blocks::getInstance()->getManifest())
      ->toThrow(Exception::class, 'Manifest file not found. Has Astro been built?');
  });
});

describe('addBlockAssets', function (): void {
  it('extracts external css and js assets, skipping the shared first entry', function (): void {
    Functions\when('get_transient')->justReturn([
      'pages' => [
        '/blocks/hero' => [
          'styles' => [
            ['type' => 'external', 'src' => '/shared.css'],
            ['type' => 'external', 'src' => '/hero.css'],
            ['type' => 'inline', 'src' => '/skip.css'],
          ],
          'scripts' => [
            ['type' => 'external', 'value' => '/shared.js'],
            ['type' => 'external', 'value' => '/hero.js'],
          ],
        ],
      ],
    ]);

    $blocks = Blocks::getInstance();
    $blocks->addBlockAssets('Hero');

    expect($blocks->getAssets())->toBe([
      'css' => ['/hero.css'],
      'js' => ['/hero.js'],
    ]);
  });

  it('logs and skips when the block is absent from the manifest', function (): void {
    Functions\when('get_transient')->justReturn(['pages' => []]);
    Functions\when('current_time')->justReturn('now');

    $blocks = Blocks::getInstance();
    $blocks->addBlockAssets('Unknown');

    expect($blocks->getAssets())->toBe(['css' => [], 'js' => []]);
  });

  it('processes a block only once', function (): void {
    Functions\when('get_transient')->justReturn([
      'pages' => [
        '/blocks/hero' => [
          'styles' => [
            ['type' => 'external', 'src' => '/shared.css'],
            ['type' => 'external', 'src' => '/hero.css'],
          ],
        ],
      ],
    ]);

    $blocks = Blocks::getInstance();
    $blocks->addBlockAssets('hero');
    $blocks->addBlockAssets('hero');

    expect($blocks->getAssets()['css'])->toBe(['/hero.css']);
  });
});

describe('handleQueue', function (): void {
  it('processes the queued blocks and returns the consolidated assets', function (): void {
    Functions\when('get_transient')->justReturn([
      'pages' => [
        '/blocks/hero' => [
          'styles' => [
            ['type' => 'external', 'src' => '/shared.css'],
            ['type' => 'external', 'src' => '/hero.css'],
          ],
        ],
      ],
    ]);

    $blocks = Blocks::getInstance();
    $blocks->queueBlock('Hero');

    expect($blocks->handleQueue())->toBe(['css' => ['/hero.css'], 'js' => []]);
  });
});

describe('getBlockName', function (): void {
  it('returns the explicit view attribute when present', function (): void {
    expect(Blocks::getBlockName(['view' => 'CustomView']))->toBe('CustomView');
  });

  it('derives the name from a namespaced block name', function (): void {
    expect(Blocks::getBlockName(['name' => 'acf/hero-banner']))->toBe('Hero-banner');
  });

  it('uses the single segment when the name is not namespaced', function (): void {
    expect(Blocks::getBlockName(['name' => 'standalone']))->toBe('Standalone');
  });

  it('returns an empty string when neither view nor name is set', function (): void {
    expect(Blocks::getBlockName([]))->toBe('');
  });
});

describe('filterRenderBlock', function (): void {
  it('passes the html through the render_block:html filter', function (): void {
    Filters\expectApplied('fern:gutenberg:render_block:html')
      ->once()
      ->andReturn('<filtered>');

    expect(Blocks::getInstance()->filterRenderBlock('<raw>', ['blockName' => 'core/group']))
      ->toBe('<filtered>');
  });
});

describe('collectAssetsOnRender', function (): void {
  it('returns the html untouched in dev mode without queueing', function (): void {
    blocksSetFernDev(true);

    $blocks = Blocks::getInstance();
    $html = $blocks->collectAssetsOnRender('<x>', ['blockName' => 'acf/hero']);

    $reflection = new ReflectionProperty($blocks, 'queuedBlocks');

    expect($html)->toBe('<x>')
      ->and($reflection->getValue($blocks))->toBe([]);
  });

  it('returns early when the should_queue filter opts out', function (): void {
    blocksSetFernDev(false);

    Filters\expectApplied('fern:gutenberg:assets:should_queue')->once()->andReturn(false);

    $blocks = Blocks::getInstance();
    $html = $blocks->collectAssetsOnRender('<x>', ['blockName' => 'acf/hero']);

    $reflection = new ReflectionProperty($blocks, 'queuedBlocks');

    expect($html)->toBe('<x>')
      ->and($reflection->getValue($blocks))->toBe([]);
  });

  it('queues the resolved block name in production', function (): void {
    blocksSetFernDev(false);

    Filters\expectApplied('fern:gutenberg:assets:should_queue')->andReturn(true);
    Filters\expectApplied('fern:gutenberg:assets:block_name')->andReturnUsing(fn ($name) => $name);

    $blocks = Blocks::getInstance();
    $blocks->collectAssetsOnRender('<x>', [
      'blockName' => 'acf/hero',
      'attrs' => ['name' => 'acf/hero-banner'],
    ]);

    $reflection = new ReflectionProperty($blocks, 'queuedBlocks');

    expect($reflection->getValue($blocks))->toBe(['hero-banner']);
  });
});

describe('addAssetsToContext', function (): void {
  it('returns the context untouched in dev mode', function (): void {
    blocksSetFernDev(true);

    expect(Blocks::getInstance()->addAssetsToContext(['a' => 1]))->toBe(['a' => 1]);
  });

  it('injects the collected assets under _assets in production', function (): void {
    blocksSetFernDev(false);

    $ctx = Blocks::getInstance()->addAssetsToContext(['a' => 1]);

    expect($ctx)->toBe(['a' => 1, '_assets' => ['css' => [], 'js' => []]]);
  });
});

describe('registerBlocks', function (): void {
  it('registers each block.json directory returned by the register filter', function (): void {
    $dir = sys_get_temp_dir() . '/fern-block-' . uniqid('', true);
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/block.json', json_encode(['name' => 'acf/hero', 'title' => 'Hero']));

    Filters\expectApplied('fern:gutenberg:blocks_base_path')->andReturn('/base/App/Blocks');
    Filters\expectApplied('fern:gutenberg:blocks_register')->andReturn([$dir]);

    Functions\expect('register_block_type')
      ->once()
      ->with($dir, Mockery::on(static function (array $settings): bool {
        return $settings['name'] === 'acf/hero'
          && is_callable($settings['render_callback']);
      }));

    Blocks::getInstance()->registerBlocks();

    unlink($dir . '/block.json');
    rmdir($dir);
  });

  it('skips a path with no block.json file', function (): void {
    $dir = sys_get_temp_dir() . '/fern-empty-' . uniqid('', true);
    mkdir($dir, 0777, true);

    Filters\expectApplied('fern:gutenberg:blocks_base_path')->andReturn('/base/App/Blocks');
    Filters\expectApplied('fern:gutenberg:blocks_register')->andReturn([$dir]);

    Functions\expect('register_block_type')->never();

    Blocks::getInstance()->registerBlocks();

    rmdir($dir);
  });

  it('resolves relative paths against the configured base path', function (): void {
    $base = sys_get_temp_dir() . '/fern-base-' . uniqid('', true);
    mkdir($base . '/hero', 0777, true);
    file_put_contents($base . '/hero/block.json', json_encode(['name' => 'acf/hero']));

    Filters\expectApplied('fern:gutenberg:blocks_base_path')->andReturn($base);
    Filters\expectApplied('fern:gutenberg:blocks_register')->andReturn(['hero']);

    Functions\expect('register_block_type')->once()->with($base . '/hero', Mockery::type('array'));

    Blocks::getInstance()->registerBlocks();

    unlink($base . '/hero/block.json');
    rmdir($base . '/hero');
    rmdir($base);
  });

  it('does nothing when the register filter yields no paths', function (): void {
    Filters\expectApplied('fern:gutenberg:blocks_base_path')->andReturn('/base/App/Blocks');
    Filters\expectApplied('fern:gutenberg:blocks_register')->andReturn([]);

    Functions\expect('register_block_type')->never();

    Blocks::getInstance()->registerBlocks();
  });
});

describe('renderBlock', function (): void {
  it('does nothing when given a non-array payload', function (): void {
    ob_start();
    Blocks::renderBlock(null);
    $output = ob_get_clean();

    expect($output)->toBe('');
  });

  it('echoes html produced by the render_block_override filter', function (): void {
    Filters\expectApplied('fern:gutenberg:pre_render_block')->andReturn([]);
    Filters\expectApplied('fern:gutenberg:render_block_view')->andReturnUsing(fn ($v) => $v);
    Filters\expectApplied('fern:gutenberg:render_block_override')->andReturn('<block-html>');
    Filters\expectApplied('fern:gutenberg:render_block_data')->andReturnUsing(fn ($d) => $d);
    Filters\expectApplied('fern:gutenberg:render_block_html')->andReturnUsing(fn ($h) => $h);

    ob_start();
    Blocks::renderBlock(['name' => 'acf/hero', 'data' => ['heading' => 'Hi']]);
    $output = ob_get_clean();

    expect($output)->toBe('<block-html>');
  });

  it('merges pre-render data into the block data before rendering', function (): void {
    $capturedData = null;

    Filters\expectApplied('fern:gutenberg:pre_render_block')->andReturn(['injected' => true]);
    Filters\expectApplied('fern:gutenberg:render_block_view')->andReturnUsing(fn ($v) => $v);
    Filters\expectApplied('fern:gutenberg:render_block_override')->andReturn('<x>');
    Filters\expectApplied('fern:gutenberg:render_block_data')
      ->andReturnUsing(function ($data) use (&$capturedData) {
        $capturedData = $data;

        return $data;
      });
    Filters\expectApplied('fern:gutenberg:render_block_html')->andReturnUsing(fn ($h) => $h);

    ob_start();
    Blocks::renderBlock(['name' => 'acf/hero', 'data' => ['existing' => 1]]);
    ob_get_clean();

    expect($capturedData)->toBe(['fields' => ['existing' => 1, 'injected' => true]]);
  });
});
