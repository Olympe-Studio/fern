<?php

declare(strict_types=1);

namespace Fern\Core\Models;

use InvalidArgumentException;
use WP_Error;
use WP_Term;
use WP_Term_Query;

/**
 * A trait that provides more readable methods for working with taxonomies & terms.
 */
abstract class TaxonomyModel {
  /**
   * Get the taxonomy name.
   */
  abstract public static function getTaxonomy(): string;

  /**
   * Query the terms.
   *
   * @param array<string, mixed> $args
   */
  public static function query(array $args = []): WP_Term_Query {
    return new WP_Term_Query([
      'taxonomy' => static::getTaxonomy(),
      ...$args,
    ]);
  }

  /**
   * Get a term by its ID.
   *
   * @param int                          $id     The term ID
   * @param 'OBJECT'|'ARRAY_A'|'ARRAY_N' $output Output type (OBJECT, ARRAY_A, or ARRAY_N)
   * @param 'raw'|'sanitize'|'translate' $filter Filter type (raw, sanitize, or translate)
   *
   * @return WP_Term|array<int<0, max>|string, int|string>|WP_Error|null
   */
  public static function getById(int $id, string $output = OBJECT, string $filter = 'raw'): mixed {
    return get_term($id, static::getTaxonomy(), $output, $filter);
  }

  /**
   * Get a single term by slug.
   *
   * @param string                       $slug   Term slug
   * @param 'OBJECT'|'ARRAY_A'|'ARRAY_N' $output Output type (OBJECT, ARRAY_A, or ARRAY_N)
   * @param 'raw'|'sanitize'|'translate' $filter Filter type (raw, sanitize, or translate)
   *
   * @return array<int<0, max>|string, int|string>|WP_Error|WP_Term|false
   */
  public static function findBySlug(string $slug, string $output = OBJECT, string $filter = 'raw'): mixed {
    return get_term_by('slug', $slug, static::getTaxonomy(), $output, $filter);
  }

  /**
   * Get terms for a specific post.
   *
   * @param int $postId Post ID
   *
   * @return WP_Term[]|false|WP_Error
   */
  public static function getForPost(int $postId): mixed {
    return get_the_terms($postId, static::getTaxonomy());
  }

  /**
   * Create a new term.
   *
   * @param string               $name Term name
   * @param array<string, mixed> $args Additional arguments
   */
  public static function create(string $name, array $args = []): mixed {
    return wp_insert_term($name, static::getTaxonomy(), $args);
  }

  /**
   * Update a term.
   *
   * @param int                  $id   Term ID
   * @param array<string, mixed> $args Update arguments
   *
   * @return WP_Error|array<int<0, max>|string, int|string>
   */
  public static function update(int $id, array $args): mixed {
    return wp_update_term($id, static::getTaxonomy(), $args);
  }

  /**
   * Delete a term.
   *
   * @param int $id Term ID
   *
   * @return bool|int|WP_Error True on success, false if term does not exist. Zero on attempted deletion of default Category. WP_Error if the taxonomy does not exist.
   */
  public static function delete(int $id): mixed {
    return wp_delete_term($id, static::getTaxonomy());
  }

  /**
   * Attach terms to a post.
   *
   * @param int   $postId  Post ID
   * @param int[] $termIds Term IDs
   */
  public static function attachToPost(int $postId, array $termIds, bool $append = false): mixed {
    return wp_set_object_terms($postId, $termIds, static::getTaxonomy(), $append);
  }

  /**
   * Detach all terms from a post.
   *
   * @param int $postId Post ID
   */
  public static function detachFromPost(int $postId): mixed {
    return wp_set_object_terms($postId, [], static::getTaxonomy(), false);
  }

  /**
   * Get the term ancestors.
   *
   * @param int $termId Term ID
   *
   * @return array<int, WP_Term>
   *
   * @throws InvalidArgumentException If term ID is invalid or term not found
   */
  public static function getAncestors(int $termId): array {
    $ancestors = [];
    $term = static::getById($termId);

    if (!$term instanceof WP_Term) {
      throw new InvalidArgumentException('Term not found');
    }

    while ($term->parent) {
      $parent = static::getById($term->parent);

      if ($parent instanceof WP_Term) {
        $ancestors[] = $parent;
        $term = $parent;
      } else {
        break;
      }
    }

    return $ancestors;
  }

  /**
   * Check if a term exists.
   *
   * @param string $termName Term name
   */
  public static function exists(string $termName): bool {
    return term_exists($termName, static::getTaxonomy()) !== null;
  }

  /**
   * Get term counts.
   *
   * @return array{
   *    total: int,
   *    with_posts: int
   * }
   */
  public static function getCounts(): array {
    $total = wp_count_terms(['taxonomy' => static::getTaxonomy()]);
    $totalCount = is_wp_error($total) ? 0 : (int) $total;

    $withPosts = wp_count_terms([
      'taxonomy' => static::getTaxonomy(),
      'hide_empty' => true,
    ]);
    $withPostsCount = is_wp_error($withPosts) ? 0 : (int) $withPosts;

    return [
      'total' => $totalCount,
      'with_posts' => $withPostsCount,
    ];
  }
}
