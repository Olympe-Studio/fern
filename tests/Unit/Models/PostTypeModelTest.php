<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Models\PostTypeModel;

/**
 * Concrete fixture binding the abstract PostTypeModel to the built-in 'page'
 * post type so the static surface can be exercised.
 */
final class TestPageModel extends PostTypeModel {
  public static function getPostType(): string {
    return 'page';
  }
}

beforeEach(function (): void {
  \WP_Query::$mockPosts = [];
});

afterEach(function (): void {
  \WP_Query::$mockPosts = [];
});

describe('getPostType', function (): void {
  it('returns the bound post type', function (): void {
    expect(TestPageModel::getPostType())->toBe('page');
  });
});

describe('query', function (): void {
  it('injects the post type into the query vars', function (): void {
    $query = TestPageModel::query(['posts_per_page' => 5]);

    expect($query)->toBeInstanceOf(WP_Query::class)
      ->and($query->query_vars)->toBe(['posts_per_page' => 5, 'post_type' => 'page']);
  });

  it('overrides a caller supplied post type with the bound one', function (): void {
    $query = TestPageModel::query(['post_type' => 'post']);

    expect($query->query_vars['post_type'])->toBe('page');
  });

  it('returns the mocked posts', function (): void {
    \WP_Query::$mockPosts = [new WP_Post(['ID' => 1]), new WP_Post(['ID' => 2])];

    $query = TestPageModel::query();

    expect($query->posts)->toHaveCount(2)
      ->and($query->post_count)->toBe(2)
      ->and($query->found_posts)->toBe(2);
  });
});

describe('getById', function (): void {
  it('returns the WP_Post from get_post with default output and filter', function (): void {
    $post = new WP_Post(['ID' => 7]);

    Functions\expect('get_post')
      ->once()
      ->with(7, OBJECT, 'raw')
      ->andReturn($post);

    expect(TestPageModel::getById(7))->toBe($post);
  });

  it('passes through a custom output and filter', function (): void {
    Functions\expect('get_post')
      ->once()
      ->with(7, ARRAY_A, 'display')
      ->andReturn(['ID' => 7]);

    expect(TestPageModel::getById(7, ARRAY_A, 'display'))->toBe(['ID' => 7]);
  });

  it('returns null when the post is not found', function (): void {
    Functions\when('get_post')->justReturn(null);

    expect(TestPageModel::getById(999))->toBeNull();
  });
});

describe('create', function (): void {
  it('merges the post type ahead of the data and forwards to wp_insert_post', function (): void {
    Functions\expect('wp_insert_post')
      ->once()
      ->with(['post_type' => 'page', 'post_title' => 'Hello'], true)
      ->andReturn(42);

    expect(TestPageModel::create(['post_title' => 'Hello']))->toBe(42);
  });

  it('lets the data override the default post type', function (): void {
    Functions\expect('wp_insert_post')
      ->once()
      ->with(['post_type' => 'custom'], true)
      ->andReturn(1);

    TestPageModel::create(['post_type' => 'custom']);
  });

  it('returns the WP_Error from wp_insert_post', function (): void {
    $error = new WP_Error('db_error', 'insert failed');

    Functions\expect('wp_insert_post')->once()->andReturn($error);

    expect(TestPageModel::create([]))->toBe($error);
  });
});

describe('update', function (): void {
  it('forwards the id and post type with wp_error and fire_after_hooks enabled', function (): void {
    Functions\expect('wp_update_post')
      ->once()
      ->with(['ID' => 5, 'post_type' => 'page', 'post_title' => 'Edited'], true, true)
      ->andReturn(5);

    expect(TestPageModel::update(5, ['post_title' => 'Edited']))->toBe(5);
  });

  it('returns the WP_Error from wp_update_post', function (): void {
    $error = new WP_Error('update_failed', 'nope');

    Functions\expect('wp_update_post')->once()->andReturn($error);

    expect(TestPageModel::update(5, []))->toBe($error);
  });
});

describe('delete', function (): void {
  it('passes the force flag to wp_delete_post and returns the deleted post', function (): void {
    $post = new WP_Post(['ID' => 3]);

    Functions\expect('wp_delete_post')
      ->once()
      ->with(3, true)
      ->andReturn($post);

    expect(TestPageModel::delete(3, true))->toBe($post);
  });

  it('defaults force to false', function (): void {
    Functions\expect('wp_delete_post')
      ->once()
      ->with(3, false)
      ->andReturn(false);

    expect(TestPageModel::delete(3))->toBeFalse();
  });

  it('returns null when wp_delete_post returns null', function (): void {
    Functions\when('wp_delete_post')->justReturn(null);

    expect(TestPageModel::delete(3))->toBeNull();
  });
});

describe('exists', function (): void {
  it('is true when getById yields a WP_Post', function (): void {
    Functions\when('get_post')->justReturn(new WP_Post(['ID' => 1]));

    expect(TestPageModel::exists(1))->toBeTrue();
  });

  it('is false when getById yields null', function (): void {
    Functions\when('get_post')->justReturn(null);

    expect(TestPageModel::exists(1))->toBeFalse();
  });

  it('is false when getById yields an array', function (): void {
    Functions\when('get_post')->justReturn(['ID' => 1]);

    expect(TestPageModel::exists(1))->toBeFalse();
  });
});

describe('getStatusCounts', function (): void {
  it('returns a single status count cast to int', function (): void {
    Functions\expect('wp_count_posts')
      ->once()
      ->with('page')
      ->andReturn((object) ['publish' => '12', 'draft' => 3]);

    expect(TestPageModel::getStatusCounts('publish'))->toBe(12);
  });

  it('returns every status as an int map when status is any', function (): void {
    Functions\expect('wp_count_posts')
      ->once()
      ->with('page')
      ->andReturn((object) ['publish' => '12', 'draft' => '3', 'trash' => 0]);

    expect(TestPageModel::getStatusCounts())->toBe([
      'publish' => 12,
      'draft' => 3,
      'trash' => 0,
    ]);
  });

  it('defaults the requested status to any', function (): void {
    Functions\when('wp_count_posts')->justReturn((object) ['publish' => 1]);

    expect(TestPageModel::getStatusCounts())->toBe(['publish' => 1]);
  });
});

describe('get_posts backed finders', function (): void {
  beforeEach(function (): void {
    WP_Query::$mockPosts = [];
  });

  afterEach(function (): void {
    WP_Query::$mockPosts = [];
  });

  it('findByIds queries post__in ordered and filters to WP_Post instances', function (): void {
    $post = new WP_Post(['ID' => 7]);
    WP_Query::$mockPosts = [$post, 'not-a-post', new WP_Post(['ID' => 9])];

    $result = TestPageModel::findByIds([7, 9]);

    expect($result)->toHaveCount(2)
      ->and($result[0])->toBe($post);
  });

  it('latest returns the queried posts', function (): void {
    WP_Query::$mockPosts = [new WP_Post(['ID' => 1]), new WP_Post(['ID' => 2])];

    expect(TestPageModel::latest(2))->toHaveCount(2);
  });

  it('findByAuthor returns the queried posts', function (): void {
    WP_Query::$mockPosts = [new WP_Post(['ID' => 3])];

    expect(TestPageModel::findByAuthor(5))->toHaveCount(1);
  });

  it('findByDateRange returns the queried posts', function (): void {
    WP_Query::$mockPosts = [new WP_Post(['ID' => 4])];

    expect(TestPageModel::findByDateRange('2026-01-01', '2026-12-31'))->toHaveCount(1);
  });

  it('search returns the queried posts', function (): void {
    WP_Query::$mockPosts = [new WP_Post(['ID' => 5])];

    expect(TestPageModel::search('fern'))->toHaveCount(1);
  });

  it('findByStatus returns the queried posts', function (): void {
    WP_Query::$mockPosts = [new WP_Post(['ID' => 6])];

    expect(TestPageModel::findByStatus('draft'))->toHaveCount(1);
  });
});
