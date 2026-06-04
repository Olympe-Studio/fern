<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\Pagination\Pagination;

if (!class_exists('WP_Query')) {
  class WP_Query {
    public int $found_posts = 0;

    public int $max_num_pages = 1;

    /** @var array<string, mixed> */
    public array $query_vars = [];

    /**
     * @param array<string, mixed> $vars
     */
    public function __construct(int $found = 0, int $maxPages = 1, array $vars = []) {
      $this->found_posts = $found;
      $this->max_num_pages = $maxPages;
      $this->query_vars = $vars;
    }
  }
}

if (!class_exists('WP_Rewrite')) {
  class WP_Rewrite {
    public string $pagination_base = 'page';

    public function __construct(private bool $usingPermalinks = false) {
    }

    public function using_permalinks(): bool {
      return $this->usingPermalinks;
    }
  }
}

/**
 * Builds a Pagination instance backed by the given fake WP_Query.
 */
function makePagination(WP_Query $query): Pagination {
  $GLOBALS['wp_query'] = $query;

  return Pagination::get();
}

beforeEach(function (): void {
  $GLOBALS['paged'] = 0;
  $GLOBALS['page'] = 0;
  unset($GLOBALS['wp_query'], $GLOBALS['wp_rewrite']);
  Functions\when('get_query_var')->justReturn(0);
});

describe('getCurrentPage', function (): void {
  it('prefers the paged query var when positive', function (): void {
    Functions\when('get_query_var')->justReturn(4);

    expect(Pagination::getCurrentPage())->toBe(4);
  });

  it('falls back to the global $paged', function (): void {
    Functions\when('get_query_var')->justReturn(0);
    $GLOBALS['paged'] = 3;

    expect(Pagination::getCurrentPage())->toBe(3);
  });

  it('falls back to the global $page', function (): void {
    Functions\when('get_query_var')->justReturn(0);
    $GLOBALS['paged'] = 0;
    $GLOBALS['page'] = 2;

    expect(Pagination::getCurrentPage())->toBe(2);
  });

  it('defaults to 1 when nothing is set', function (): void {
    expect(Pagination::getCurrentPage())->toBe(1);
  });
});

describe('getPageRange', function (): void {
  it('produces the expected range with ellipses', function (int $current, int $total, int $range, array $expected): void {
    $pagination = Pagination::get();

    expect($pagination->getPageRange($current, $total, $range))->toBe($expected);
  })->with([
    'single page' => [1, 1, 2, [1]],
    'page 1 of 5' => [1, 5, 2, [1, 2, 3, '...', 5]],
    'last page of 5' => [5, 5, 2, [1, '...', 3, 4, 5]],
    'middle with both ellipses' => [5, 10, 2, [1, '...', 3, 4, 5, 6, 7, '...', 10]],
    'near start no left ellipsis' => [2, 10, 2, [1, 2, 3, 4, '...', 10]],
    'near end no right ellipsis' => [9, 10, 2, [1, '...', 7, 8, 9, 10]],
    'left gap of one collapses ellipsis' => [4, 10, 2, [1, 2, 3, 4, 5, 6, '...', 10]],
    'right neighbour without ellipsis' => [8, 10, 2, [1, '...', 6, 7, 8, 9, 10]],
    'zero range shows only current' => [5, 10, 0, [1, '...', 5, '...', 10]],
  ]);
});

describe('getPageUrl', function (): void {
  it('uses pretty permalinks when available and the base has no query string', function (): void {
    $GLOBALS['wp_rewrite'] = new WP_Rewrite(true);
    Functions\when('get_pagenum_link')->justReturn('https://x.test/blog/');
    Functions\when('trailingslashit')->alias(fn (string $s): string => rtrim($s, '/') . '/');
    Functions\when('user_trailingslashit')->alias(fn (string $s): string => $s . '/');

    $pagination = Pagination::get();

    expect($pagination->getPageUrl(3))->toBe('https://x.test/blog/page/3/');
  });

  it('uses a query arg when permalinks are disabled', function (): void {
    $GLOBALS['wp_rewrite'] = new WP_Rewrite(false);
    Functions\when('get_pagenum_link')->justReturn('https://x.test/blog/');
    Functions\expect('add_query_arg')->once()->with('paged', 2, 'https://x.test/blog/')->andReturn('https://x.test/blog/?paged=2');

    $pagination = Pagination::get();

    expect($pagination->getPageUrl(2))->toBe('https://x.test/blog/?paged=2');
  });

  it('uses a query arg when permalinks are on but the base already has a query string', function (): void {
    $GLOBALS['wp_rewrite'] = new WP_Rewrite(true);
    Functions\expect('add_query_arg')->once()->with('paged', 5, 'https://x.test/?s=hi')->andReturn('https://x.test/?s=hi&paged=5');

    $pagination = Pagination::get();

    expect($pagination->getPageUrl(5, 'https://x.test/?s=hi'))->toBe('https://x.test/?s=hi&paged=5');
  });

  it('throws when the rewrite component is not initialized', function (): void {
    $GLOBALS['wp_rewrite'] = null;
    Functions\when('get_pagenum_link')->justReturn('https://x.test/');

    $pagination = Pagination::get();

    expect(fn () => $pagination->getPageUrl(2))
      ->toThrow(RuntimeException::class, 'WordPress rewrite component not initialized');
  });
});

describe('getPaginationLinks', function (): void {
  beforeEach(function (): void {
    Functions\when('get_pagenum_link')->justReturn('https://x.test/');
    Functions\when('add_query_arg')->alias(fn (string $key, int $page, string $base): string => "{$base}?{$key}={$page}");
    $GLOBALS['wp_rewrite'] = new WP_Rewrite(false);
  });

  it('builds prev/next/first/last and page entries for a middle page', function (): void {
    $pagination = Pagination::get();

    $links = $pagination->getPaginationLinks(3, 5, 2);

    expect($links['current'])->toBe('https://x.test/?paged=3')
      ->and($links['previous'])->toBe('https://x.test/?paged=2')
      ->and($links['next'])->toBe('https://x.test/?paged=4')
      ->and($links['first'])->toBe('https://x.test/?paged=1')
      ->and($links['last'])->toBe('https://x.test/?paged=5')
      ->and($links['pages'])->toHaveCount(5)
      ->and($links['pages'][2])->toBe([
        'number' => 3,
        'url' => 'https://x.test/?paged=3',
        'current' => true,
      ]);
  });

  it('omits previous on the first page and next on the last page', function (): void {
    $pagination = Pagination::get();

    expect($pagination->getPaginationLinks(1, 5, 2)['previous'])->toBeNull();
    expect($pagination->getPaginationLinks(5, 5, 2)['next'])->toBeNull();
  });

  it('emits an ellipsis marker entry within the pages list', function (): void {
    $pagination = Pagination::get();

    $links = $pagination->getPaginationLinks(5, 10, 2);

    expect($links['pages'][1])->toBe(['type' => 'ellipsis']);
  });
});

describe('hasPreviousPage / hasNextPage / getOffset', function (): void {
  it('detects a previous page from an explicit current page', function (): void {
    $pagination = Pagination::get();

    expect($pagination->hasPreviousPage(2))->toBeTrue()
      ->and($pagination->hasPreviousPage(1))->toBeFalse();
  });

  it('detects a next page from explicit arguments', function (): void {
    $pagination = Pagination::get();

    expect($pagination->hasNextPage(2, 5))->toBeTrue()
      ->and($pagination->hasNextPage(5, 5))->toBeFalse();
  });

  it('computes the WP_Query offset', function (int $perPage, int $current, int $expected): void {
    $pagination = Pagination::get();

    expect($pagination->getOffset($perPage, $current))->toBe($expected);
  })->with([
    'page 1' => [10, 1, 0],
    'page 3' => [10, 3, 20],
    'odd size' => [7, 4, 21],
  ]);

  it('uses the current page when no argument is supplied', function (): void {
    Functions\when('get_query_var')->justReturn(3);
    $pagination = Pagination::get();

    expect($pagination->hasPreviousPage())->toBeTrue()
      ->and($pagination->getOffset(10))->toBe(20);
  });

  it('reads the query max_num_pages when total is omitted', function (): void {
    $pagination = makePagination(new WP_Query(0, 4));
    Functions\when('get_query_var')->justReturn(2);

    expect($pagination->hasNextPage())->toBeTrue();
  });
});

describe('hasPagination', function (): void {
  it('uses an explicit total when provided', function (): void {
    $pagination = Pagination::get();

    expect($pagination->hasPagination(3))->toBeTrue()
      ->and($pagination->hasPagination(1))->toBeFalse();
  });

  it('falls back to the query max_num_pages', function (): void {
    $pagination = makePagination(new WP_Query(0, 2));

    expect($pagination->hasPagination())->toBeTrue();
  });
});

describe('getSummary', function (): void {
  it('formats the showing range using the query state', function (): void {
    $pagination = makePagination(new WP_Query(45, 5, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(2);

    expect($pagination->getSummary())->toBe('Showing 11-20 of 45 results');
  });

  it('clamps the upper bound to the total on the last page', function (): void {
    $pagination = makePagination(new WP_Query(45, 5, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(5);

    expect($pagination->getSummary())->toBe('Showing 41-45 of 45 results');
  });

  it('honours an explicit postsPerPage override', function (): void {
    $pagination = makePagination(new WP_Query(20, 4, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(1);

    expect($pagination->getSummary(5))->toBe('Showing 1-5 of 20 results');
  });
});

describe('getPaginationData / getCurrentPagination', function (): void {
  beforeEach(function (): void {
    Functions\when('get_pagenum_link')->justReturn('https://x.test/');
    Functions\when('add_query_arg')->alias(fn (string $key, int $page, string $base): string => "{$base}?{$key}={$page}");
    $GLOBALS['wp_rewrite'] = new WP_Rewrite(false);
  });

  it('computes pagination for a standard multi-page query', function (): void {
    $pagination = makePagination(new WP_Query(25, 3, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(2);

    $data = $pagination->getPaginationData();

    expect($data['current'])->toBe(2)
      ->and($data['total'])->toBe(3)
      ->and($data['found'])->toBe(25)
      ->and($data['has_previous'])->toBeTrue()
      ->and($data['has_next'])->toBeTrue()
      ->and($data['range'])->toBe([1, 2, 3]);
  });

  it('forces a single page when found posts fit on one page', function (): void {
    $pagination = makePagination(new WP_Query(8, 1, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(1);

    $data = $pagination->getPaginationData();

    expect($data['total'])->toBe(1)
      ->and($data['has_next'])->toBeFalse();
  });

  it('falls back to the posts_per_page option when the query lacks one', function (): void {
    $pagination = makePagination(new WP_Query(30, 3, []));
    Functions\when('get_query_var')->justReturn(1);
    Functions\expect('get_option')->once()->with('posts_per_page', 10)->andReturn(5);

    $data = $pagination->getPaginationData();

    expect($data['total'])->toBe(6);
  });

  it('honours an explicit totalPages override clamped to at least one', function (): void {
    $pagination = makePagination(new WP_Query(0, 1, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(1);

    expect($pagination->getPaginationData(2, null, 7)['total'])->toBe(7);
    expect($pagination->getPaginationData(2, null, 0)['total'])->toBe(1);
  });

  it('derives totalPages from an explicit postsPerPage override', function (): void {
    $pagination = makePagination(new WP_Query(21, 1, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(1);

    expect($pagination->getPaginationData(2, 5)['total'])->toBe(5);
  });

  it('forces the current page back to 1 when beyond the first but no posts exist', function (): void {
    $pagination = makePagination(new WP_Query(0, 1, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(3);

    expect($pagination->getPaginationData()['current'])->toBe(1);
  });

  it('clamps the current page to the last page when out of range', function (): void {
    $pagination = makePagination(new WP_Query(25, 3, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(9);

    expect($pagination->getPaginationData()['current'])->toBe(3);
  });

  it('is reachable through the static getCurrentPagination helper', function (): void {
    makePagination(new WP_Query(25, 3, ['posts_per_page' => 10]));
    Functions\when('get_query_var')->justReturn(1);

    $data = Pagination::getCurrentPagination();

    expect($data['current'])->toBe(1)
      ->and($data['total'])->toBe(3);
  });
});

describe('getQuery fallback', function (): void {
  it('falls back to a fresh WP_Query when the global is absent', function (): void {
    unset($GLOBALS['wp_query']);

    $pagination = Pagination::get();

    expect($pagination->hasPagination())->toBeFalse();
  });
});
