<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WordPress class stubs
|--------------------------------------------------------------------------
|
| Lightweight, behaviour-light fakes for WordPress data classes the framework
| type-checks against (instanceof) or constructs. Loaded once via autoload-dev.
|
| NOTE: only data-holder classes belong here. Behaviour-heavy classes such as
| WP_Query are intentionally left undefined so Phase 3 can use Mockery overload.
|
*/

if (!class_exists('WP_Error')) {
  class WP_Error {
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $error_data = [];

    public function __construct(string|int $code = '', string $message = '', mixed $data = '') {
      if ($code !== '' && $code !== 0) {
        $this->errors[(string) $code][] = $message;

        if ($data !== '') {
          $this->error_data[(string) $code] = $data;
        }
      }
    }

    public function get_error_message(string|int $code = ''): string {
      $code = $code === '' ? array_key_first($this->errors) : (string) $code;

      return $code !== null && isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
    }

    public function get_error_code(): string|int {
      return array_key_first($this->errors) ?? '';
    }

    /**
     * @return array<int, string|int>
     */
    public function get_error_codes(): array {
      return array_keys($this->errors);
    }

    public function has_errors(): bool {
      return $this->errors !== [];
    }
  }
}

if (!class_exists('WP_Post')) {
  #[AllowDynamicProperties]
  class WP_Post {
    public int $ID = 0;

    public string $post_type = 'post';

    public string $post_status = 'publish';

    public string $post_title = '';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = []) {
      foreach ($data as $key => $value) {
        $this->{$key} = $value;
      }
    }
  }
}

if (!class_exists('WP_Term')) {
  #[AllowDynamicProperties]
  class WP_Term {
    public int $term_id = 0;

    public string $taxonomy = 'category';

    public string $slug = '';

    public string $name = '';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = []) {
      foreach ($data as $key => $value) {
        $this->{$key} = $value;
      }
    }
  }
}

if (!class_exists('WP_User')) {
  #[AllowDynamicProperties]
  class WP_User {
    public int $ID = 0;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = []) {
      foreach ($data as $key => $value) {
        $this->{$key} = $value;
      }
    }
  }
}

if (!class_exists('WP_Query')) {
  /**
   * Canonical WP_Query stub. Supports two construction styles so a single shared
   * definition works across tests:
   *   - real API:    new WP_Query(['post_type' => 'page'])  // array of query vars
   *   - convenience: new WP_Query($foundPosts, $maxNumPages, $queryVars)
   * For the array form, set WP_Query::$mockPosts BEFORE constructing to control
   * the returned posts (reset it to [] between tests).
   */
  #[AllowDynamicProperties]
  class WP_Query {
    /** @var array<int, mixed> Posts returned by the real-API (array-args) construction path. */
    public static array $mockPosts = [];

    /** @var array<int, mixed> */
    public array $posts = [];

    public int $post_count = 0;

    public int $found_posts = 0;

    public int $max_num_pages = 1;

    /** @var array<string, mixed> */
    public array $query_vars = [];

    /**
     * @param array<string, mixed>|int $query A query-vars array, or the found-posts count.
     * @param array<string, mixed>     $vars  Query vars for the convenience form.
     */
    public function __construct(array|int $query = [], int $maxNumPages = 1, array $vars = []) {
      if (is_array($query)) {
        $this->query_vars = $query;
        $this->posts = self::$mockPosts;
        $this->post_count = count(self::$mockPosts);
        $this->found_posts = count(self::$mockPosts);
      } else {
        $this->found_posts = $query;
        $this->max_num_pages = $maxNumPages;
        $this->query_vars = $vars;
      }
    }

    /**
     * @return array<int, mixed>
     */
    public function get_posts(): array {
      return $this->posts;
    }
  }
}

if (!defined('OBJECT')) {
  define('OBJECT', 'OBJECT');
}

if (!defined('ARRAY_A')) {
  define('ARRAY_A', 'ARRAY_A');
}

if (!defined('ARRAY_N')) {
  define('ARRAY_N', 'ARRAY_N');
}

if (!class_exists('WP_Term_Query')) {
  /**
   * Canonical WP_Term_Query stub. Set WP_Term_Query::$mockTerms BEFORE
   * constructing to control the returned terms (reset to [] between tests).
   */
  #[AllowDynamicProperties]
  class WP_Term_Query {
    /** @var array<int, mixed> */
    public static array $mockTerms = [];

    /** @var array<int, mixed> */
    public array $terms = [];

    /** @var array<string, mixed> */
    public array $query_vars = [];

    /**
     * @param array<string, mixed> $query
     */
    public function __construct(array $query = []) {
      $this->query_vars = $query;
      $this->terms = self::$mockTerms;
    }

    /**
     * @return array<int, mixed>
     */
    public function get_terms(): array {
      return $this->terms;
    }
  }
}
