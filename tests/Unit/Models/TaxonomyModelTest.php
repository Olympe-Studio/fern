<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Models\TaxonomyModel;

/**
 * Concrete fixture binding the abstract TaxonomyModel to the built-in 'category'
 * taxonomy so the static surface can be exercised.
 */
final class TestCategoryModel extends TaxonomyModel {
  public static function getTaxonomy(): string {
    return 'category';
  }
}

beforeEach(function (): void {
  \WP_Term_Query::$mockTerms = [];
});

afterEach(function (): void {
  \WP_Term_Query::$mockTerms = [];
});

describe('getTaxonomy', function (): void {
  it('returns the bound taxonomy', function (): void {
    expect(TestCategoryModel::getTaxonomy())->toBe('category');
  });
});

describe('query', function (): void {
  it('injects the taxonomy ahead of the caller args', function (): void {
    $query = TestCategoryModel::query(['hide_empty' => false]);

    expect($query)->toBeInstanceOf(WP_Term_Query::class)
      ->and($query->query_vars)->toBe(['taxonomy' => 'category', 'hide_empty' => false]);
  });

  it('returns the mocked terms', function (): void {
    \WP_Term_Query::$mockTerms = [new WP_Term(['term_id' => 1]), new WP_Term(['term_id' => 2])];

    $query = TestCategoryModel::query();

    expect($query->get_terms())->toHaveCount(2);
  });
});

describe('getById', function (): void {
  it('forwards the taxonomy with default output and filter to get_term', function (): void {
    $term = new WP_Term(['term_id' => 9]);

    Functions\expect('get_term')
      ->once()
      ->with(9, 'category', OBJECT, 'raw')
      ->andReturn($term);

    expect(TestCategoryModel::getById(9))->toBe($term);
  });

  it('passes through a custom output and filter', function (): void {
    Functions\expect('get_term')
      ->once()
      ->with(9, 'category', ARRAY_A, 'sanitize')
      ->andReturn(['term_id' => 9]);

    expect(TestCategoryModel::getById(9, ARRAY_A, 'sanitize'))->toBe(['term_id' => 9]);
  });

  it('returns null when the term is missing', function (): void {
    Functions\when('get_term')->justReturn(null);

    expect(TestCategoryModel::getById(9))->toBeNull();
  });

  it('returns the WP_Error from get_term', function (): void {
    $error = new WP_Error('invalid_taxonomy', 'bad');

    Functions\when('get_term')->justReturn($error);

    expect(TestCategoryModel::getById(9))->toBe($error);
  });
});

describe('findBySlug', function (): void {
  it('queries get_term_by on the slug field for the bound taxonomy', function (): void {
    $term = new WP_Term(['slug' => 'news']);

    Functions\expect('get_term_by')
      ->once()
      ->with('slug', 'news', 'category', OBJECT, 'raw')
      ->andReturn($term);

    expect(TestCategoryModel::findBySlug('news'))->toBe($term);
  });

  it('returns false when no term matches', function (): void {
    Functions\when('get_term_by')->justReturn(false);

    expect(TestCategoryModel::findBySlug('missing'))->toBeFalse();
  });
});

describe('getForPost', function (): void {
  it('returns the terms attached to a post', function (): void {
    $terms = [new WP_Term(['term_id' => 1])];

    Functions\expect('get_the_terms')
      ->once()
      ->with(15, 'category')
      ->andReturn($terms);

    expect(TestCategoryModel::getForPost(15))->toBe($terms);
  });

  it('returns false when the post has no terms', function (): void {
    Functions\when('get_the_terms')->justReturn(false);

    expect(TestCategoryModel::getForPost(15))->toBeFalse();
  });

  it('returns the WP_Error from get_the_terms', function (): void {
    $error = new WP_Error('invalid_taxonomy', 'bad');

    Functions\when('get_the_terms')->justReturn($error);

    expect(TestCategoryModel::getForPost(15))->toBe($error);
  });
});

describe('create', function (): void {
  it('forwards the name, taxonomy and args to wp_insert_term', function (): void {
    Functions\expect('wp_insert_term')
      ->once()
      ->with('News', 'category', ['slug' => 'news'])
      ->andReturn(['term_id' => 5, 'term_taxonomy_id' => 5]);

    expect(TestCategoryModel::create('News', ['slug' => 'news']))->toBe(['term_id' => 5, 'term_taxonomy_id' => 5]);
  });

  it('defaults the args to an empty array', function (): void {
    Functions\expect('wp_insert_term')
      ->once()
      ->with('News', 'category', [])
      ->andReturn(['term_id' => 5]);

    TestCategoryModel::create('News');
  });

  it('returns the WP_Error from wp_insert_term', function (): void {
    $error = new WP_Error('term_exists', 'already there');

    Functions\when('wp_insert_term')->justReturn($error);

    expect(TestCategoryModel::create('News'))->toBe($error);
  });
});

describe('update', function (): void {
  it('forwards the id, taxonomy and args to wp_update_term', function (): void {
    Functions\expect('wp_update_term')
      ->once()
      ->with(5, 'category', ['name' => 'Updated'])
      ->andReturn(['term_id' => 5, 'term_taxonomy_id' => 5]);

    expect(TestCategoryModel::update(5, ['name' => 'Updated']))->toBe(['term_id' => 5, 'term_taxonomy_id' => 5]);
  });

  it('returns the WP_Error from wp_update_term', function (): void {
    $error = new WP_Error('invalid_term', 'nope');

    Functions\when('wp_update_term')->justReturn($error);

    expect(TestCategoryModel::update(5, []))->toBe($error);
  });
});

describe('delete', function (): void {
  it('forwards the id and taxonomy to wp_delete_term', function (): void {
    Functions\expect('wp_delete_term')
      ->once()
      ->with(5, 'category')
      ->andReturn(true);

    expect(TestCategoryModel::delete(5))->toBeTrue();
  });

  it('returns false when the term does not exist', function (): void {
    Functions\when('wp_delete_term')->justReturn(false);

    expect(TestCategoryModel::delete(5))->toBeFalse();
  });

  it('returns zero when deleting the default category', function (): void {
    Functions\when('wp_delete_term')->justReturn(0);

    expect(TestCategoryModel::delete(1))->toBe(0);
  });

  it('returns the WP_Error from wp_delete_term', function (): void {
    $error = new WP_Error('invalid_taxonomy', 'bad');

    Functions\when('wp_delete_term')->justReturn($error);

    expect(TestCategoryModel::delete(5))->toBe($error);
  });
});

describe('attachToPost', function (): void {
  it('sets the object terms without appending by default', function (): void {
    Functions\expect('wp_set_object_terms')
      ->once()
      ->with(15, [1, 2], 'category', false)
      ->andReturn([1, 2]);

    expect(TestCategoryModel::attachToPost(15, [1, 2]))->toBe([1, 2]);
  });

  it('appends when requested', function (): void {
    Functions\expect('wp_set_object_terms')
      ->once()
      ->with(15, [3], 'category', true)
      ->andReturn([1, 2, 3]);

    expect(TestCategoryModel::attachToPost(15, [3], true))->toBe([1, 2, 3]);
  });

  it('returns the WP_Error from wp_set_object_terms', function (): void {
    $error = new WP_Error('invalid_term', 'nope');

    Functions\when('wp_set_object_terms')->justReturn($error);

    expect(TestCategoryModel::attachToPost(15, [1]))->toBe($error);
  });
});

describe('detachFromPost', function (): void {
  it('clears every term for the post via an empty, non-appending set', function (): void {
    Functions\expect('wp_set_object_terms')
      ->once()
      ->with(15, [], 'category', false)
      ->andReturn([]);

    expect(TestCategoryModel::detachFromPost(15))->toBe([]);
  });
});

describe('getAncestors', function (): void {
  it('throws when the starting term cannot be resolved', function (): void {
    Functions\when('get_term')->justReturn(null);

    expect(fn () => TestCategoryModel::getAncestors(9))
      ->toThrow(InvalidArgumentException::class, 'Term not found');
  });

  it('returns an empty array for a root level term', function (): void {
    Functions\when('get_term')->justReturn(new WP_Term(['term_id' => 9, 'parent' => 0]));

    expect(TestCategoryModel::getAncestors(9))->toBe([]);
  });

  it('walks the parent chain to the root', function (): void {
    $child = new WP_Term(['term_id' => 3, 'parent' => 2]);
    $mid = new WP_Term(['term_id' => 2, 'parent' => 1]);
    $root = new WP_Term(['term_id' => 1, 'parent' => 0]);

    Functions\when('get_term')->alias(static function (int $id) use ($child, $mid, $root): WP_Term {
      return match ($id) {
        3 => $child,
        2 => $mid,
        default => $root,
      };
    });

    expect(TestCategoryModel::getAncestors(3))->toBe([$mid, $root]);
  });

  it('stops walking when a parent fails to resolve to a WP_Term', function (): void {
    $child = new WP_Term(['term_id' => 3, 'parent' => 2]);

    Functions\when('get_term')->alias(static function (int $id) use ($child): mixed {
      return $id === 3 ? $child : null;
    });

    expect(TestCategoryModel::getAncestors(3))->toBe([]);
  });
});

describe('exists', function (): void {
  it('is true when term_exists returns a non-null value', function (): void {
    Functions\expect('term_exists')
      ->once()
      ->with('News', 'category')
      ->andReturn(5);

    expect(TestCategoryModel::exists('News'))->toBeTrue();
  });

  it('is false when term_exists returns null', function (): void {
    Functions\when('term_exists')->justReturn(null);

    expect(TestCategoryModel::exists('Missing'))->toBeFalse();
  });

  it('is true even when term_exists returns zero', function (): void {
    Functions\when('term_exists')->justReturn(0);

    expect(TestCategoryModel::exists('Zero'))->toBeTrue();
  });
});

describe('getCounts', function (): void {
  it('returns the total and the with_posts counts as ints', function (): void {
    Functions\when('wp_count_terms')->alias(static function (array $args): string {
      return ($args['hide_empty'] ?? false) === true ? '4' : '10';
    });

    expect(TestCategoryModel::getCounts())->toBe(['total' => 10, 'with_posts' => 4]);
  });

  it('falls back to zero when wp_count_terms returns a WP_Error', function (): void {
    Functions\when('wp_count_terms')->justReturn(new WP_Error('invalid_taxonomy', 'bad'));

    expect(TestCategoryModel::getCounts())->toBe(['total' => 0, 'with_posts' => 0]);
  });

  it('handles the total erroring while the with_posts count succeeds', function (): void {
    Functions\when('wp_count_terms')->alias(static function (array $args): mixed {
      return ($args['hide_empty'] ?? false) === true ? '4' : new WP_Error('boom', 'x');
    });

    expect(TestCategoryModel::getCounts())->toBe(['total' => 0, 'with_posts' => 4]);
  });
});
