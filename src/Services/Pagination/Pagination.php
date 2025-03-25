<?php

declare(strict_types=1);

namespace Fern\Core\Services\Pagination;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\HTTP\Request;
use RuntimeException;
use WP_Query;
use WP_Rewrite;

/**
 * @phpstan-type PaginationLinks array{
 *   current: string,
 *   previous: string|null,
 *   next: string|null,
 *   first: string,
 *   last: string,
 *   pages: array<array{
 *     type?: string,
 *     number?: int,
 *     url?: string,
 *     current?: bool
 *   }>
 * }
 *
 * @phpstan-type PaginationData array{
 *   current: int,
 *   total: int,
 *   found: int,
 *   links: PaginationLinks,
 *   range: array<int|string>,
 *   has_previous: bool,
 *   has_next: bool
 * }
 *
 * @method static int getCurrentPage()
 * @method static PaginationData getPaginationData(int $range = self::DEFAULT_PAGE_RANGE, ?int $postsPerPage = null, ?int $totalPages = null)
 * @method static PaginationData getCurrentPagination(int $range = self::DEFAULT_PAGE_RANGE, ?int $postsPerPage = null, ?int $totalPages = null)
 * @method static array<int|string> getPageRange(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE)
 * @method static PaginationLinks getPaginationLinks(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE)
 * @method static string getPageUrl(int $page, string $base = '')
 * @method static bool hasPagination()
 */
class Pagination extends Singleton {
  public const DEFAULT_PAGE_RANGE = 2;

  public const PAGE_QUERY_VAR = 'paged';

  /**
   * Current WordPress query
   */
  private ?WP_Query $wpQuery = null;

  /**
   * Initialize pagination with WordPress state
   */
  public function __construct() {
    global $wp_query;
    $this->wpQuery = $wp_query;
  }

  /**
   * Get current WordPress query
   *
   * @return WP_Query The current WordPress query
   */
  private function getQuery(): WP_Query {
    if (!$this->wpQuery instanceof WP_Query) {
      global $wp_query;
      $this->wpQuery = $wp_query;
    }

    return $this->wpQuery;
  }

  /**
   * Get current page number with WordPress context awareness
   *
   * @return int
   */
  public static function getCurrentPage(): int {
    global $paged, $page;

    if (get_query_var(self::PAGE_QUERY_VAR)) {
      return (int) get_query_var(self::PAGE_QUERY_VAR);
    }

    if ($paged) {
      return (int) $paged;
    }

    if ($page) {
      return (int) $page;
    }

    return 1;
  }

  /**
   * Get current pagination data
   *
   * @param int $range The range of pages to show
   * @param int|null $postsPerPage Optional number of posts per page override
   * @param int|null $totalPages Optional total pages override for custom queries
   *
   * @return PaginationData
   */
  public static function getCurrentPagination(int $range = self::DEFAULT_PAGE_RANGE, ?int $postsPerPage = null, ?int $totalPages = null): array {
    return self::getInstance()->getPaginationData($range, $postsPerPage, $totalPages);
  }

  /**
   * Get pagination data with multiple formats
   *
   * @param int $range The range of pages to show
   * @param int|null $postsPerPage Optional number of posts per page override
   * @param int|null $totalPages Optional total pages override for custom queries
   *
   * @return PaginationData
   */
  public function getPaginationData(int $range = self::DEFAULT_PAGE_RANGE, ?int $postsPerPage = null, ?int $totalPages = null): array {
    $currentPage = self::getCurrentPage();
    $query = $this->getQuery();
    $foundPosts = (int) $query->found_posts;

    // Get actual posts per page
    $perPage = $postsPerPage ?? (int) $query->query_vars['posts_per_page'];
    if ($perPage <= 0) {
      $perPage = get_option('posts_per_page', 10);
    }

    // Use explicit total pages if provided (for custom queries)
    if ($totalPages !== null) {
      $totalPages = max(1, $totalPages);
    }
    // When custom postsPerPage is provided but no totalPages, calculate from posts per page
    else if ($postsPerPage !== null) {
      // For custom queries with explicit postsPerPage, we trust the calculated pages
      $totalPages = (int) max(1, ceil($foundPosts / $perPage));
    }
    // Standard WordPress query handling with edge case fixes
    else {
      // For standard WordPress queries, we need to handle edge cases
      $calculatedPages = ceil($foundPosts / $perPage);
      $totalPages = (int) max(1, $calculatedPages);

      // Force to single page for edge cases in WordPress's internal query
      if ($foundPosts <= $perPage) {
        $totalPages = 1;
      }
    }

    // If we're on page 2+ but there are no posts, force page 1
    if ($currentPage > 1 && $foundPosts <= 0) {
      $currentPage = 1;
    }

    // If we're on a page that doesn't exist, force to last page
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
    }

    /** @var PaginationData */
    return [
      'current' => $currentPage,
      'total' => $totalPages,
      'found' => $foundPosts,
      'links' => $this->getPaginationLinks($currentPage, $totalPages, $range),
      'range' => $this->getPageRange($currentPage, $totalPages, $range),
      'has_previous' => $this->hasPreviousPage($currentPage),
      'has_next' => $this->hasNextPage($currentPage, $totalPages),
    ];
  }

  /**
   * Generate page range with ellipsis
   *
   * @param int $current The current page number
   * @param int $total   The total number of pages
   * @param int $range   The range of pages to show
   *
   * @return array<int|string>
   */
  public function getPageRange(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE): array {
    $pages = [];
    $min = max(1, $current - $range);
    $max = min($total, $current + $range);

    if ($min > 1) {
      $pages[] = 1;

      if ($min > 2) {
        $pages[] = '...';
      }
    }

    for ($i = $min; $i <= $max; $i++) {
      $pages[] = $i;
    }

    if ($max < $total) {
      if ($max < $total - 1) {
        $pages[] = '...';
      }
      $pages[] = $total;
    }

    return $pages;
  }

  /**
   * Get pagination links with various types
   *
   * @param int $current The current page number
   * @param int $total   The total number of pages
   * @param int $range   The range of pages to show
   *
   * @return PaginationLinks
   */
  public function getPaginationLinks(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE): array {
    /** @var PaginationLinks */
    $links = [
      'current' => $this->getPageUrl($current),
      'previous' => $current > 1 ? $this->getPageUrl($current - 1) : null,
      'next' => $current < $total ? $this->getPageUrl($current + 1) : null,
      'first' => $this->getPageUrl(1),
      'last' => $this->getPageUrl($total),
      'pages' => [],
    ];

    foreach ($this->getPageRange($current, $total, $range) as $page) {
      if ($page === '...') {
        $links['pages'][] = ['type' => 'ellipsis'];
        continue;
      }

      $page = (int) $page;
      $links['pages'][] = [
        'number' => $page,
        'url' => $this->getPageUrl($page),
        'current' => $page === $current,
      ];
    }

    return $links;
  }

  /**
   * Generate page URL with query string preservation
   *
   * @param int    $page  The page number
   * @param string $base  The base URL
   *
   * @return string
   *
   * @throws RuntimeException When WordPress functions are not available
   */
  public function getPageUrl(int $page, string $base = ''): string {
    global $wp_rewrite;

    if (!$base) {
      $base = get_pagenum_link(1, false);
    }

    if (!$wp_rewrite instanceof WP_Rewrite) {
      throw new RuntimeException('WordPress rewrite component not initialized');
    }

    if ($wp_rewrite->using_permalinks() && !str_contains($base, '?')) {
      return trailingslashit($base) . user_trailingslashit("{$wp_rewrite->pagination_base}/{$page}", 'paged');
    }

    return add_query_arg(self::PAGE_QUERY_VAR, $page, $base);
  }

  /**
   * Check if pagination is needed
   *
   * @param int|null $totalPages Optional total pages override
   * @return bool
   */
  public function hasPagination(?int $totalPages = null): bool {
    $totalPages ??= (int) $this->getQuery()->max_num_pages;
    return $totalPages > 1;
  }

  /**
   * Check if there's a previous page
   *
   * @param int|null $current Optional current page
   * @return bool
   */
  public function hasPreviousPage(?int $current = null): bool {
    $current ??= self::getCurrentPage();

    return $current > 1;
  }

  /**
   * Check if there's a next page
   *
   * @param int|null $current Optional current page
   * @param int|null $totalPages Optional total pages override
   * @return bool
   */
  public function hasNextPage(?int $current = null, ?int $totalPages = null): bool {
    $current ??= self::getCurrentPage();
    $totalPages ??= (int) $this->getQuery()->max_num_pages;

    return $current < $totalPages;
  }

  /**
   * Calculate offset for WP_Query
   *
   * @param int $perPage Number of items per page
   * @param int|null $current Optional current page
   * @return int
   */
  public function getOffset(int $perPage, ?int $current = null): int {
    $current ??= self::getCurrentPage();

    return ($current - 1) * $perPage;
  }

  /**
   * Get pagination summary text
   *
   * @param int|null $postsPerPage Optional number of posts per page override
   * @return string
   */
  public function getSummary(?int $postsPerPage = null): string {
    $query = $this->getQuery();
    $current = self::getCurrentPage();
    $perPage = $postsPerPage ?? (int) $query->query_vars['posts_per_page'];
    $total = (int) $query->found_posts;

    $from = (($current - 1) * $perPage) + 1;
    $to = min($current * $perPage, $total);

    return sprintf(
      'Showing %d-%d of %d results',
      $from,
      $to,
      $total,
    );
  }

  /**
   * Static method to get instance for method chaining
   *
   * @return self
   */
  public static function get(): self {
    return self::getInstance();
  }
}
