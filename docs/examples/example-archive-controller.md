# Example: Archive Controller

This example demonstrates handling a custom post type archive with pagination, filtering, and search.

## Use Case

You have a "Product" custom post type and need a searchable, filterable archive page.

## File Structure

```
src/App/Controllers/ProductArchiveController.php
src/App/Models/Product.php
resources/src/pages/ProductArchive.astro
```

## Model Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Timber\Post;

class Product {
    /**
     * Transform Timber posts to product arrays
     *
     * @param array<Post> $posts Timber posts
     *
     * @return array<int, array<string, mixed>> Product data
     */
    public static function toArray(array $posts): array {
        return array_map(fn(Post $post) => self::transform($post), $posts);
    }

    /**
     * Transform a single post to product data
     *
     * @param Post $post Timber post
     *
     * @return array<string, mixed> Product data
     */
    private static function transform(Post $post): array {
        $fields = get_fields($post->ID);
        $thumbnail = $post->thumbnail();

        return [
            'id' => $post->ID,
            'title' => $post->title(),
            'url' => $post->link(),
            'excerpt' => $post->preview(30),
            'image' => $thumbnail ? $thumbnail->src() : '',
            'price' => $fields['price'] ?? null,
            'category' => self::getCategory($post),
        ];
    }

    /**
     * Get product category name
     *
     * @param Post $post Timber post
     *
     * @return string|null Category name
     */
    private static function getCategory(Post $post): ?string {
        $terms = $post->terms('product_category');

        if (empty($terms)) {
            return null;
        }

        return $terms[0]->name ?? null;
    }
}
```

## Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Product;
use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class ProductArchiveController extends ViewController implements Controller {
    public static string $handle = 'product';

    private const POSTS_PER_PAGE = 12;

    /**
     * Handle the product archive page request
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $page = max(1, (int) $request->getUrlParam('paged'));
        $search = sanitize_text_field($request->getUrlParam('s') ?? '');
        $category = sanitize_text_field($request->getUrlParam('category') ?? '');

        $args = $this->buildQueryArgs($page, $search, $category);
        $query = Timber::get_posts($args);

        $products = $query ? Product::toArray($query->to_array()) : [];

        return new Reply(200, Views::render('ProductArchive', [
            'title' => 'Products',
            'products' => $products,
            'pagination' => $this->getPagination($query, $page),
            'categories' => $this->getCategories(),
            'currentCategory' => $category,
            'search' => $search,
        ]));
    }

    /**
     * Build WP_Query arguments
     *
     * @param int    $page     Current page number
     * @param string $search   Search query
     * @param string $category Category slug
     *
     * @return array<string, mixed> Query arguments
     */
    private function buildQueryArgs(int $page, string $search, string $category): array {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => self::POSTS_PER_PAGE,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        if ($category !== '') {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_category',
                    'field' => 'slug',
                    'terms' => $category,
                ],
            ];
        }

        return $args;
    }

    /**
     * Get pagination data
     *
     * @param \Timber\PostQuery|null $query Timber query result
     * @param int                    $page  Current page
     *
     * @return array<string, mixed> Pagination data
     */
    private function getPagination($query, int $page): array {
        if (!$query) {
            return ['pages' => 0, 'current' => 1, 'total' => 0];
        }

        $totalPages = $query->pagination()->pages ?? 1;

        return [
            'pages' => $totalPages,
            'current' => $page,
            'total' => $query->found_posts ?? 0,
            'hasNext' => $page < $totalPages,
            'hasPrev' => $page > 1,
        ];
    }

    /**
     * Get all product categories
     *
     * @return array<int, array<string, string>> Category data
     */
    private function getCategories(): array {
        $terms = get_terms([
            'taxonomy' => 'product_category',
            'hide_empty' => true,
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return array_map(fn($term) => [
            'name' => $term->name,
            'slug' => $term->slug,
            'count' => $term->count,
        ], $terms);
    }
}
```

## Frontend Template (Astro)

```astro
---
// resources/src/pages/ProductArchive.astro
import ProductCard from '../components/ProductCard';
import Pagination from '../components/Pagination';

interface Props {
  title: string;
  products: Array<{
    id: number;
    title: string;
    url: string;
    excerpt: string;
    image: string;
    price: number | null;
    category: string | null;
  }>;
  pagination: {
    pages: number;
    current: number;
    total: number;
    hasNext: boolean;
    hasPrev: boolean;
  };
  categories: Array<{
    name: string;
    slug: string;
    count: number;
  }>;
  currentCategory: string;
  search: string;
}

const { title, products, pagination, categories, currentCategory, search } = Astro.props;
---

<Layout title={title}>
  <main class="archive-page">
    <header class="archive-header">
      <h1>{title}</h1>
      <p>{pagination.total} products found</p>
    </header>

    <div class="archive-sidebar">
      <div class="search-form">
        <form method="get" action="">
          <input
            type="search"
            name="s"
            value={search}
            placeholder="Search products..."
          />
          <button type="submit">Search</button>
        </form>
      </div>

      <div class="categories-filter">
        <h3>Categories</h3>
        <ul>
          <li>
            <a
              href="?category="
              class:list={{ active: currentCategory === '' }}
            >
              All Products ({pagination.total})
            </a>
          </li>
          {categories.map(cat => (
            <li>
              <a
                href={`?category=${cat.slug}`}
                class:list={{ active: currentCategory === cat.slug }}
              >
                {cat.name} ({cat.count})
              </a>
            </li>
          ))}
        </ul>
      </div>
    </div>

    <div class="archive-content">
      {products.length === 0 ? (
        <p class="no-results">No products found. Try a different search.</p>
      ) : (
        <>
          <div class="products-grid">
            {products.map(product => (
              <ProductCard {...product} />
            ))}
          </div>

          <Pagination {...pagination} />
        </>
      )}
    </div>
  </main>
</Layout>

<style>
  .archive-page {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
  }

  .archive-header {
    grid-column: 1 / -1;
  }

  .products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
  }

  .categories-filter ul {
    list-style: none;
    padding: 0;
  }

  .categories-filter a {
    display: block;
    padding: 0.5rem;
    text-decoration: none;
  }

  .categories-filter a.active {
    font-weight: bold;
    background: #f0f0f0;
  }
</style>
```

## Product Card Component

```astro
---
// resources/src/components/ProductCard.astro
interface Props {
  id: number;
  title: string;
  url: string;
  excerpt: string;
  image: string;
  price: number | null;
  category: string | null;
}

const { title, url, excerpt, image, price, category } = Astro.props;
---

<article class="product-card">
  <a href={url}>
    <div class="product-image">
      <img src={image} alt={title} />
    </div>
    <div class="product-info">
      {category && <span class="product-category">{category}</span>}
      <h3 class="product-title">{title}</h3>
      <p class="product-excerpt">{excerpt}</p>
      {price && <span class="product-price">${price}</span>}
    </div>
  </a>
</article>
```

## Pagination Component

```astro
---
// resources/src/components/Pagination.astro
interface Props {
  pages: number;
  current: number;
  hasNext: boolean;
  hasPrev: boolean;
}

const { pages, current, hasNext, hasPrev } = Astro.props;

const getPageUrl = (page: number) => {
  const params = new URLSearchParams(window.location.search);
  params.set('paged', page.toString());
  return `?${params.toString()}`;
};
---

{pages > 1 && (
  <nav class="pagination">
    {hasPrev && (
      <a href={getPageUrl(current - 1)} class="prev">
        &larr; Previous
      </a>
    )}

    <span class="page-numbers">
      Page {current} of {pages}
    </span>

    {hasNext && (
      <a href={getPageUrl(current + 1)} class="next">
        Next &rarr;
      </a>
    )}
  </nav>
)}
```

## Archive Page ID Approach

Alternatively, you can use a static page ID for the archive:

```php
// In controller
public static string $handle = '99'; // Page ID for /products/

// In WordPress admin
// Create a page with slug "products"
// Set that page ID as the handle
```

Or use the `archive_` prefix convention:

```php
public static string $handle = 'archive_product';
```

## Key Points

1. **Handle Matches Post Type**: `$handle = 'product'` handles all product archives
2. **Model Transformation**: `Product::toArray()` transforms Timber posts to clean arrays
3. **Query Building**: Separate method for building WP_Query arguments
4. **Pagination**: Built-in WordPress pagination via Timber
5. **Filtering**: Tax query for category filtering
6. **Search**: WordPress native search via `s` parameter
7. **URL Parameters**: Clean URL handling with `getUrlParam()`
8. **Type Safety**: All methods are strictly typed