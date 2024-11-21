<?php

declare(strict_types=1);

namespace Fern\Core\Models;

use InvalidArgumentException;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * A class that provides more readable methods for working with post types & posts.
 */
abstract class PostTypeModel {
  /**
   * Get the post type.
   */
  abstract public static function getPostType(): string;

  /**
   * Query the posts.
   *
   * @param array<string, mixed> $args
   */
  public static function query(array $args = []): WP_Query {
    return new WP_Query([...$args, 'post_type' => static::getPostType()]);
  }

  /**
   * Get a post by its ID.
   *
   * @param 'OBJECT'|'ARRAY_A'|'ARRAY_N' $output Output type (OBJECT, ARRAY_A, or ARRAY_N)
   * @param 'db'|'display'|'edit'|'raw'  $filter Filter type (raw, sanitize, or translate)
   *
   * @return array<int|string, mixed>|WP_Post|null
   */
  public static function getById(int $id, string $output = OBJECT, string $filter = 'raw'): WP_Post|array|null {
    return get_post($id, $output, $filter);
  }

  /**
   * Create a new post.
   *
   * @param array{
   *     post_title?: string,
   *     post_content?: string,
   *     post_status?: string,
   *     post_author?: int,
   *     post_excerpt?: string,
   *     post_date?: string,
   *     post_date_gmt?: string,
   *     post_modified?: string,
   *     post_modified_gmt?: string,
   *     post_parent?: int,
   *     menu_order?: int,
   *     post_password?: string,
   *     comment_status?: string,
   *     ping_status?: string,
   *     post_name?: string,
   *     meta_input?: array<string, mixed>
   * } $data Post data
   *
   * @throws InvalidArgumentException If required fields are missing
   *
   * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
   */
  public static function create(array $data): int|WP_Error {
    return wp_insert_post([
      'post_type' => static::getPostType(),
      ...$data,
    ], true);
  }

  /**
   * Update a post.
   *
   * @param int                  $id   Post ID
   * @param array<string, mixed> $data Update data
   *
   * @return int|WP_Error Post ID or error
   */
  public static function update(int $id, array $data): int|WP_Error {
    return wp_update_post([
      'ID' => $id,
      'post_type' => static::getPostType(),
      ...$data,
    ], true, true);
  }

  /**
   * Delete a post.
   *
   * @param int  $id    Post ID
   * @param bool $force Whether to bypass trash
   */
  public static function delete(int $id, bool $force = false): WP_Post|false|null {
    return wp_delete_post($id, $force);
  }

  /**
   * Get multiple posts by IDs.
   *
   * @param array<int> $ids Array of post IDs
   *
   * @return array<int, WP_Post>
   */
  public static function findByIds(array $ids): array {
    $posts = static::query([
      'post__in' => $ids,
      'posts_per_page' => -1,
      'orderby' => 'post__in',
    ])->get_posts();

    return array_filter($posts, fn($post) => $post instanceof WP_Post);
  }

  /**
   * Check if a post exists.
   *
   * @param int $id Post ID
   */
  public static function exists(int $id): bool {
    return static::getById($id) instanceof WP_Post;
  }

  /**
   * Get the latest posts.
   *
   * @param int $limit Number of posts to return
   *
   * @return array<int|WP_Post>
   */
  public static function latest(int $limit = 10): array {
    return static::query([
      'posts_per_page' => $limit,
      'orderby' => 'date',
      'order' => 'DESC',
    ])->get_posts();
  }

  /**
   * Get posts by author.
   *
   * @param int $authorId The author ID
   * @param int $limit    Posts per page (-1 for all)
   *
   * @return array<int|WP_Post>
   */
  public static function findByAuthor(int $authorId, int $limit = -1): array {
    return static::query([
      'author' => $authorId,
      'posts_per_page' => $limit,
    ])->get_posts();
  }

  /**
   * Get posts by date range.
   *
   * @param string $after  Date string (Y-m-d)
   * @param string $before Date string (Y-m-d)
   *
   * @return array<int|WP_Post>
   */
  public static function findByDateRange(string $after, string $before): array {
    return static::query([
      'date_query' => [
        [
          'after' => $after,
          'before' => $before,
          'inclusive' => true,
        ],
      ],
    ])->get_posts();
  }

  /**
   * Search posts.
   *
   * @param string               $search    Search term
   * @param array<string, mixed> $extraArgs Additional query arguments
   *
   * @return array<int|WP_Post>
   */
  public static function search(string $search, array $extraArgs = []): array {
    return static::query([
      's' => $search,
      ...$extraArgs,
    ])->get_posts();
  }

  /**
   * Get posts by their status.
   *
   * @param string $status Post status (publish, draft, private, etc.)
   *
   * @return array<int|WP_Post>
   */
  public static function findByStatus(string $status): array {
    return static::query([
      'post_status' => $status,
    ])->get_posts();
  }

  /**
   * Get post counts by status.
   *
   * @param string $status Post status (any, publish, draft, private, etc.)
   *
   * @return array<string, int>|int
   */
  public static function getStatusCounts(string $status = 'any'): array|int {
    $counts = wp_count_posts(static::getPostType());

    if ($status !== 'any') {
      return (int) $counts->{$status};
    }

    return array_map('intval', (array) $counts);
  }
}
