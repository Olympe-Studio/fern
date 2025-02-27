<?php

declare(strict_types=1);

namespace Fern\Core\Services\Pagination;

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
 * @method static PaginationData getPaginationData(WP_Query $query, int $range = self::DEFAULT_PAGE_RANGE)
 * @method static array<int|string> getPageRange(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE)
 * @method static PaginationLinks getPaginationLinks(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE)
 * @method static string getPageUrl(int $page, string $base = '')
 * @method static bool hasPagination(WP_Query $query)
 */
class Pagination {
  public const DEFAULT_PAGE_RANGE = 2;

  public const PAGE_QUERY_VAR = 'paged';

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
   * Get pagination data with multiple formats
   *
   * @param WP_Query $query The query object
   * @param int      $range The range of pages to show
   *
   * @return PaginationData
   */
  public static function getPaginationData(WP_Query $query, int $range = self::DEFAULT_PAGE_RANGE): array {
    $currentPage = self::getCurrentPage();
    $totalPages = (int) $query->max_num_pages;
    $foundPosts = (int) $query->found_posts;

    /** @var PaginationData */
    return [
      'current' => $currentPage,
      'total' => $totalPages,
      'found' => $foundPosts,
      'links' => self::getPaginationLinks($currentPage, $totalPages, $range),
      'range' => self::getPageRange($currentPage, $totalPages, $range),
      'has_previous' => self::hasPreviousPage($currentPage),
      'has_next' => self::hasNextPage($query, $currentPage),
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
  public static function getPageRange(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE): array {
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
   *
   * @return PaginationLinks
   */
  public static function getPaginationLinks(int $current, int $total, int $range = self::DEFAULT_PAGE_RANGE): array {
    /** @var PaginationLinks */
    $links = [
      'current' => self::getPageUrl($current),
      'previous' => $current > 1 ? self::getPageUrl($current - 1) : null,
      'next' => $current < $total ? self::getPageUrl($current + 1) : null,
      'first' => self::getPageUrl(1),
      'last' => self::getPageUrl($total),
      'pages' => [],
    ];

    foreach (self::getPageRange($current, $total, $range) as $page) {
      if ($page === '...') {
        $links['pages'][] = ['type' => 'ellipsis'];
        continue;
      }

      $page = (int) $page;
      $links['pages'][] = [
        'number' => $page,
        'url' => self::getPageUrl($page),
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
  public static function getPageUrl(int $page, string $base = ''): string {
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
   */
  public static function hasPagination(WP_Query $query): bool {
    return (int) $query->max_num_pages > 1;
  }

  /**
   * Check if there's a previous page
   */
  public static function hasPreviousPage(?int $current = null): bool {
    $current ??= self::getCurrentPage();

    return $current > 1;
  }

  /**
   * Check if there's a next page
   */
  public static function hasNextPage(WP_Query $query, ?int $current = null): bool {
    $current ??= self::getCurrentPage();

    return $current < (int) $query->max_num_pages;
  }

  /**
   * Calculate offset for WP_Query
   */
  public static function getOffset(int $perPage, ?int $current = null): int {
    $current ??= self::getCurrentPage();

    return ($current - 1) * $perPage;
  }

  /**
   * Get pagination summary text
   */
  public static function getSummary(WP_Query $query): string {
    $current = self::getCurrentPage();
    $perPage = (int) $query->query_vars['posts_per_page'];
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
}