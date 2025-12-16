# WordPress Integration

> Actions, filters, and WordPress hooks in Fern

## Overview

Fern provides clean wrappers around WordPress's hook system through the `Events` (actions) and `Filters` classes. These provide a modern, type-safe interface while maintaining full compatibility with WordPress core.

## Quick Start

```php
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

// Register an action
Events::on('init', function(): void {
    register_post_type('product', [
        'public' => true,
        'label' => 'Products',
    ]);
});

// Register a filter
Filters::on('the_content', function(string $content): string {
    return $content . '<p>Thank you for reading!</p>';
});

// Trigger a custom event
Events::trigger('my_plugin:product_created', $productId);

// Apply a custom filter
$price = Filters::apply('my_plugin:format_price', $rawPrice, $currency);
```

---

## Events (Actions)

The `Events` class wraps WordPress actions with a cleaner API.

### `Events::on(string|array $eventName, callable $callback, int $priority = 10, int $acceptedArgs = -1): void`

Register an event handler.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$eventName` | `string\|array<string>` | Yes | Event name(s) to listen to |
| `$callback` | `callable` | Yes | Handler function |
| `$priority` | `int` | No | Execution priority (default: 10) |
| `$acceptedArgs` | `int` | No | Number of arguments (default: auto-detected) |

**Alias:** `Events::addHandlers()`

**Example:**
```php
// Single event
Events::on('init', function(): void {
    // Runs on WordPress init
});

// Multiple events with same handler
Events::on(['wp_enqueue_scripts', 'admin_enqueue_scripts'], function(): void {
    wp_enqueue_style('my-style', get_template_directory_uri() . '/style.css');
});

// With priority (higher = later)
Events::on('wp_head', function(): void {
    echo '<meta name="author" content="My Site">';
}, 20);

// With explicit argument count
Events::on('save_post', function(int $postId, \WP_Post $post): void {
    if ($post->post_type === 'product') {
        // Update product cache
    }
}, 10, 2);

// Using class methods
Events::on('init', [MyClass::class, 'registerPostTypes']);
Events::on('init', [$this, 'initialize']);
```

### `Events::trigger(string $name, mixed ...$args): void`

Trigger an event with arguments.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$name` | `string` | Yes | Event name to trigger |
| `$args` | `mixed` | No | Arguments to pass to handlers |

**Example:**
```php
// Simple trigger
Events::trigger('my_plugin:initialized');

// With arguments
Events::trigger('my_plugin:order_placed', $orderId, $customerId);

// Custom events for extensibility
Events::trigger('my_plugin:before_render', $template, $data);
$result = $this->render($template, $data);
Events::trigger('my_plugin:after_render', $template, $result);
```

### `Events::renderToString(string $name, array $args = []): string`

Trigger an event and capture output as a string.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$name` | `string` | Yes | Event name to trigger |
| `$args` | `array<mixed>` | No | Arguments to pass |

**Returns:** `string` - Captured output

**Example:**
```php
// Capture wp_head output
$headContent = Events::renderToString('wp_head');

// Capture custom hook output
$widgetHtml = Events::renderToString('my_plugin:render_widget', [$widgetId]);
```

### `Events::removeHandlers(string|array $eventName): void`

Remove all handlers from event(s).

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$eventName` | `string\|array<string>` | Yes | Event name(s) |

**Example:**
```php
// Remove all handlers from single event
Events::removeHandlers('wp_head');

// Remove from multiple events
Events::removeHandlers(['wp_head', 'wp_footer']);
```

---

## Filters

The `Filters` class wraps WordPress filters.

### `Filters::on(string|array $filters, callable $callback, int $priority = 10, int $acceptedArgs = -1): void`

Register a filter handler.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$filters` | `string\|array<string>` | Yes | Filter name(s) |
| `$callback` | `callable` | Yes | Handler function |
| `$priority` | `int` | No | Execution priority (default: 10) |
| `$acceptedArgs` | `int` | No | Number of arguments (default: auto-detected) |

**Alias:** `Filters::add()`

**Example:**
```php
// Modify content
Filters::on('the_content', function(string $content): string {
    if (is_single()) {
        $content .= '<div class="share-buttons">...</div>';
    }
    return $content;
});

// Multiple filters with same handler
Filters::on(['the_title', 'the_excerpt'], function(string $text): string {
    return strip_tags($text);
});

// With additional arguments
Filters::on('the_title', function(string $title, int $postId): string {
    $prefix = get_post_meta($postId, 'title_prefix', true);
    return $prefix ? "$prefix: $title" : $title;
}, 10, 2);

// Using class methods
Filters::on('body_class', [ThemeHelper::class, 'addBodyClasses']);
```

### `Filters::apply(string $filter, mixed $startingValue, mixed ...$args): mixed`

Apply a filter to a value.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$filter` | `string` | Yes | Filter name |
| `$startingValue` | `mixed` | Yes | Initial value |
| `$args` | `mixed` | No | Additional arguments |

**Returns:** `mixed` - Filtered value

**Example:**
```php
// Apply WordPress filter
$content = Filters::apply('the_content', $rawContent);

// Custom filter with arguments
$price = Filters::apply('my_plugin:format_price', $rawPrice, $currency, $locale);

// Allow extension points
$data = [
    'title' => 'My Product',
    'price' => 99.99,
];
$data = Filters::apply('my_plugin:product_data', $data, $productId);
```

### `Filters::removeHandlers(string|array $filterName): void`

Remove all handlers from filter(s).

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$filterName` | `string\|array<string>` | Yes | Filter name(s) |

**Example:**
```php
// Remove all handlers
Filters::removeHandlers('the_content');

// Remove from multiple filters
Filters::removeHandlers(['the_title', 'the_excerpt']);
```

---

## Fern-Specific Hooks

### Core Lifecycle Events

#### `fern:core:before_boot`

Triggered before Fern initializes.

```php
Events::on('fern:core:before_boot', function(): void {
    // Very early initialization
    // Config not yet available
});
```

#### `fern:core:after_boot`

Triggered after Fern initializes.

```php
Events::on('fern:core:after_boot', function(): void {
    // Services are ready
    // Config is available
});
```

#### `fern:core:config:after_boot`

Triggered after configuration is loaded.

```php
Events::on('fern:core:config:after_boot', function(Config $config): void {
    // React to configuration
}, 10, 1);
```

#### `fern:core:reply:has_been_sent`

Triggered after reply is sent, just before exiting.

```php
Events::on('fern:core:reply:has_been_sent', function(Reply $reply): void {
    // Logging, cleanup, analytics
    error_log('Response sent with status: ' . $reply->getCode());
}, 10, 1);
```

### Core Filters

#### `fern:core:config`

Modify configuration before boot.

```php
Filters::on('fern:core:config', function(array $config): array {
    // Environment-specific overrides
    if (getenv('USE_STAGING_API')) {
        $config['api']['url'] = 'https://staging-api.example.com';
    }
    return $config;
});
```

#### `fern:core:ctx`

Modify global application context.

```php
Filters::on('fern:core:ctx', function(array $ctx): array {
    $ctx['site_name'] = get_bloginfo('name');
    $ctx['current_year'] = date('Y');
    $ctx['is_logged_in'] = is_user_logged_in();
    return $ctx;
});
```

### Views Filters

#### `fern:core:views:ctx`

Inject context into all views.

```php
Filters::on('fern:core:views:ctx', function(array $ctx): array {
    $ctx['main_menu'] = wp_get_nav_menu_items('primary');
    return $ctx;
});
```

#### `fern:core:views:data`

Modify view data before rendering.

```php
Filters::on('fern:core:views:data', function(array $data): array {
    $data['rendered_at'] = time();
    return $data;
});
```

#### `fern:core:views:result`

Modify rendered HTML.

```php
Filters::on('fern:core:views:result', function(string $html): string {
    // Minification, caching headers, etc.
    return $html;
});
```

### Router Filters

#### `fern:core:router:resolve_id`

Modify resolved page/post ID.

```php
// Polylang integration
Filters::on('fern:core:router:resolve_id', function(int $id, Request $req): ?int {
    if (function_exists('pll_get_post')) {
        return pll_get_post($id) ?: $id;
    }
    return $id;
}, 10, 2);
```

#### `fern:core:router:get_archive_page_id`

Modify archive page ID.

```php
Filters::on('fern:core:router:get_archive_page_id', function(int $id, ?string $type): int {
    if ($type === 'product') {
        return get_option('woocommerce_shop_page_id');
    }
    return $id;
}, 10, 2);
```

#### `fern:core:controller_resolve`

Modify controller handle before resolution.

```php
Filters::on('fern:core:controller_resolve', function(string $handle, string $type): string {
    // Custom routing logic
    return $handle;
}, 10, 2);
```

### Action Filters

#### `fern:core:action:can_run`

Control whether an action can execute.

```php
Filters::on('fern:core:action:can_run', function(bool $canRun, Action $action, $controller): bool {
    // Rate limiting
    $actionName = $action->getName();
    $userId = get_current_user_id();

    if ($this->isRateLimited($actionName, $userId)) {
        return false;
    }

    return $canRun;
}, 10, 3);
```

### Reply Filters

#### `fern:core:reply:headers`

Modify reply headers.

```php
Filters::on('fern:core:reply:headers', function(Reply $reply): void {
    $reply->setHeader('X-Powered-By', 'Fern');
    $reply->setHeader('X-Frame-Options', 'SAMEORIGIN');
});
```

#### `fern:core:reply:will_be_send`

Modify body before sending.

```php
Filters::on('fern:core:reply:will_be_send', function($body, Reply $reply) {
    // Log response
    if (Fern::isDev()) {
        error_log('Sending: ' . substr(print_r($body, true), 0, 500));
    }
    return $body;
}, 10, 2);
```

### File Filters

#### `fern:core:file:disallowed_upload_extensions`

Modify blocked file extensions.

```php
Filters::on('fern:core:file:disallowed_upload_extensions', function(array $extensions): array {
    $extensions[] = 'svg'; // Block SVG uploads
    return $extensions;
});
```

#### `fern:core:file:allowed_mime_types`

Modify allowed MIME types.

```php
Filters::on('fern:core:file:allowed_mime_types', function(array $types): array {
    $types[] = 'image/webp';
    $types[] = 'image/avif';
    return $types;
});
```

### HTTP Request Filters

#### `fern:core:http:request:queried_object_id`

Modify the queried object ID.

```php
Filters::on('fern:core:http:request:queried_object_id', function(int $id): int {
    // Custom ID resolution
    return $id;
});
```

---

## Common Patterns

### Registering Post Types

```php
// src/App/_postTypes.php
use Fern\Core\Wordpress\Events;

Events::on('init', function(): void {
    register_post_type('product', [
        'labels' => [
            'name' => 'Products',
            'singular_name' => 'Product',
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'thumbnail'],
        'rewrite' => ['slug' => 'products'],
    ]);

    register_post_type('testimonial', [
        'labels' => [
            'name' => 'Testimonials',
            'singular_name' => 'Testimonial',
        ],
        'public' => true,
        'supports' => ['title', 'editor'],
    ]);
});
```

### Registering Taxonomies

```php
// src/App/_taxonomies.php
use Fern\Core\Wordpress\Events;

Events::on('init', function(): void {
    register_taxonomy('product_category', 'product', [
        'labels' => [
            'name' => 'Product Categories',
            'singular_name' => 'Category',
        ],
        'hierarchical' => true,
        'rewrite' => ['slug' => 'product-category'],
    ]);
});
```

### Enqueueing Scripts and Styles

```php
use Fern\Core\Wordpress\Events;

Events::on('wp_enqueue_scripts', function(): void {
    // Styles
    wp_enqueue_style(
        'theme-style',
        get_template_directory_uri() . '/dist/css/main.css',
        [],
        filemtime(get_template_directory() . '/dist/css/main.css')
    );

    // Scripts
    wp_enqueue_script(
        'theme-script',
        get_template_directory_uri() . '/dist/js/main.js',
        [],
        filemtime(get_template_directory() . '/dist/js/main.js'),
        true
    );
});
```

### Admin Customizations

```php
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

// Add admin menu item
Events::on('admin_menu', function(): void {
    add_menu_page(
        'My Settings',
        'My Settings',
        'manage_options',
        'my-settings',
        [SettingsPage::class, 'render'],
        'dashicons-admin-settings',
        99
    );
});

// Add meta boxes
Events::on('add_meta_boxes', function(): void {
    add_meta_box(
        'product_details',
        'Product Details',
        [ProductMetaBox::class, 'render'],
        'product',
        'normal',
        'high'
    );
});

// Modify admin columns
Filters::on('manage_product_posts_columns', function(array $columns): array {
    $columns['price'] = 'Price';
    $columns['stock'] = 'Stock';
    return $columns;
});
```

### REST API Integration

```php
use Fern\Core\Wordpress\Events;

Events::on('rest_api_init', function(): void {
    register_rest_route('my-plugin/v1', '/products', [
        'methods' => 'GET',
        'callback' => [ProductAPI::class, 'getProducts'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('my-plugin/v1', '/products/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => [ProductAPI::class, 'getProduct'],
        'permission_callback' => '__return_true',
    ]);
});
```

### WooCommerce Integration

```php
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

// Modify cart
Filters::on('woocommerce_add_cart_item_data', function(array $cartData, int $productId): array {
    $cartData['custom_data'] = 'value';
    return $cartData;
}, 10, 2);

// After order placed
Events::on('woocommerce_thankyou', function(int $orderId): void {
    // Send notification, update CRM, etc.
}, 10, 1);

// Modify checkout fields
Filters::on('woocommerce_checkout_fields', function(array $fields): array {
    unset($fields['billing']['billing_company']);
    return $fields;
});
```

---

## Hook Execution Order

Understanding when hooks fire:

```
1. mu-plugins loaded
2. plugins loaded
3. theme functions.php (requires fern-config.php)
4. fern:core:before_boot
5. Config loaded
6. fern:core:config filter
7. fern:core:config:after_boot
8. Services boot
9. fern:core:after_boot
10. App::boot()
11. init
12. wp_loaded
13. template_redirect (frontend only)
14. Router resolves
15. fern:core:router:resolve_id filter
16. fern:core:controller_resolve filter
17. Controller->handle()
18. fern:core:views:ctx filter
19. fern:core:views:data filter
20. Template rendered
21. fern:core:views:result filter
22. fern:core:reply:headers filter
23. fern:core:reply:will_be_send filter
24. Response sent
25. fern:core:reply:has_been_sent event
```

---

## Best Practices

### 1. Use Descriptive Hook Names

```php
// Good: Namespaced and descriptive
Events::trigger('my_plugin:order:created', $order);
Events::trigger('my_plugin:user:registered', $user);

// Avoid: Generic names
Events::trigger('order_created', $order);
```

### 2. Always Return Values in Filters

```php
// Good: Always return
Filters::on('the_content', function(string $content): string {
    if (!is_single()) {
        return $content; // Return unmodified
    }
    return $content . '<div>...</div>';
});

// Bad: Missing return
Filters::on('the_content', function(string $content): string {
    if (is_single()) {
        return $content . '<div>...</div>';
    }
    // Missing return for non-single pages!
});
```

### 3. Use Appropriate Priorities

```php
// Early execution (low priority number)
Events::on('init', function(): void {
    // Register post types first
}, 5);

// Late execution (high priority number)
Events::on('init', function(): void {
    // Modify registered post types
}, 20);

// Default is 10
Events::on('init', function(): void {
    // Normal priority
});
```

### 4. Keep Callbacks Small

```php
// Good: Delegate to methods
Events::on('save_post', [ProductHandler::class, 'onSave']);

class ProductHandler {
    public static function onSave(int $postId, \WP_Post $post): void {
        if ($post->post_type !== 'product') {
            return;
        }

        self::updateCache($postId);
        self::syncInventory($postId);
    }
}

// Avoid: Large inline callbacks
Events::on('save_post', function(int $postId, \WP_Post $post): void {
    // 100 lines of code...
});
```

---

## See Also

- [Core Classes](./core.md) - Fern, Config, Context
- [Controllers](./controllers.md) - Route handling
- [Views](./views.md) - Template rendering
