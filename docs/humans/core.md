# Core Classes

> Foundation classes that power the Fern framework

## Overview

The core module provides the fundamental building blocks of Fern: configuration management, application context, the singleton pattern, and the main framework entry point. These classes work together to initialize and configure your WordPress application.

## Quick Start

```php
<?php
use Fern\Core\Fern;

// Bootstrap Fern in your fern-config.php
Fern::defineConfig([
    'root' => __DIR__,
    'rendering_engine' => new \App\Services\Astro\Astro(),
    'core' => [
        'routes' => [
            'disable' => [
                'author_archive' => true,
                'feed' => true,
            ],
        ],
    ],
    'theme' => [
        'support' => ['post-thumbnails' => true],
        'menus' => ['primary' => 'Primary Menu'],
    ],
]);
```

---

## Fern

Main framework class providing static utility methods and application bootstrapping.

### Methods

#### `Fern::defineConfig(array $config): void`

Defines Fern configuration and boots the application. This is your primary entry point.

**Signature:** `public static function defineConfig(array $config): void`

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$config` | `array<string, mixed>` | Yes | Configuration array with root, rendering_engine, core, theme, and other settings |

**Throws:** `FernConfigurationExceptions` if App class not found

**Example:**
```php
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
            'html5' => ['search-form', 'comment-form', 'gallery'],
        ],
        'menus' => [
            'primary' => 'Primary Menu',
            'footer' => 'Footer Menu',
        ],
    ],
    'mailer' => [
        'from_name' => 'My Site',
        'from_address' => 'noreply@example.com',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'user',
        'password' => 'pass',
        'encryption' => 'tls',
    ],
]);
```

**Notes:**
- This method triggers `fern:core:before_boot` and `fern:core:after_boot` events
- After configuration, it calls `\App\App::boot()` automatically
- The `root` key is required; all other keys are optional

---

#### `Fern::getVersion(): string`

Gets the current Fern framework version.

**Signature:** `public static function getVersion(): string`

**Returns:** `string` - The version string (e.g., "0.1.0")

**Example:**
```php
echo Fern::getVersion(); // "0.1.0"
```

---

#### `Fern::isDev(): bool`

Checks if the current environment is development.

**Signature:** `public static function isDev(): bool`

**Returns:** `bool` - `true` if `WP_ENV === 'development'`, `false` otherwise

**Example:**
```php
if (Fern::isDev()) {
    error_log('Running in development mode');
    // Enable debug features
}
```

**Notes:**
- Result is cached after first call for performance
- Checks the `WP_ENV` constant defined in your environment

---

#### `Fern::isNotDev(): bool`

Checks if the current environment is NOT development.

**Signature:** `public static function isNotDev(): bool`

**Returns:** `bool` - `true` if not in development, `false` otherwise

**Example:**
```php
if (Fern::isNotDev()) {
    // Production-only optimizations
    wp_cache_add_non_persistent_groups(['fern']);
}
```

---

#### `Fern::getRoot(): string`

Gets the project root path as defined in configuration.

**Signature:** `public static function getRoot(): string`

**Returns:** `string` - The absolute path to the project root

**Example:**
```php
$root = Fern::getRoot(); // "/var/www/html"
$configPath = $root . '/config/app.php';
```

---

#### `Fern::passed(): bool`

Checks if the router has passed control back to WordPress.

**Signature:** `public static function passed(): bool`

**Returns:** `bool` - `true` if router didn't handle this request

**Example:**
```php
if (Fern::passed()) {
    // Router didn't find a matching controller
    // WordPress will handle this request normally
}
```

---

#### `Fern::context(): array`

Retrieves the global application context.

**Signature:** `public static function context(): array<string, mixed>`

**Returns:** `array<string, mixed>` - The application context data

**Example:**
```php
$context = Fern::context();
echo $context['site_name'];
echo $context['current_user']->display_name;
```

---

## Config

Configuration management with dot notation support and caching.

### Methods

#### `Config::get(string $key, mixed $default = null): mixed`

Gets a configuration value using dot notation.

**Signature:** `public static function get(string $key, mixed $default = null): mixed`

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$key` | `string` | Yes | Dot notation key (e.g., 'core.routes.disable.search') |
| `$default` | `mixed` | No | Default value if key doesn't exist |

**Returns:** `mixed` - The configuration value or default

**Example:**
```php
use Fern\Core\Config;

// Get top-level value
$engine = Config::get('rendering_engine');

// Get nested value with dot notation
$searchDisabled = Config::get('core.routes.disable.search', false);

// Get with default
$timeout = Config::get('api.timeout', 30);

// Access theme menus
$menus = Config::get('theme.menus', []);
```

**Notes:**
- Results are cached for subsequent lookups
- Returns `$default` if any part of the path doesn't exist

---

#### `Config::has(string $key): bool`

Checks if a configuration key exists.

**Signature:** `public static function has(string $key): bool`

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$key` | `string` | Yes | Dot notation key to check |

**Returns:** `bool` - `true` if key exists, `false` otherwise

**Example:**
```php
if (Config::has('mailer')) {
    // Mailer is configured, safe to send emails
    Mailer::send($email);
}

if (Config::has('app.features.dark_mode')) {
    // Feature is configured
}
```

---

#### `Config::all(): array`

Gets all configuration values.

**Signature:** `public static function all(): array<string, mixed>`

**Returns:** `array<string, mixed>` - Complete configuration array

**Example:**
```php
$allConfig = Config::all();
print_r($allConfig);
```

---

#### `Config::toArray(): array`

Alias for `Config::all()`.

**Signature:** `public static function toArray(): array<string, mixed>`

**Returns:** `array<string, mixed>` - Complete configuration array

---

#### `Config::toJson(): string`

Exports configuration as JSON string.

**Signature:** `public static function toJson(): string`

**Returns:** `string` - JSON-encoded configuration

**Example:**
```php
$json = Config::toJson();
file_put_contents('/tmp/config-debug.json', $json);
```

---

### Configuration Filters

You can modify configuration before it's applied using the `fern:core:config` filter:

```php
use Fern\Core\Wordpress\Filters;

Filters::on('fern:core:config', function(array $config): array {
    // Add environment-specific settings
    if (defined('WP_ENV') && WP_ENV === 'staging') {
        $config['app']['debug'] = true;
    }

    return $config;
});
```

---

## Context

Global application context management for sharing data across views.

### Methods

#### `Context::set(array $ctx): void`

Overrides the entire application context.

**Signature:** `public static function set(array $ctx): void`

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$ctx` | `array<string, mixed>` | Yes | Context data to set |

**Example:**
```php
use Fern\Core\Context;

// Usually set in App::boot() or via filter
Context::set([
    'site_name' => get_bloginfo('name'),
    'site_url' => home_url(),
    'current_user' => wp_get_current_user(),
    'is_logged_in' => is_user_logged_in(),
    'locale' => get_locale(),
]);
```

**Notes:**
- Completely replaces existing context
- Typically called once during bootstrap

---

#### `Context::get(): array`

Retrieves the application context.

**Signature:** `public static function get(): array<string, mixed>`

**Returns:** `array<string, mixed>` - The current context data

**Example:**
```php
$context = Context::get();

// Use in view data
return new Reply(200, Views::render('Page', [
    'title' => 'My Page',
    // Context is automatically merged
]));
```

---

#### `Context::boot(): void`

Boots the context singleton and applies the `fern:core:ctx` filter.

**Signature:** `public static function boot(): void`

**Example:**
```php
// Called automatically by Fern, but you can use the filter:
Filters::on('fern:core:ctx', function(array $ctx): array {
    $ctx['custom_data'] = 'value';
    $ctx['navigation'] = wp_get_nav_menu_items('primary');
    return $ctx;
});
```

---

## Singleton

Abstract base class implementing the singleton pattern for services.

### Usage

Extend `Singleton` to create services with a single instance throughout the request lifecycle:

```php
use Fern\Core\Factory\Singleton;

class MyService extends Singleton {
    private array $data = [];

    protected function __construct() {
        // Initialize your service
        $this->data = $this->loadData();
    }

    public function getData(): array {
        return $this->data;
    }

    private function loadData(): array {
        return ['key' => 'value'];
    }
}

// Usage
$service = MyService::getInstance();
$data = $service->getData();
```

### Methods

#### `getInstance(array ...$args): static`

Gets or creates the singleton instance.

**Signature:** `public static function getInstance(array ...$args): static`

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$args` | `array` | No | Constructor arguments (only used on first call) |

**Returns:** `static` - The singleton instance

**Example:**
```php
// First call creates the instance
$cache = Cache::getInstance();

// Subsequent calls return the same instance
$sameCache = Cache::getInstance();

assert($cache === $sameCache); // true
```

### Key Points

1. **Constructor is protected** - Cannot instantiate with `new`
2. **No cloning** - `__clone()` is private
3. **No unserialization** - `__wakeup()` is final and empty
4. **One instance per class** - Stored in static `$_instances` array
5. **Lazy initialization** - Instance created on first `getInstance()` call

---

## Common Patterns

### Bootstrap Sequence

```
1. theme functions.php requires fern-config.php
2. fern:core:before_boot event
3. Configuration loaded and filtered
4. fern:core:config:after_boot event
5. Services boot (I18N, Autoloader, Mailer, WordPress, Router)
6. Theme support registered
7. fern:core:after_boot event
8. App::boot() called
```

### Typical App::boot() Implementation

```php
<?php
namespace App;

use Fern\Core\Context;
use Fern\Core\Wordpress\Events;

class App {
    public static function boot(): void {
        // Set application context
        self::setupContext();

        // Register services
        self::registerServices();

        // Register hooks
        self::registerHooks();
    }

    private static function setupContext(): void {
        Context::set([
            'site_name' => get_bloginfo('name'),
            'current_user' => wp_get_current_user(),
            'is_logged_in' => is_user_logged_in(),
        ]);
    }

    private static function registerServices(): void {
        // Initialize custom services
    }

    private static function registerHooks(): void {
        Events::on('init', [self::class, 'registerPostTypes']);
    }

    public static function registerPostTypes(): void {
        register_post_type('product', [/* ... */]);
    }
}
```

---

## Troubleshooting

### "App class not found" Error

**Problem:** `FernConfigurationExceptions: App class not found`

**Solution:** Ensure you have an `App.php` file in the App namespace with a static `boot()` method:

```php
<?php
// src/App/App.php
namespace App;

class App {
    public static function boot(): void {
        // Your bootstrap code
    }
}
```

### Configuration Not Loading

**Problem:** `Config::get()` returns null for expected values

**Causes:**
1. Called before `Fern::defineConfig()`
2. Typo in key path
3. Filter modifying config incorrectly

**Debug:**
```php
// Check all config
dd(Config::all());

// Check if key exists
if (!Config::has('mykey')) {
    error_log('Key not found in config');
}
```

---

## See Also

- [HTTP Layer](./http.md) - Request, Reply, Action, File classes
- [Controllers](./controllers.md) - Controller implementation guide
- [WordPress Integration](./wordpress.md) - Events and Filters
