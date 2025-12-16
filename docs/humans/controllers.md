# Controllers

> Route handlers that process requests and return responses

## Overview

Controllers are the entry point for handling HTTP requests in Fern. They receive requests, process data, and return responses. Controllers can handle pages, post types, taxonomies, archives, and admin pages.

## Quick Start

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

class ProductController extends ViewController implements Controller {
    public static string $handle = 'product'; // Post type slug

    public function handle(Request $request): Reply {
        $post = Timber::get_post();

        return new Reply(200, Views::render('Product', [
            'title' => $post->title(),
            'content' => $post->content(),
            'price' => get_field('price'),
        ]));
    }
}
```

---

## Controller Interface

All controllers must implement the `Controller` interface:

```php
interface Controller {
    public function handle(Request $request): Reply;
    public static function getInstance(array ...$args): static;
}
```

| Method | Description |
|--------|-------------|
| `handle(Request $request): Reply` | Process the request and return a response |
| `getInstance(): static` | Return the singleton instance (inherited from ViewController) |

---

## ViewController Pattern

Most controllers extend `ViewController`, which provides singleton functionality and integrates with the Fern router.

### Basic Structure

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class MyController extends ViewController implements Controller {
    public static string $handle = 'page_id_or_post_type';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('MyPage', [
            'data' => $this->getData(),
        ]));
    }

    private function getData(): array {
        return ['key' => 'value'];
    }
}
```

### Handle Options

The `$handle` property determines which requests this controller handles:

| Handle Type | Example | Description |
|-------------|---------|-------------|
| Page ID | `'42'` | Specific WordPress page by ID |
| Post Type | `'product'` | All posts of a custom post type |
| Post Type | `'post'` | All blog posts |
| Taxonomy | `'product_cat'` | Taxonomy term pages |
| Archive | `'archive_product'` | Post type archive page |
| Default | `'_default'` | Fallback for unmatched requests |
| 404 | `'_404'` | 404 error page handler |

### Page ID Controller

Handle a specific WordPress page:

```php
class AboutController extends ViewController implements Controller {
    public static string $handle = '15'; // Page ID

    public function handle(Request $request): Reply {
        $post = Timber::get_post();
        $team = get_field('team_members');

        return new Reply(200, Views::render('About', [
            'title' => $post->title(),
            'content' => $post->content(),
            'team' => $team,
        ]));
    }
}
```

### Post Type Controller

Handle all posts of a type:

```php
class ProductController extends ViewController implements Controller {
    public static string $handle = 'product';

    public function handle(Request $request): Reply {
        $product = Timber::get_post();
        $related = Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => 4,
            'post__not_in' => [$product->ID],
        ]);

        return new Reply(200, Views::render('Product', [
            'product' => $product,
            'related' => $related,
        ]));
    }
}
```

### Archive Controller

Handle post type archives:

```php
class ProductArchiveController extends ViewController implements Controller {
    public static string $handle = 'archive_product';

    public function handle(Request $request): Reply {
        $paged = $request->getUrlParam('paged', 1);

        $products = Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => 12,
            'paged' => $paged,
        ]);

        return new Reply(200, Views::render('ProductArchive', [
            'products' => $products,
            'pagination' => Timber::get_pagination(),
        ]));
    }
}
```

### Taxonomy Controller

Handle taxonomy term pages:

```php
class ProductCategoryController extends ViewController implements Controller {
    public static string $handle = 'product_cat';

    public function handle(Request $request): Reply {
        $term = Timber::get_term();
        $products = Timber::get_posts([
            'post_type' => 'product',
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'id',
                'terms' => $term->ID,
            ]],
        ]);

        return new Reply(200, Views::render('ProductCategory', [
            'category' => $term,
            'products' => $products,
        ]));
    }
}
```

### Default Controller

Fallback for unmatched requests:

```php
class DefaultController extends ViewController implements Controller {
    public static string $handle = '_default';

    public function handle(Request $request): Reply {
        $post = Timber::get_post();

        return new Reply(200, Views::render('Default', [
            'post' => $post,
        ]));
    }
}
```

### 404 Controller

Handle 404 errors:

```php
class NotFoundController extends ViewController implements Controller {
    public static string $handle = '_404';

    public function handle(Request $request): Reply {
        return new Reply(404, Views::render('NotFound', [
            'search_url' => home_url('/search'),
        ]));
    }
}
```

---

## Actions

Actions are controller methods that can be called from the frontend using `callAction()`. Any public, non-static method is automatically available as an action.

### Defining Actions

```php
class ContactController extends ViewController implements Controller {
    public static string $handle = '12';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Contact', [
            'nonces' => [
                'contact_form' => wp_create_nonce('contact_form'),
            ],
        ]));
    }

    // This is an action - callable from frontend
    #[Nonce('contact_form')]
    public function submitContact(Request $request): Reply {
        $action = $request->getAction();
        $email = sanitize_email($action->get('email'));
        $message = sanitize_textarea_field($action->get('message'));

        // Process form...

        return new Reply(200, ['success' => true]);
    }

    // Private methods are NOT actions
    private function sendEmail(string $to, string $message): bool {
        return wp_mail($to, 'Contact Form', $message);
    }

    // Static methods are NOT actions
    public static function getFormFields(): array {
        return ['email', 'message'];
    }
}
```

### Action Visibility Rules

| Visibility | Is Action? | Description |
|------------|------------|-------------|
| `public` | Yes | Callable from frontend |
| `protected` | No | Not exposed |
| `private` | No | Not exposed |
| `public static` | No | Static methods not exposed |

### Calling Actions from Frontend

```typescript
import { callAction } from '@ferndev/core';

const { data, error } = await callAction('submitContact', {
    email: 'user@example.com',
    message: 'Hello!'
}, nonce);

if (error) {
    console.error(error.message);
} else {
    console.log('Success:', data);
}
```

### Action Traits

Share actions across multiple controllers using traits:

```php
// src/App/Actions/FormActions.php
trait FormActions {
    #[Nonce('newsletter')]
    public function subscribeNewsletter(Request $request): Reply {
        $action = $request->getAction();
        $email = sanitize_email($action->get('email'));

        // Subscribe logic...

        return new Reply(200, ['success' => true]);
    }
}

// Use in controllers
class HomeController extends ViewController implements Controller {
    use FormActions;

    public static string $handle = '2';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Home', [
            'nonces' => [
                'newsletter' => wp_create_nonce('newsletter'),
            ],
        ]));
    }
}

class BlogController extends ViewController implements Controller {
    use FormActions;

    public static string $handle = 'post';
    // ...
}
```

---

## Admin Controllers

Create WordPress admin pages using the `AdminController` trait.

### Basic Admin Controller

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class SettingsController extends ViewController implements Controller {
    use AdminController;

    public static string $handle = 'my-settings';

    public function configure(): array {
        return [
            'page_title' => 'My Plugin Settings',
            'menu_title' => 'Settings',
            'capability' => 'manage_options',
            'menu_slug' => 'my-settings',
            'icon_url' => 'dashicons-admin-settings',
            'position' => 99,
        ];
    }

    public function handle(Request $request): Reply {
        $options = get_option('my_plugin_settings', []);

        return new Reply(200, Views::render('AdminSettings', [
            'options' => $options,
            'nonces' => [
                'save_settings' => wp_create_nonce('save_settings'),
            ],
        ]));
    }

    #[Nonce('save_settings')]
    #[RequireCapabilities(['manage_options'])]
    public function saveSettings(Request $request): Reply {
        $action = $request->getAction();
        $settings = $action->get('settings', []);

        update_option('my_plugin_settings', $settings);

        return new Reply(200, ['success' => true]);
    }
}
```

### Configure Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `page_title` | `string` | Yes | Browser page title |
| `menu_title` | `string` | Yes | Menu item label |
| `capability` | `string` | Yes | Required WordPress capability |
| `menu_slug` | `string` | Yes | URL slug (must match `$handle`) |
| `icon_url` | `string` | No | Dashicon class or image URL |
| `position` | `int` | No | Menu position (higher = lower) |
| `parent_slug` | `string` | No | Parent menu slug for submenu |

### Submenu Pages

Create a submenu under an existing menu:

```php
class AdvancedSettingsController extends ViewController implements Controller {
    use AdminController;

    public static string $handle = 'my-advanced-settings';

    public function configure(): array {
        return [
            'page_title' => 'Advanced Settings',
            'menu_title' => 'Advanced',
            'capability' => 'manage_options',
            'menu_slug' => 'my-advanced-settings',
            'parent_slug' => 'my-settings', // Parent menu slug
        ];
    }

    public function handle(Request $request): Reply {
        // ...
    }
}
```

---

## Security Attributes

Fern uses PHP 8+ attributes for declarative security on actions.

### #[Nonce]

Validates WordPress nonces automatically.

```php
use Fern\Core\Services\Actions\Attributes\Nonce;

#[Nonce('form_action')]
public function submitForm(Request $request): Reply {
    // Nonce validated before this executes
    // Returns 403 if nonce is invalid
}
```

**Generate nonce in controller:**
```php
'nonces' => [
    'form_action' => wp_create_nonce('form_action'),
]
```

**Send nonce from frontend:**
```typescript
await callAction('submitForm', args, nonce);
```

### #[RequireCapabilities]

Ensures users have required WordPress capabilities.

```php
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;

// Single capability
#[RequireCapabilities(['manage_options'])]
public function adminAction(Request $request): Reply {
    // Only users with 'manage_options' can access
}

// Multiple capabilities (ALL required)
#[RequireCapabilities(['edit_posts', 'publish_posts'])]
public function publishAction(Request $request): Reply {
    // Requires BOTH capabilities
}
```

**Common WordPress Capabilities:**
| Capability | Description |
|------------|-------------|
| `read` | Basic read access |
| `edit_posts` | Edit own posts |
| `publish_posts` | Publish posts |
| `edit_others_posts` | Edit others' posts |
| `delete_posts` | Delete own posts |
| `manage_options` | Admin settings access |
| `upload_files` | Media uploads |
| `edit_users` | Edit user profiles |
| `manage_categories` | Manage taxonomies |

### #[CacheReply]

Caches action responses for improved performance.

```php
use Fern\Core\Services\Actions\Attributes\CacheReply;

// Cache for 1 hour
#[CacheReply(ttl: 3600)]
public function getStats(Request $request): Reply {
    // Expensive operation cached
    return new Reply(200, $this->calculateStats());
}

// Custom cache key
#[CacheReply(ttl: 1800, key: 'product_stats')]
public function getProductStats(Request $request): Reply {
    return new Reply(200, $this->getStats());
}

// Vary cache by parameters
#[CacheReply(ttl: 3600, varyBy: ['user_id', 'category'])]
public function getUserCategoryData(Request $request): Reply {
    $action = $request->getAction();
    $userId = $action->get('user_id');
    $category = $action->get('category');

    // Each user_id + category combination is cached separately
    return new Reply(200, $this->getData($userId, $category));
}
```

**Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `ttl` | `int` | `3600` | Time to live in seconds |
| `key` | `string\|null` | `null` | Custom cache key (auto-generated if null) |
| `varyBy` | `array<string>` | `[]` | Parameters to vary cache by |

### Combining Attributes

Stack multiple attributes for comprehensive security:

```php
#[Nonce('save_settings')]
#[RequireCapabilities(['manage_options'])]
#[CacheReply(ttl: 600)]
public function saveAndGetSettings(Request $request): Reply {
    // 1. Nonce validated
    // 2. Capabilities checked
    // 3. Response cached for 10 minutes
}
```

**Execution Order:**
1. Nonce validation
2. Capability check
3. Cache lookup/store

---

## Controller Organization

### Directory Structure

```
src/App/Controllers/
├── HomeController.php
├── ProductController.php
├── ProductArchiveController.php
├── NotFoundController.php
├── DefaultController.php
├── MyAccount/
│   ├── DashboardController.php
│   ├── OrdersController.php
│   └── SettingsController.php
└── Admin/
    ├── SettingsController.php
    └── ReportsController.php
```

### Actions Directory

```
src/App/Actions/
├── FormActions.php
├── CartActions.php
└── AuthenticationActions.php
```

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Controller | PascalCase + "Controller" | `ProductController` |
| Action trait | PascalCase + "Actions" | `FormActions` |
| Handle (page) | Numeric string | `'42'` |
| Handle (post type) | Snake_case | `'product'` |
| Handle (archive) | `archive_` prefix | `'archive_product'` |

---

## Best Practices

### 1. Keep Controllers Focused

```php
// Good: Single responsibility
class ProductController extends ViewController implements Controller {
    public static string $handle = 'product';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Product', [
            'product' => $this->getProduct($request),
        ]));
    }

    private function getProduct(Request $request): array {
        // Product-specific logic
    }
}

// Bad: Too many responsibilities
class EverythingController extends ViewController implements Controller {
    public function handle(Request $request): Reply {
        // Handles products, users, orders, etc.
    }
}
```

### 2. Use Traits for Shared Actions

```php
trait CartActions {
    #[Nonce('cart')]
    public function addToCart(Request $request): Reply { }

    #[Nonce('cart')]
    public function removeFromCart(Request $request): Reply { }

    #[Nonce('cart')]
    public function updateQuantity(Request $request): Reply { }
}
```

### 3. Always Validate and Sanitize Input

```php
public function processForm(Request $request): Reply {
    $action = $request->getAction();

    // Sanitize all input
    $email = sanitize_email($action->get('email', ''));
    $name = sanitize_text_field($action->get('name', ''));
    $content = wp_kses_post($action->get('content', ''));

    // Validate
    if (!is_email($email)) {
        return new Reply(400, ['error' => 'Invalid email']);
    }

    // Process...
}
```

### 4. Use Appropriate HTTP Status Codes

```php
// 200 - Success
return new Reply(200, ['data' => $data]);

// 201 - Created
return new Reply(201, ['id' => $newId]);

// 400 - Bad Request (validation error)
return new Reply(400, ['error' => 'Invalid input']);

// 403 - Forbidden (authorization)
return new Reply(403, ['error' => 'Access denied']);

// 404 - Not Found
return new Reply(404, ['error' => 'Resource not found']);

// 500 - Server Error
return new Reply(500, ['error' => 'Something went wrong']);
```

### 5. Keep Methods Under 25 Lines

```php
// Good: Small, focused methods
public function handle(Request $request): Reply {
    $product = $this->getProduct($request);
    $related = $this->getRelatedProducts($product);

    return new Reply(200, Views::render('Product', [
        'product' => $product,
        'related' => $related,
    ]));
}

private function getProduct(Request $request): array {
    $post = Timber::get_post();
    return [
        'title' => $post->title(),
        'price' => get_field('price'),
    ];
}

private function getRelatedProducts(array $product): array {
    return Timber::get_posts([/* ... */]);
}
```

---

## Troubleshooting

### Controller Not Matching

**Problem:** Controller isn't handling expected requests

**Solutions:**
1. Check `$handle` matches exactly (case-sensitive)
2. Verify the page/post exists
3. Check for conflicting controllers with same handle
4. Clear route cache if enabled

### Action Not Found

**Problem:** `callAction()` returns 404

**Solutions:**
1. Ensure method is `public` and non-static
2. Check method name matches action name exactly
3. Verify controller is registered and handles current page
4. Check for typos in action name

### Nonce Validation Failing

**Problem:** Always getting 403 Forbidden

**Solutions:**
1. Ensure nonce name matches in both PHP and JS
2. Check nonce is passed to frontend in view data
3. Verify nonce isn't expired (24-hour default)
4. Ensure user is logged in if required

---

## See Also

- [HTTP Layer](./http.md) - Request, Reply, Action classes
- [Views](./views.md) - Template rendering
- [Frontend Integration](./frontend.md) - callAction usage
- [WordPress Integration](./wordpress.md) - Events and Filters
