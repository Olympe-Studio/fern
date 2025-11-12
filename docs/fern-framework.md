# Fern Framework Documentation

> A modern, type-safe PHP framework for WordPress applications built on PSR-12 standards

## Table of Contents

1. [Introduction](#introduction)
2. [Installation & Bootstrap](#installation--bootstrap)
3. [Configuration System](#configuration-system)
4. [Core Patterns](#core-patterns)
5. [Request/Response Cycle](#requestresponse-cycle)
6. [Controllers](#controllers)
7. [Actions](#actions)
8. [Security Attributes](#security-attributes)
9. [Views & Frontend](#views--frontend)
10. [WordPress Integration](#wordpress-integration)
11. [Utilities](#utilities)
12. [Error Handling](#error-handling)

---

## Introduction

Fern is a modern PHP framework designed specifically for WordPress, bringing structure, type safety, and developer experience improvements to WordPress plugin and theme development.

### Key Features

- **Type-Safe**: PHPStan level 10 compliant
- **PSR-12 Standard**: Clean, consistent code style
- **MVC-Inspired**: Clear separation of concerns
- **Singleton Pattern**: Efficient resource management
- **Attribute-Based Security**: Modern PHP 8+ attributes for nonces, capabilities, and caching
- **Integration Ready**: Seamless integration with Timber, ACF, and WooCommerce

### Philosophy

Fern follows these principles:

- **KISS**: Keep It Simple, Stupid
- **Single Responsibility**: Functions do one thing well (max 25 lines)
- **Type Safety**: Strict types everywhere
- **No Inline Comments**: Self-documenting code through clear naming
- **PHPDoc Required**: All public methods must have comprehensive documentation

---

## Installation & Bootstrap

### Basic Setup

Fern is bootstrapped via the `Fern::defineConfig()` method, typically called in `./src/fern-config.php`.

```php
<?php
use Fern\Core\Fern;

Fern::defineConfig([
    'root' => __DIR__,
    'rendering_engine' => new \App\Services\Astro\Astro(),
    'core' => [
        'routes' => [
            'disable' => [
                'author_archive' => true,
                'tag_archive' => true,
                'category_archive' => true,
                'date_archive' => true,
                'feed' => true,
                'search' => true,
            ],
        ],
    ],
    'theme' => [
        'support' => [
            'post-thumbnails' => true,
            'title-tag' => true,
            'html5' => ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption'],
        ],
        'menus' => [
            'primary' => 'Primary Menu',
            'footer' => 'Footer Menu',
        ],
    ],
]);
```

### Application Bootstrap

After Fern configuration, your `App\App` class's `boot()` method is called:

```php
<?php
namespace App;

class App {
    public static function boot(): void {
        // Register controllers, services, hooks, etc.
    }
}
```

This is used to initialized elements that required the config to boot first.

---

## Configuration System

Fern's `Config` class provides a centralized configuration system with dot notation support.

### Setting Configuration

Configuration is set during bootstrap via `Fern::defineConfig()`.

### Accessing Configuration

```php
use Fern\Core\Config;

// Get with dot notation
$engine = Config::get('rendering_engine');
$disableSearch = Config::get('core.routes.disable.search', false);

// Check if exists
if (Config::has('theme.menus')) {
    // ...
}

// Get all configuration
$allConfig = Config::all();
```

### Configuration Filters

You can modify configuration before it's set:

```php
use Fern\Core\Wordpress\Filters;

Filters::on('fern:core:config', function(array $config): array {
    $config['custom_setting'] = 'value';
    return $config;
});
```

---

## Core Patterns

### Singleton Pattern

Most Fern services use the `Singleton` pattern for efficient resource management.

```php
use Fern\Core\Factory\Singleton;

class MyService extends Singleton {
    protected function __construct() {
        // Initialize service
    }

    public function doSomething(): void {
        // Service logic
    }
}

// Usage
$service = MyService::getInstance();
$service->doSomething();
```

**Key Points:**
- Constructor is `protected`
- No cloning or unserialization allowed
- One instance per class throughout the request lifecycle

---

## Request/Response Cycle

### Request Object

The `Request` class encapsulates all HTTP request data and WordPress context.

```php
use Fern\Core\Services\HTTP\Request;

$request = Request::getCurrent();

// HTTP Methods
if ($request->isPost()) { /* ... */ }
if ($request->isGet()) { /* ... */ }

// URL Parameters
$page = $request->getUrlParam('page');
$allParams = $request->getUrlParams();

// Request Body
$body = $request->getBody();
$email = $request->getBodyParam('email');

// Headers
$userAgent = $request->getUserAgent();
$header = $request->getHeader('X-Custom-Header');

// WordPress Context
$postType = $request->getPostType(); // 'post', 'page', 'product', etc.
$taxonomy = $request->getTaxonomy(); // 'category', 'post_tag', etc.
$id = $request->getId(); // Current post/page/term ID

// Conditional Checks
if ($request->isHome()) { /* ... */ }
if ($request->isArchive()) { /* ... */ }
if ($request->isTerm()) { /* ... */ }
if ($request->isAdmin()) { /* ... */ }
if ($request->isAction()) { /* ... */ } // Fern action request
```

### Reply Object

The `Reply` class handles all HTTP responses.

```php
use Fern\Core\Services\HTTP\Reply;

// Basic reply
$reply = new Reply(200, 'Hello World');
$reply->send(); // Outputs and exits

// JSON reply (automatically detected from array)
$reply = new Reply(200, ['success' => true, 'data' => $data]);
$reply->send();

// Custom content type
$reply = new Reply(200, $xml, 'application/xml');

// Chaining methods
$reply = new Reply(200, $data)
    ->code(201)
    ->setHeader('X-Custom', 'value')
    ->contentType('application/json');

// Redirects
$reply->redirect('https://example.com');

// Prevent sending (hijack)
$reply->hijack(); // Useful for streaming or custom handling
```

**Reply Methods:**
- `code(int)` / `status(int)` / `statusCode(int)` - Set HTTP status
- `contentType(string)` - Set content type
- `setHeader(string, mixed)` - Add header
- `removeHeader(string)` - Remove header
- `getBody()` / `setBody(mixed)` - Get/set body
- `send(?mixed)` - Send response and exit
- `hijack()` - Prevent automatic sending

---

## Controllers

Controllers handle requests and return replies. They are the entry point for page rendering.

### Controller Interface

All controllers must implement the `Controller` interface:

```php
namespace Fern\Core\Services\Controller;

interface Controller {
    public function handle(Request $request): Reply;
    public static function getInstance(array ...$args): static;
}
```

### ViewController Pattern

Most controllers extend `ViewController` (which extends `Singleton` and implements `Controller`):

```php
use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class ProductController extends ViewController implements Controller {
    public static string $handle = 'product'; // Post type or page ID

    public function handle(Request $request): Reply {
        $post = Timber::get_post();

        return new Reply(200, Views::render('Product', [
            'title' => $post->title(),
            'content' => $post->content(),
        ]));
    }
}
```

This allows to use traits that are shared a high number of time.

### Controller Registration

Controllers are typically auto-discovered based on their `$handle` property, which can be:

- **Page ID**: `'4'` - Handles page with ID 4
- **Post Type**: `'product'` - Handles all posts of type 'product'
- **Taxonomy**: Taxonomy slug for term archives
- **Archive Handle**: `'archive_product'` - Custom archive handler

### Admin Controllers

Admin controllers use the `AdminController` trait:

```php
use Fern\Core\Services\Controller\AdminController;

class SettingsController extends ViewController implements Controller {
    use AdminController;

    public static string $handle = 'my-settings-page'; // Menu slug

    public function configure(): array {
        return [
            'page_title' => 'My Settings',
            'menu_title' => 'Settings',
            'capability' => 'manage_options',
            'menu_slug' => 'my-settings-page',
            'icon_url' => 'dashicons-admin-generic',
            'position' => 99,
        ];
    }

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('AdminSettings', [
            'options' => get_option('my_settings', []),
        ]));
    }
}
```

---

## Actions

Actions are methods of controllers that can be called by the frontend through special AJAX requests that use the frontend library @ferndev/core that expose a callAction function.

All public non-static methods of a controller are actions.
If you don't want a method to be callable from the frontend, you need to make it private or static.

### Basic Action

```php
class MyController extends ViewController implements Controller {
    public static string $handle = '4';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('MyPage', []));
    }

    public function submitForm(Request $request): Reply {
        $action = $request->getAction();
        $email = sanitize_email($action->get('email'));
        $message = sanitize_textarea_field($action->get('message'));

        // Process form...

        return new Reply(200, ['success' => true]);
    }
}
```

### Action Class API

The action class allows an easier access to the request.

```php
$action = $request->getAction();

// Get action name
$name = $action->getName(); // 'submitForm'

// Get arguments
$email = $action->get('email', 'default@example.com');
$allArgs = $action->getRawArgs();

// Check arguments
if ($action->has('email')) { /* ... */ }
if ($action->hasNot('phone')) { /* ... */ }

// Modify arguments (rare, usually for middleware)
$action->add('timestamp', time());
$action->update('email', strtolower($email));
$action->remove('sensitive_field');
$action->merge(['extra' => 'data']);
```

### Action Traits

For shared actions across multiple controllers, use traits:

```php
trait FormActions {
    public function submitContact(Request $request): Reply {
        $action = $request->getAction();
        // Handle contact form
        return new Reply(200, ['success' => true]);
    }
}

class HomeController extends ViewController implements Controller {
    use FormActions;

    public static string $handle = '4';

    public function handle(Request $request): Reply {
        // ...
    }
}
```

---

## Security Attributes

Fern uses PHP 8+ attributes for declarative security and caching.

### Nonce Validation

The `#[Nonce]` attribute validates WordPress nonces automatically:

```php
use Fern\Core\Services\Actions\Attributes\Nonce;

class MyController extends ViewController implements Controller {
    #[Nonce('contact_form')]
    public function submitContact(Request $request): Reply {
        $action = $request->getAction();
        // Nonce is validated before this method executes
        $email = sanitize_email($action->get('email'));

        return new Reply(200, ['success' => true]);
    }
}
```

**Frontend Nonce Generation:**
```php
// In your view data
'nonces' => [
    'contact_form' => wp_create_nonce('contact_form'),
]
```

### Capability Checks

The `#[RequireCapabilities]` attribute ensures users have required permissions:

```php
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;

class AdminController extends ViewController implements Controller {
    #[RequireCapabilities(['manage_options'])]
    public function saveSettings(Request $request): Reply {
        // Only users with 'manage_options' can execute this
        $action = $request->getAction();
        $settings = $action->get('settings');

        update_option('my_settings', $settings);

        return new Reply(200, ['success' => true]);
    }

    #[RequireCapabilities(['edit_posts', 'publish_posts'])]
    public function publishPost(Request $request): Reply {
        // Requires BOTH capabilities
        return new Reply(200, ['success' => true]);
    }
}
```

### Reply Caching

The `#[CacheReply]` attribute caches action responses:

```php
use Fern\Core\Services\Actions\Attributes\CacheReply;

class DataController extends ViewController implements Controller {
    #[CacheReply(ttl: 3600)] // Cache for 1 hour
    public function getStats(Request $request): Reply {
        // Expensive operation
        $stats = $this->calculateStats();

        return new Reply(200, $stats);
    }

    #[CacheReply(ttl: 1800, key: 'user_data', varyBy: ['user_id'])]
    public function getUserData(Request $request): Reply {
        $userId = $request->getAction()->get('user_id');
        // Cache varies by user_id parameter

        return new Reply(200, ['user' => get_user_by('id', $userId)]);
    }
}
```

**CacheReply Parameters:**
- `ttl` (int): Time to live in seconds (default: 3600)
- `key` (string|null): Custom cache key (default: auto-generated)
- `varyBy` (array): Action parameters to vary cache by (default: [])

### Combining Attributes

You can stack multiple attributes:

```php
#[Nonce('save_settings')]
#[RequireCapabilities(['manage_options'])]
#[CacheReply(ttl: 600)]
public function saveAndGetSettings(Request $request): Reply {
    // Validated nonce, checked capabilities, and cached result
    return new Reply(200, ['settings' => get_option('my_settings')]);
}
```

---

## Views & Frontend

### Views System

The `Views` class renders templates and passes data to the frontend.

```php
use Fern\Core\Services\Views\Views;

// In a controller
public function handle(Request $request): Reply {
    return new Reply(200, Views::render('HomePage', [
        'title' => 'Welcome',
        'posts' => $posts,
        'user' => wp_get_current_user(),
    ]));
}
```

**Template Resolution:**
- Templates are located in `resources/src/pages/`
- File name matches the template name: `Views::render('HomePage')` → `resources/src/pages/HomePage.astro`

### Rendering Engines

Fern supports multiple rendering engines via the `RenderingEngine` interface:

- **Remote Engine**: Communicates with external service (Astro dev server)
- **Vanilla Engine**: Traditional PHP templates

Configure in `Fern::defineConfig()`:
```php
'rendering_engine' => new \App\Services\Astro\Astro(),
```

### Context System

Global application context is available in all views:

```php
use Fern\Core\Context;

// Set context (usually in App::boot())
Context::set([
    'site_name' => get_bloginfo('name'),
    'current_user' => wp_get_current_user(),
]);

// Get context
$ctx = Context::get();

// Inject via filter
Filters::on('fern:core:ctx', function(array $ctx): array {
    $ctx['custom_data'] = 'value';
    return $ctx;
});
```

**In Views:**
All context is automatically available as `ctx` in view data:
```php
Views::render('Page', ['title' => 'Test']); // ctx is merged automatically
```

### Client-Side Action Calls

Use the `callAction` function from `@ferndev/core` to call server actions:

```typescript
import { callAction } from '@ferndev/core';

const { data, error } = await callAction('submitContact', {
    email: 'user@example.com',
    message: 'Hello world'
}, nonce);

if (error) {
    console.error(error.message);
} else {
    console.log('Success:', data);
}
```

**TypeScript Types:**
```typescript
type ActionResult<T = any> = Promise<{
    data?: T;
    error?: { message: string; status?: number };
    status: 'ok' | 'error';
}>;
```

**FormData Support:**
```typescript
const formData = new FormData();
formData.append('email', 'user@example.com');

const { data, error } = await callAction('submitContact', formData, nonce);
```

---

## WordPress Integration

### Events (Actions)

Fern's `Events` class wraps WordPress actions:

```php
use Fern\Core\Wordpress\Events;

// Add event handler
Events::on('init', function(): void {
    register_post_type('product', [/* ... */]);
});

// Multiple events
Events::on(['wp_enqueue_scripts', 'admin_enqueue_scripts'], function(): void {
    wp_enqueue_style('my-style', /* ... */);
});

// Trigger event
Events::trigger('my_custom_event', $arg1, $arg2);

// Render to string
$html = Events::renderToString('my_template_hook', [$data]);

// Remove handlers
Events::removeHandlers('init');
Events::removeHandlers(['init', 'wp_head']);
```

### Filters

Fern's `Filters` class wraps WordPress filters:

```php
use Fern\Core\Wordpress\Filters;

// Add filter
Filters::on('the_content', function(string $content): string {
    return $content . '<p>Footer text</p>';
});

// Apply filter
$value = Filters::apply('my_custom_filter', $startingValue, $arg1, $arg2);

// Remove handlers
Filters::removeHandlers('the_content');
```

### Timber Integration

Fern works seamlessly with Timber:

```php
use Timber\Timber;

$post = Timber::get_post(); // Current post
$posts = Timber::get_posts(['post_type' => 'product']);

return new Reply(200, Views::render('Archive', [
    'posts' => $posts,
]));
```

Timber is the prefered way to make Wordpress interaction when possible.

### ACF Integration

Advanced Custom Fields work naturally:

```php
$fields = get_fields(); // ACF fields for current post
$value = get_field('custom_field', $postId);

return new Reply(200, Views::render('Page', [
    'fields' => $fields,
]));
```

---

## Utilities

### Config

See [Configuration System](#configuration-system).

### Cache

```php
use Fern\Core\Utils\Cache;

// Store value
Cache::set('my_key', $data, 3600); // TTL in seconds

// Retrieve value
$data = Cache::get('my_key', $default);

// Check if exists
if (Cache::has('my_key')) { /* ... */ }

// Delete
Cache::delete('my_key');

// Clear all
Cache::flush();
```

### JSON

Type-safe JSON encoding/decoding:

```php
use Fern\Core\Utils\JSON;

// Encode
$json = JSON::encode(['key' => 'value']); // Returns string or false

// Decode
$data = JSON::decode($json, true); // Associative array
$obj = JSON::decode($json, false); // Object
```

### Types

Type checking utilities:

```php
use Fern\Core\Utils\Types;

Types::isString($value);
Types::isInt($value);
Types::isArray($value);
Types::isBool($value);
// ... and more
```

---

## Error Handling

Fern provides specific exception classes for different error scenarios:

### Exception Types

```php
use Fern\Core\Errors\ActionException;
use Fern\Core\Errors\ActionNotFoundException;
use Fern\Core\Errors\AttributeValidationException;
use Fern\Core\Errors\ControllerRegistration;
use Fern\Core\Errors\FernConfigurationExceptions;
use Fern\Core\Errors\ReplyParsingError;
use Fern\Core\Errors\RouterException;
use Fern\Core\Errors\ViewsExceptions;
```

### Error Handling in Actions

```php
public function riskyAction(Request $request): Reply {
    try {
        // Risky operation
        $result = $this->doSomethingRisky();

        return new Reply(200, ['success' => true, 'data' => $result]);
    } catch (\Exception $e) {
        // Log error
        error_log('Action failed: ' . $e->getMessage());

        // Return error response
        return new Reply(500, [
            'success' => false,
            'message' => 'An error occurred',
        ]);
    }
}
```

### Debug Helper

```php
// Dump and die (development only)
dd($variable); // Outputs variable and exits

// simple dump and keep going
dump($var) // output var but keep going.
```

The use of `dd()` and `dump()` is the prefered way of debuging.

---

## Best Practices

### Code Style

1. **Follow PSR-12** with opening brace on same line:
   ```php
   class MyClass {
       public function myMethod(): void {
           // ...
       }
   }
   ```

2. **Use camelCase** for variables and methods:
   ```php
   $myVariable = 'value';
   public function myMethod(): void { }
   ```

3. **Keep functions under 25 lines** (±5):
   ```php
   public function processData(array $data): array {
       $result = $this->validate($data);
       $result = $this->transform($result);
       $result = $this->enrich($result);
       return $result;
   }
   ```

4. **Single Responsibility**:
   - Each function does ONE thing only
   - Break complex logic into smaller methods

5. **No Inline Comments**:
   - Code should be self-documenting
   - Use clear variable and method names

6. **PHPDoc Required**:
   ```php
   /**
    * Process user data and return formatted result
    *
    * @param array<string, mixed> $data The user data to process
    *
    * @return array<string, mixed> The processed data
    */
   public function processUserData(array $data): array {
       // ...
   }
   ```

### Security

1. **Always sanitize user input**:
   ```php
   $email = sanitize_email($action->get('email'));
   $text = sanitize_text_field($action->get('text'));
   $html = wp_kses_post($action->get('content'));
   ```

2. **Use nonce validation** for all state-changing actions:
   ```php
   #[Nonce('my_action')]
   public function myAction(Request $request): Reply { }
   ```

3. **Check capabilities** for privileged operations:
   ```php
   #[RequireCapabilities(['manage_options'])]
   public function adminAction(Request $request): Reply { }
   ```

### Performance

1. **Use caching** for expensive operations:
   ```php
   #[CacheReply(ttl: 3600)]
   public function getExpensiveData(Request $request): Reply { }
   ```

2. **Optimize database queries** with Timber:
   ```php
   $posts = Timber::get_posts([
       'posts_per_page' => 10,
       'fields' => 'ids', // Only fetch IDs if that's all you need
   ]);
   ```

3. **Use WordPress transients** for data caching:
   ```php
   if (false === ($data = get_transient('my_data'))) {
       $data = $this->expensiveOperation();
       set_transient('my_data', $data, HOUR_IN_SECONDS);
   }
   ```

### Type Safety

1. **Always use type hints**:
   ```php
   public function process(string $name, int $age): bool {
       return true;
   }
   ```

2. **Use PHPStan** to catch type errors:
   ```bash
   docker exec toolbox composer phpstan
   ```

3. **Prefer strict types**:
   ```php
   <?php declare(strict_types=1);
   ```

---

## Advanced Topics

### Custom Rendering Engine

Create a custom rendering engine by implementing `RenderingEngine`:

```php
use Fern\Core\Services\Views\RenderingEngine;

class MyEngine implements RenderingEngine {
    public function boot(): void {
        // Initialize engine
    }

    public function render(string $template, array $data): string {
        // Render template with data
        return $html;
    }
}
```

### Router Hooks

Customize router behavior:

```php
// Modify resolved ID before controller lookup
Filters::on('fern:core:router:resolve_id', function(int $id, Request $req): ?int {
    // Return different ID or null
    return $id;
});

// Modify archive page ID
Filters::on('fern:core:router:get_archive_page_id', function(int $id, ?string $type): int {
    return $id;
});

// Control action execution
Filters::on('fern:core:action:can_run', function(bool $canRun, Action $action, $controller): bool {
    // Custom validation
    return $canRun;
});
```

### View Hooks

Modify view rendering:

```php
// Inject global context
Filters::on('fern:core:views:ctx', function(array $ctx): array {
    $ctx['global_data'] = 'value';
    return $ctx;
});

// Inject global data
Filters::on('fern:core:views:data', function(array $data): array {
    $data['timestamp'] = time();
    return $data;
});

// Modify rendered result
Filters::on('fern:core:views:result', function(string $result): string {
    // Modify HTML before sending
    return $result;
});
```

### Reply Hooks

Customize reply behavior:

```php
// Modify headers before sending
Filters::on('fern:core:reply:headers', function(Reply $reply): void {
    $reply->setHeader('X-Powered-By', 'Fern');
});

// Modify body before sending
Filters::on('fern:core:reply:will_be_send', function($body, Reply $reply) {
    // Modify body
    return $body;
});

// After reply sent
Events::on('fern:core:reply:has_been_sent', function(Reply $reply): void {
    // Log, cleanup, etc.
});
```

---

## References

See the `examples/` directory for complete working examples:

- [Basic Controller](examples/example-basic-controller.md)
- [Action with Nonce](examples/example-action-with-nonce.md)
- [Admin Controller](examples/example-admin-controller.md)
- [Archive Controller](examples/example-archive-controller.md)
- [Model Transformer](examples/example-model-transformer.md)
- [Client Action Call](examples/example-client-action-call.md)
- [Cached Action](examples/example-cached-action.md)
- [Capability Check](examples/example-capability-check.md)

API Reference:
- [API Reference](./fern-api-reference.md)

Frontend Reference: