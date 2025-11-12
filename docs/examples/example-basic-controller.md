# Example: Basic Controller

This example demonstrates a simple page controller that fetches a WordPress page and renders it with custom data.

## Use Case

You have a "Products" page (ID: 42) and want to display it with a list of featured products from ACF fields.

## File Structure

```
src/App/Controllers/ProductsPageController.php
resources/src/pages/ProductsPage.astro
```

## Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class ProductsPageController extends ViewController implements Controller {
    public static string $handle = '42'; // let's imagine you set 'products' slug in wp admin.

    /**
     * Handle the products page request
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $post = Timber::get_post();
        $fields = get_fields();

        return new Reply(200, Views::render('ProductsPage', [
            'title' => $post->title(),
            'content' => $post->content(),
            'products' => $this->getFeaturedProducts($fields),
        ]));
    }

    /**
     * Get featured products from ACF fields
     *
     * @param array<string, mixed>|false $fields ACF fields
     *
     * @return array<int, array<string, mixed>> Featured products data
     */
    private function getFeaturedProducts(array|false $fields): array {
        if (!is_array($fields) || !isset($fields['featured_products'])) {
            return [];
        }

        $products = [];

        foreach ($fields['featured_products'] as $product) {
            $post = Timber::get_post($product->ID);

            if (!$post) {
                continue;
            }

            $thumbnail = $post->thumbnail();

            $products[] = [
                'title' => $post->title(),
                'url' => $post->link(),
                'image' => $thumbnail ? $thumbnail->src() : '',
                'excerpt' => $post->preview(20),
            ];
        }

        return $products;
    }
}
```

## Frontend Template (Astro)

```astro
---
// resources/src/pages/ProductsPage.astro
interface Props {
  title: string;
  content: string;
  products: Array<{
    title: string;
    url: string;
    image: string;
    excerpt: string;
  }>;
}

const { title, content, products } = Astro.props;
---

<Layout title={title}>
  <main>
    <h1>{title}</h1>
    <div set:html={content} />

    <section class="featured-products">
      <h2>Featured Products</h2>
      <div class="products-grid">
        {products.map(product => (
          <article class="product-card">
            <img src={product.image} alt={product.title} />
            <h3>{product.title}</h3>
            <p>{product.excerpt}</p>
            <a href={product.url}>Learn More</a>
          </article>
        ))}
      </div>
    </section>
  </main>
</Layout>
```

## Registration

Controllers with a `$handle` property are automatically registered. The router will match page ID `42` to this controller.

## Key Points

1. **Type Safety**: All parameters and return types are strictly typed
2. **Timber Integration**: Uses `Timber::get_post()` for efficient WordPress queries
3. **ACF Fields**: Accesses custom fields via `get_fields()`
4. **View Rendering**: Uses `Views::render()` to pass data to frontend
5. **Single Responsibility**: `getFeaturedProducts()` is a separate, focused method
6. **PHPDoc**: All public methods have comprehensive documentation
7. **Error Handling**: Gracefully handles missing fields and posts

## Testing

Visit `/products` (where page ID 42 is accessible) to see the rendered page.

## Variations

### Handle by Post Type

Instead of a specific page ID, handle all posts of a type:

```php
public static string $handle = 'product'; // Post type slug
```

### Handle Multiple Pages

Use a trait for shared logic across multiple page controllers:

```php
trait ProductDataTrait {
    private function getFeaturedProducts(array|false $fields): array {
        // Shared implementation
    }
}

class ProductsPageController extends ViewController implements Controller {
    use ProductDataTrait;

    public static string $handle = '42';
    // ...
}

class ProductArchiveController extends ViewController implements Controller {
    use ProductDataTrait;

    public static string $handle = 'product';
    // ...
}
```
