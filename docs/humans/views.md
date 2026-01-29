# Views & Rendering

> Template rendering and frontend integration

## Overview

The Views system provides template rendering through pluggable rendering engines. It handles data preparation, context merging, and template execution, bridging PHP controllers with frontend templates (typically Astro).

## Quick Start

```php
use Fern\Core\Services\Views\Views;
use Fern\Core\Services\HTTP\Reply;

class ProductController extends ViewController implements Controller {
    public static string $handle = 'product';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Product', [
            'title' => 'My Product',
            'price' => 99.99,
            'description' => 'Product description',
        ]));
    }
}
```

---

## Views Class

The `Views` class is the primary interface for rendering templates.

### `Views::render(string $template, array $data = [], bool $doingBlock = false): string`

Renders a template with the provided data.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$template` | `string` | Yes | Template name (without extension) |
| `$data` | `array<string, mixed>` | No | Data to pass to template |
| `$doingBlock` | `bool` | No | Whether rendering a Gutenberg block |

**Returns:** `string` - The rendered HTML

**Throws:** `InvalidArgumentException` if data is not an array

**Example:**
```php
// Basic rendering
$html = Views::render('HomePage', [
    'title' => 'Welcome',
    'posts' => $posts,
]);

// With nested data
$html = Views::render('Product', [
    'product' => [
        'title' => 'Widget',
        'price' => 29.99,
        'stock' => 100,
    ],
    'related' => $relatedProducts,
]);

// In a controller
return new Reply(200, Views::render('Archive', [
    'posts' => Timber::get_posts(),
    'pagination' => Timber::get_pagination(),
]));
```

### Template Resolution

Templates are resolved based on the rendering engine configuration. With the typical Astro engine:

| Template Name | File Path |
|---------------|-----------|
| `'HomePage'` | `resources/src/pages/HomePage.astro` |
| `'Product'` | `resources/src/pages/Product.astro` |
| `'Admin/Settings'` | `resources/src/pages/Admin/Settings.astro` |

### Context Merging

Views automatically merges the global application context into every render:

```php
// Global context (set in App::boot())
Context::set([
    'site_name' => 'My Site',
    'current_user' => wp_get_current_user(),
]);

// In controller
Views::render('Page', [
    'title' => 'My Page',
    // ctx is automatically merged
]);

// In template, 'ctx' contains:
// {
//   site_name: 'My Site',
//   current_user: {...},
// }
```

---

## RenderingEngine Interface

Create custom rendering engines by implementing this interface:

```php
interface RenderingEngine {
    public function render(string $template, array $data = []): string;
    public function renderBlock(string $block, array $data = []): string;
    public function boot(): void;
}
```

### Methods

| Method | Description |
|--------|-------------|
| `render(string $template, array $data): string` | Render a full page template |
| `renderBlock(string $block, array $data): string` | Render a Gutenberg block |
| `boot(): void` | Initialize the engine |

### Example: Custom Engine

```php
<?php
namespace App\Services\Rendering;

use Fern\Core\Services\Views\RenderingEngine;

class MyEngine implements RenderingEngine {
    private string $templateDir;

    public function __construct(array $options = []) {
        $this->templateDir = $options['template_dir'] ?? '/templates';
    }

    public function boot(): void {
        // Initialize template engine
        // Load helpers, configure caching, etc.
    }

    public function render(string $template, array $data = []): string {
        $path = $this->templateDir . '/' . $template . '.php';

        if (!file_exists($path)) {
            throw new \RuntimeException("Template not found: $template");
        }

        extract($data);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    public function renderBlock(string $block, array $data = []): string {
        return $this->render('blocks/' . $block, $data);
    }
}
```

### Registering a Custom Engine

```php
// In fern-config.php
Fern::defineConfig([
    'root' => __DIR__,
    'rendering_engine' => new \App\Services\Rendering\MyEngine([
        'template_dir' => __DIR__ . '/templates',
    ]),
]);
```

---

## Built-in Engines

### RemoteEngine

Communicates with an external rendering service (like Astro dev server).

```php
use Fern\Core\Services\Views\Engines\RemoteEngine;

'rendering_engine' => new RemoteEngine([
    'url' => 'http://bun:3000',
    'timeout' => 2.5,
]),
```

**Options:**
| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `url` | `string` | Required | Rendering service URL |
| `timeout` | `float` | `2.0` | Request timeout in seconds |

### VanillaEngine

Traditional PHP templates.

```php
use Fern\Core\Services\Views\Engines\VanillaEngine;

'rendering_engine' => new VanillaEngine([
    'template_dir' => __DIR__ . '/templates',
]),
```

---

## View Filters

Customize view rendering using these filters:

### `fern:core:views:ctx`

Inject global context into all views.

```php
use Fern\Core\Wordpress\Filters;

Filters::on('fern:core:views:ctx', function(array $ctx): array {
    // Add navigation
    $ctx['main_menu'] = wp_get_nav_menu_items('primary');

    // Add global settings
    $ctx['settings'] = [
        'analytics_id' => get_option('analytics_id'),
        'theme' => get_option('site_theme', 'light'),
    ];

    // Add translations
    $ctx['translations'] = [
        'add_to_cart' => __('Add to Cart', 'theme'),
        'checkout' => __('Checkout', 'theme'),
    ];

    return $ctx;
});
```

### `fern:core:views:data`

Modify view data before rendering.

```php
Filters::on('fern:core:views:data', function(array $data): array {
    // Add timestamp to all views
    $data['rendered_at'] = time();

    // Add debug info in development
    if (Fern::isDev()) {
        $data['debug'] = [
            'memory' => memory_get_usage(true),
            'queries' => get_num_queries(),
        ];
    }

    return $data;
});
```

### `fern:core:views:result`

Modify rendered HTML before sending.

```php
Filters::on('fern:core:views:result', function(string $html): string {
    // Minify in production
    if (Fern::isNotDev()) {
        $html = preg_replace('/\s+/', ' ', $html);
    }

    // Add cache headers
    // Note: Better to use Reply headers for this

    return $html;
});
```

---

## Frontend Templates (Astro)

### Template Structure

```astro
---
// resources/src/pages/Product.astro
import Layout from '../layouts/Layout.astro';
import ProductGallery from '../components/ProductGallery.astro';
import AddToCart from '../components/AddToCart';

interface Props {
    title: string;
    price: number;
    description: string;
    images: string[];
    ctx: {
        site_name: string;
        current_user: {
            ID: number;
            display_name: string;
        };
    };
    nonces: {
        add_to_cart: string;
    };
}

const { title, price, description, images, ctx, nonces } = Astro.props;
---

<Layout title={title}>
    <main>
        <h1>{title}</h1>

        <ProductGallery images={images} />

        <div class="product-info">
            <p class="price">${price}</p>
            <div set:html={description} />

            <AddToCart
                client:load
                nonce={nonces.add_to_cart}
            />
        </div>
    </main>
</Layout>
```

### Accessing Context

```astro
---
const { ctx } = Astro.props;
---

<header>
    <h1>{ctx.site_name}</h1>
    {ctx.current_user && (
        <span>Welcome, {ctx.current_user.display_name}!</span>
    )}
</header>
```

### Using Nonces

```astro
---
const { nonces } = Astro.props;
---

<script define:vars={{ nonce: nonces.form_action }}>
    import { callAction } from '@ferndev/core';

    async function submitForm(data) {
        const { data: result, error } = await callAction('submitForm', data, nonce);
        // ...
    }
</script>
```

### Client-Side Components (SolidJS)

```tsx
// resources/src/components/AddToCart.tsx
import { createSignal } from 'solid-js';
import { callAction } from '@ferndev/core';

interface Props {
    productId: number;
    nonce: string;
}

export default function AddToCart(props: Props) {
    const [loading, setLoading] = createSignal(false);
    const [quantity, setQuantity] = createSignal(1);

    const handleAdd = async () => {
        setLoading(true);
        const { data, error } = await callAction('addToCart', {
            product_id: props.productId,
            quantity: quantity(),
        }, props.nonce);
        setLoading(false);

        if (error) {
            alert(error.message);
        }
    };

    return (
        <div class="add-to-cart">
            <input
                type="number"
                value={quantity()}
                onInput={(e) => setQuantity(parseInt(e.currentTarget.value))}
                min="1"
            />
            <button onClick={handleAdd} disabled={loading()}>
                {loading() ? 'Adding...' : 'Add to Cart'}
            </button>
        </div>
    );
}
```

---

## Common Patterns

### Passing Nonces

```php
// Controller
public function handle(Request $request): Reply {
    return new Reply(200, Views::render('ContactPage', [
        'title' => 'Contact Us',
        'nonces' => [
            'contact_form' => wp_create_nonce('contact_form'),
            'newsletter' => wp_create_nonce('newsletter'),
        ],
    ]));
}
```

### Passing User Data

```php
public function handle(Request $request): Reply {
    $user = wp_get_current_user();

    return new Reply(200, Views::render('Dashboard', [
        'user' => [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
        ],
        'is_admin' => current_user_can('manage_options'),
    ]));
}
```

### Passing Timber Posts

```php
public function handle(Request $request): Reply {
    $posts = Timber::get_posts([
        'post_type' => 'post',
        'posts_per_page' => 10,
    ]);

    // Transform for frontend
    $formattedPosts = array_map(fn($post) => [
        'id' => $post->ID,
        'title' => $post->title(),
        'excerpt' => $post->preview(20),
        'url' => $post->link(),
        'thumbnail' => $post->thumbnail()?->src() ?? '',
        'date' => $post->date('F j, Y'),
    ], $posts);

    return new Reply(200, Views::render('Blog', [
        'posts' => $formattedPosts,
        'pagination' => Timber::get_pagination(),
    ]));
}
```

### Conditional Content

```php
public function handle(Request $request): Reply {
    $user = wp_get_current_user();

    $data = [
        'title' => 'My Account',
        'is_logged_in' => is_user_logged_in(),
    ];

    if (is_user_logged_in()) {
        $data['user'] = [
            'name' => $user->display_name,
            'orders' => $this->getUserOrders($user->ID),
        ];
        $data['nonces'] = [
            'update_profile' => wp_create_nonce('update_profile'),
        ];
    }

    return new Reply(200, Views::render('MyAccount', $data));
}
```

---

## Best Practices

### 1. Keep Data Serializable

Only pass data that can be JSON-encoded:

```php
// Good
Views::render('Page', [
    'user_name' => $user->display_name,
    'user_id' => $user->ID,
]);

// Avoid
Views::render('Page', [
    'user' => $user, // WP_User object may have non-serializable properties
]);
```

### 2. Transform Data in Controllers

```php
// Good: Transform in controller
$product = [
    'title' => $post->title(),
    'price' => (float) get_field('price'),
    'image' => get_the_post_thumbnail_url($post->ID, 'large'),
];

Views::render('Product', ['product' => $product]);

// Avoid: Passing raw WordPress objects
Views::render('Product', ['post' => $post]);
```

### 3. Use Context for Global Data

```php
// In App::boot() or via filter
Filters::on('fern:core:views:ctx', function(array $ctx): array {
    $ctx['site_logo'] = get_theme_mod('site_logo');
    $ctx['social_links'] = get_option('social_links', []);
    return $ctx;
});

// Then in any template
// ctx.site_logo is available
```

### 4. Create Reusable View Helpers

```php
// src/App/Services/ViewHelpers.php
class ViewHelpers {
    public static function formatPrice(float $price): string {
        return number_format($price, 2, '.', ',');
    }

    public static function getMenuItems(string $location): array {
        $locations = get_nav_menu_locations();
        if (!isset($locations[$location])) {
            return [];
        }

        $items = wp_get_nav_menu_items($locations[$location]);
        return array_map(fn($item) => [
            'title' => $item->title,
            'url' => $item->url,
            'target' => $item->target,
        ], $items ?: []);
    }
}
```

---

## Troubleshooting

### Template Not Found

**Problem:** `RuntimeException: Template not found`

**Solutions:**
1. Check template name matches file name (case-sensitive)
2. Verify file exists in correct directory
3. Ensure rendering engine is properly configured

### Context Not Available

**Problem:** `ctx` is undefined in template

**Solutions:**
1. Check Context is set in App::boot()
2. Verify filter is returning an array
3. Ensure Views::render() is called (not direct engine render)

### Data Not Rendering

**Problem:** Passed data not appearing in template

**Solutions:**
1. Check prop types match in Astro interface
2. Verify data is JSON-serializable
3. Check for typos in property names

---

## See Also

- [Controllers](./controllers.md) - How controllers use Views
- [Frontend Integration](./frontend.md) - @ferndev/core library
- [Core Classes](./core.md) - Context management
