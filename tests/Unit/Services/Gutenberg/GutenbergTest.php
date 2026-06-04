<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Services\Gutenberg\Gutenberg;

if (!class_exists('WP_Block_Type')) {
  #[AllowDynamicProperties]
  class WP_Block_Type {
    public ?string $category = null;

    public function __construct(?string $category = null) {
      $this->category = $category;
    }
  }
}

if (!class_exists('WP_Block_Type_Registry')) {
  class WP_Block_Type_Registry {
    private static ?self $instance = null;

    /** @var array<string, WP_Block_Type> */
    private array $blocks = [];

    public static function get_instance(): self {
      return self::$instance ??= new self();
    }

    /**
     * @param array<string, WP_Block_Type> $blocks
     */
    public static function seed(array $blocks): void {
      $registry = self::get_instance();
      $registry->blocks = $blocks;
    }

    public static function reset(): void {
      self::$instance = null;
    }

    /**
     * @return array<string, WP_Block_Type>
     */
    public function get_all_registered(): array {
      return $this->blocks;
    }

    public function get_registered(string $name): ?WP_Block_Type {
      return $this->blocks[$name] ?? null;
    }
  }
}

/**
 * Seeds the Gutenberg theme config and returns a fresh instance.
 *
 * @param array<string, mixed> $gutenberg
 */
function makeGutenberg(array $gutenberg): Gutenberg {
  Config::getInstance()->setConfig(['theme' => ['gutenberg' => $gutenberg]]);

  return Gutenberg::getInstance();
}

afterEach(function (): void {
  WP_Block_Type_Registry::reset();
});

describe('construction', function (): void {
  it('reads include / exclude / post-type config into the instance', function (): void {
    $gutenberg = makeGutenberg([
      'show_on_post_types' => ['post', 'page'],
      'core_block_exclude' => ['core/*'],
      'core_block_include' => ['core/paragraph'],
    ]);

    expect($gutenberg->filterAllowedBlocks(true))->toBe(['core/paragraph']);
  });

  it('tolerates a non-array gutenberg config', function (): void {
    Config::getInstance()->setConfig(['theme' => ['gutenberg' => 'nope']]);

    $gutenberg = Gutenberg::getInstance();

    expect($gutenberg->filterAllowedBlocks(['fallback']))->toBe(['fallback']);
  });

  it('tolerates non-array sub-config values', function (): void {
    $gutenberg = makeGutenberg([
      'show_on_post_types' => 'not-an-array',
      'core_block_exclude' => 'not-an-array',
      'core_block_include' => 'not-an-array',
    ]);

    expect($gutenberg->explicitlyShowOnPostTypes(false, 'post'))->toBeFalse()
      ->and($gutenberg->filterAllowedBlocks(['kept']))->toBe(['kept']);
  });
});

describe('boot', function (): void {
  beforeEach(function (): void {
    Functions\when('untrailingslashit')->alias(fn (string $v): string => rtrim($v, '/'));
    Functions\when('trailingslashit')->alias(fn (string $v): string => rtrim($v, '/') . '/');
    Functions\when('get_transient')->justReturn(false);
    Functions\when('getallheaders')->justReturn([]);
    Functions\when('get_home_url')->justReturn('https://example.test');
    Functions\when('get_the_ID')->justReturn(false);
    Functions\when('get_queried_object')->justReturn(null);
    Functions\when('get_queried_object_id')->justReturn(0);
    Config::getInstance()->setConfig(['root' => '/tmp/fern-gb', 'theme' => ['gutenberg' => []]]);
  });

  it('registers editor filters and boots Blocks when in admin', function (): void {
    Functions\when('is_admin')->justReturn(true);

    Filters\expectAdded('use_block_editor_for_post_type')->once();
    Filters\expectAdded('allowed_block_types_all')->once();
    Filters\expectAdded('block_categories_all')->once();

    Gutenberg::boot();
  });

  it('skips the admin-only editor filters on a front-end request', function (): void {
    Functions\when('is_admin')->justReturn(false);

    Filters\expectAdded('use_block_editor_for_post_type')->never();
    Filters\expectAdded('allowed_block_types_all')->never();
    Filters\expectAdded('block_categories_all')->once();

    Gutenberg::boot();
  });
});

describe('explicitlyShowOnPostTypes', function (): void {
  it('returns false for the page post type when there is no global post', function (): void {
    $GLOBALS['post'] = null;
    $gutenberg = makeGutenberg(['show_on_post_types' => ['12']]);

    expect($gutenberg->explicitlyShowOnPostTypes(false, 'page'))->toBeFalse();
  });

  it('matches the page when its id is listed among the numeric ids', function (): void {
    $GLOBALS['post'] = new WP_Post(['ID' => 12]);
    $gutenberg = makeGutenberg(['show_on_post_types' => ['12', 'something']]);

    expect($gutenberg->explicitlyShowOnPostTypes(false, 'page'))->toBeTrue();
  });

  it('rejects the page when its id is not listed', function (): void {
    $GLOBALS['post'] = new WP_Post(['ID' => 99]);
    $gutenberg = makeGutenberg(['show_on_post_types' => ['12']]);

    expect($gutenberg->explicitlyShowOnPostTypes(false, 'page'))->toBeFalse();
  });

  it('matches a non-page post type listed in the configuration', function (): void {
    $gutenberg = makeGutenberg(['show_on_post_types' => ['product']]);

    expect($gutenberg->explicitlyShowOnPostTypes(false, 'product'))->toBeTrue()
      ->and($gutenberg->explicitlyShowOnPostTypes(false, 'event'))->toBeFalse();
  });
});

describe('filterAllowedBlocks', function (): void {
  it('returns the include list verbatim when one is configured', function (): void {
    $gutenberg = makeGutenberg(['core_block_include' => ['core/heading', 'core/list']]);

    expect($gutenberg->filterAllowedBlocks(true))->toBe(['core/heading', 'core/list']);
  });

  it('returns the input unchanged when neither include nor exclude is set', function (): void {
    $gutenberg = makeGutenberg([]);

    expect($gutenberg->filterAllowedBlocks(['core/quote']))->toBe(['core/quote'])
      ->and($gutenberg->filterAllowedBlocks(true))->toBeTrue();
  });

  it('excludes blocks matching a namespace wildcard pattern', function (): void {
    WP_Block_Type_Registry::seed([
      'core/paragraph' => new WP_Block_Type('text'),
      'acme/widget' => new WP_Block_Type('embed'),
      'core/heading' => new WP_Block_Type('text'),
    ]);

    $gutenberg = makeGutenberg(['core_block_exclude' => ['core/*']]);

    expect($gutenberg->filterAllowedBlocks(true))->toBe(['acme/widget']);
  });

  it('excludes an exact block name match', function (): void {
    WP_Block_Type_Registry::seed([
      'core/paragraph' => new WP_Block_Type('text'),
      'core/heading' => new WP_Block_Type('text'),
    ]);

    $gutenberg = makeGutenberg(['core_block_exclude' => ['core/heading']]);

    expect($gutenberg->filterAllowedBlocks(true))->toBe(['core/paragraph']);
  });

  it('excludes by category when the exclude key is a string and the pattern matches', function (): void {
    WP_Block_Type_Registry::seed([
      'core/paragraph' => new WP_Block_Type('text'),
      'core/cover' => new WP_Block_Type('media'),
      'core/image' => new WP_Block_Type('media'),
    ]);

    $gutenberg = makeGutenberg(['core_block_exclude' => ['media' => 'core/*']]);

    expect($gutenberg->filterAllowedBlocks(true))->toBe(['core/paragraph']);
  });

  it('keeps blocks whose category differs from a categorised exclusion', function (): void {
    WP_Block_Type_Registry::seed([
      'core/paragraph' => new WP_Block_Type('text'),
      'core/image' => new WP_Block_Type('media'),
    ]);

    $gutenberg = makeGutenberg(['core_block_exclude' => ['media' => 'core/paragraph']]);

    expect($gutenberg->filterAllowedBlocks(true))->toBe(['core/paragraph', 'core/image']);
  });
});

