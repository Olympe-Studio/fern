# Fern Framework API Reference

> Complete developer reference for all public APIs, hooks, configuration, and CLI commands

**Version:** 0.1.0
**Last Updated:** 2025-01-18

---

## Table of Contents

1. [Core Classes](#core-classes)
2. [HTTP Layer](#http-layer)
3. [Controllers](#controllers)
4. [Security Attributes](#security-attributes)
5. [Views System](#views-system)
6. [WordPress Integration](#wordpress-integration)
7. [Utilities](#utilities)
8. [SEO & Services](#seo--services)
9. [Hooks Reference](#hooks-reference)
10. [Configuration Reference](#configuration-reference)
11. [CLI Commands](#cli-commands)
12. [Folder Structure](#folder-structure)
13. [Type Definitions](#type-definitions)

---

## Core Classes

### Fern

Main framework class providing static utility methods.

#### Methods

##### `Fern::getVersion(): string`

Gets the current Fern framework version.

```php
$version = Fern::getVersion(); // "0.1.0"
```

---

##### `Fern::isDev(): bool`

Checks if the current environment is development.

```php
if (Fern::isDev()) {
    error_log('Running in development mode');
}
```

**Returns:** `true` if `WP_ENV === 'development'`, `false` otherwise

---

##### `Fern::isNotDev(): bool`

Checks if the current environment is NOT development.

```php
if (Fern::isNotDev()) {
    // Production-only code
}
```

---

##### `Fern::getRoot(): string`

Gets the project root path.

```php
$root = Fern::getRoot(); // "/var/www/html"
```

---

##### `Fern::passed(): bool`

Checks if the router has passed control back to WordPress.

```php
if (Fern::passed()) {
    // Router didn't handle this request
}
```

---

##### `Fern::context(): array<string, mixed>`

Retrieves the global application context.

```php
$context = Fern::context();
echo $context['site_name'];
```

---

##### `Fern::defineConfig(array $config): void`

Defines Fern configuration and boots the application.

```php
Fern::defineConfig([
    'root' => __DIR__,
    'rendering_engine' => new \App\Services\Astro\Astro(),
    'core' => [
        'routes' => [
            'disable' => [
                'author_archive' => true,
            ],
        ],
    ],
]);
```

**Parameters:**
- `$config` (array<string, mixed>) - Configuration array

**Throws:** `FernConfigurationExceptions` if App class not found

---

### Config

Configuration management with dot notation support.

#### Methods

##### `Config::get(string $key, mixed $default = null): mixed`

Gets a configuration value using dot notation.

```php
$engine = Config::get('rendering_engine');
$searchDisabled = Config::get('core.routes.disable.search', false);
```

**Parameters:**
- `$key` (string) - Dot notation key
- `$default` (mixed) - Default value if key doesn't exist

---

##### `Config::has(string $key): bool`

Checks if a configuration key exists.

```php
if (Config::has('mailer')) {
    // Mailer is configured
}
```

---

##### `Config::all(): array<string, mixed>`

Gets all configuration.

```php
$allConfig = Config::all();
```

---

### Context

Global application context management.

#### Methods

##### `Context::set(array $ctx): void`

Overrides the entire application context.

```php
Context::set([
    'site_name' => get_bloginfo('name'),
    'user' => wp_get_current_user(),
]);
```

**Parameters:**
- `$ctx` (array<string, mixed>) - Context data

---

##### `Context::get(): array<string, mixed>`

Retrieves the application context.

```php
$context = Context::get();
```

---

##### `Context::boot(): void`

Boots the context singleton and applies filters.

```php
Context::boot(); // Called automatically by Fern
```

---

### Singleton

Base factory class for singleton pattern.

#### Methods

##### `getInstance(array ...$args): static`

Gets the singleton instance.

```php
$cache = Cache::getInstance();
```

**Note:** Constructor is protected, cannot instantiate with `new`

---

## HTTP Layer

### Request

HTTP request wrapper with WordPress context.

#### Constructor

```php
Request::__construct()
```

**Note:** Use `Request::getCurrent()` or `Request::getInstance()` instead

---

#### Static Methods

##### `Request::getCurrent(): Request`

Gets the current request instance.

```php
$request = Request::getCurrent();
```

---

##### `Request::getInstance(): Request`

Gets the singleton request instance.

```php
$request = Request::getInstance();
```

---

#### HTTP Method Checks

##### `isGet(): bool`

```php
if ($request->isGet()) { /* ... */ }
```

##### `isPost(): bool`

```php
if ($request->isPost()) { /* ... */ }
```

##### `isPut(): bool`

```php
if ($request->isPut()) { /* ... */ }
```

##### `isDelete(): bool`

```php
if ($request->isDelete()) { /* ... */ }
```

##### `isPatch(): bool`

```php
if ($request->isPatch()) { /* ... */ }
```

##### `isOptions(): bool`

```php
if ($request->isOptions()) { /* ... */ }
```

##### `isHead(): bool`

```php
if ($request->isHead()) { /* ... */ }
```

---

#### Special Request Types

##### `isAction(): bool`

Checks if request is a Fern action (has X-Fern-Action header).

```php
if ($request->isAction()) {
    // Handle action
}
```

##### `isAjax(): bool`

Checks if request is an AJAX request.

```php
if ($request->isAjax()) { /* ... */ }
```

##### `isREST(): bool`

Checks if request is to WordPress REST API.

```php
if ($request->isREST()) { /* ... */ }
```

##### `isCRON(): bool`

```php
if ($request->isCRON()) { /* ... */ }
```

##### `isCLI(): bool`

```php
if ($request->isCLI()) { /* ... */ }
```

##### `isXMLRPC(): bool`

```php
if ($request->isXMLRPC()) { /* ... */ }
```

##### `isAutoSave(): bool`

```php
if ($request->isAutoSave()) { /* ... */ }
```

##### `isSitemap(): bool`

```php
if ($request->isSitemap()) { /* ... */ }
```

---

#### URL Parameters

##### `getUrlParam(string $key, mixed $default = null): mixed`

Gets a URL query parameter.

```php
$page = $request->getUrlParam('paged', 1);
$search = $request->getUrlParam('s');
```

**Parameters:**
- `$key` (string) - Parameter name
- `$default` (mixed) - Default value

---

##### `getUrlParams(): array<string, mixed>`

Gets all URL parameters.

```php
$params = $request->getUrlParams();
```

---

#### Request Body

##### `getBody(): array<string, mixed>`

Gets the parsed request body.

```php
$body = $request->getBody();
```

---

##### `getBodyParam(string $key, mixed $default = null): mixed`

Gets a specific body parameter.

```php
$email = $request->getBodyParam('email');
$name = $request->getBodyParam('name', 'Anonymous');
```

---

##### `getRawBody(): string`

Gets the raw request body as string.

```php
$rawBody = $request->getRawBody();
```

---

#### Headers

##### `getHeader(string $key): string|null`

Gets a request header.

```php
$userAgent = $request->getHeader('User-Agent');
$contentType = $request->getHeader('Content-Type');
```

---

##### `getHeaders(): array<string, string>`

Gets all request headers.

```php
$headers = $request->getHeaders();
```

---

##### `getUserAgent(): string`

Gets the User-Agent header.

```php
$ua = $request->getUserAgent();
```

---

#### Files

##### `getFiles(): array<string, File>`

Gets uploaded files.

```php
$files = $request->getFiles();
foreach ($files as $file) {
    $file->upload();
}
```

---

##### `getFile(string $key): File|null`

Gets a specific uploaded file.

```php
$avatar = $request->getFile('avatar');
if ($avatar) {
    $avatar->upload();
}
```

---

#### WordPress Context

##### `getId(): int`

Gets the current WordPress object ID.

```php
$postId = $request->getId(); // Post/Page/Term ID
```

---

##### `getCurrentId(): int`

Gets the current queried object ID.

```php
$id = $request->getCurrentId();
```

---

##### `getPostType(): string|null`

Gets the current post type.

```php
$postType = $request->getPostType(); // 'post', 'page', 'product', etc.
```

---

##### `getTaxonomy(): string|null`

Gets the current taxonomy.

```php
$taxonomy = $request->getTaxonomy(); // 'category', 'post_tag', etc.
```

---

#### WordPress Conditionals

##### `isHome(): bool`

```php
if ($request->isHome()) { /* ... */ }
```

##### `isFrontPage(): bool`

```php
if ($request->isFrontPage()) { /* ... */ }
```

##### `isSingle(): bool`

```php
if ($request->isSingle()) { /* ... */ }
```

##### `isPage(): bool`

```php
if ($request->isPage()) { /* ... */ }
```

##### `isArchive(): bool`

```php
if ($request->isArchive()) { /* ... */ }
```

##### `isTerm(): bool`

```php
if ($request->isTerm()) { /* ... */ }
```

##### `isCategory(): bool`

```php
if ($request->isCategory()) { /* ... */ }
```

##### `isTag(): bool`

```php
if ($request->isTag()) { /* ... */ }
```

##### `isAuthor(): bool`

```php
if ($request->isAuthor()) { /* ... */ }
```

##### `isDate(): bool`

```php
if ($request->isDate()) { /* ... */ }
```

##### `isSearch(): bool`

```php
if ($request->isSearch()) { /* ... */ }
```

##### `is404(): bool`

```php
if ($request->is404()) { /* ... */ }
```

##### `isAttachment(): bool`

```php
if ($request->isAttachment()) { /* ... */ }
```

##### `isFeed(): bool`

```php
if ($request->isFeed()) { /* ... */ }
```

##### `isAdmin(): bool`

```php
if ($request->isAdmin()) { /* ... */ }
```

---

#### Action Methods

##### `getAction(): Action`

Gets the action object for the current request.

```php
$action = $request->getAction();
$email = $action->get('email');
```

---

### Reply

HTTP response wrapper.

#### Constructor

```php
new Reply(int $code = 200, mixed $body = '', string $contentType = 'text/html')
```

**Parameters:**
- `$code` (int) - HTTP status code
- `$body` (mixed) - Response body (auto-detects JSON for arrays)
- `$contentType` (string) - Content-Type header

**Examples:**

```php
// HTML response
new Reply(200, '<h1>Hello</h1>');

// JSON response (auto-detected)
new Reply(200, ['success' => true]);

// Custom content type
new Reply(200, $xml, 'application/xml');
```

---

#### Methods

##### `send(?mixed $body = null): never`

Sends the response and exits.

```php
$reply->send();

// Override body
$reply->send(['data' => $data]);
```

**Note:** This method exits the script

---

##### `code(int $code): self`

Sets the HTTP status code.

```php
$reply->code(404);
$reply->code(201)->send();
```

**Aliases:** `status()`, `statusCode()`

---

##### `contentType(string $type): self`

Sets the Content-Type header.

```php
$reply->contentType('application/json');
```

---

##### `setHeader(string $key, mixed $value): self`

Sets a response header.

```php
$reply->setHeader('X-Custom-Header', 'value');
$reply->setHeader('Cache-Control', 'no-cache');
```

---

##### `removeHeader(string $key): self`

Removes a response header.

```php
$reply->removeHeader('X-Powered-By');
```

---

##### `getHeaders(): array<string, mixed>`

Gets all response headers.

```php
$headers = $reply->getHeaders();
```

---

##### `getBody(): mixed`

Gets the response body.

```php
$body = $reply->getBody();
```

---

##### `setBody(mixed $body): self`

Sets the response body.

```php
$reply->setBody(['updated' => true]);
```

---

##### `redirect(string $url, int $code = 302): never`

Redirects to a URL.

```php
$reply->redirect('https://example.com');
$reply->redirect('/login', 301); // Permanent redirect
```

---

##### `hijack(): self`

Prevents the reply from being sent automatically.

```php
$reply->hijack();
// Manual streaming or custom handling
```

---

### Action

Action request handler.

#### Methods

##### `getName(): string`

Gets the action name.

```php
$name = $action->getName(); // 'submitForm'
```

---

##### `get(string $key, mixed $default = null): mixed`

Gets an action argument.

```php
$email = $action->get('email');
$count = $action->get('count', 0);
```

---

##### `getRawArgs(): array<string, mixed>`

Gets all action arguments.

```php
$args = $action->getRawArgs();
```

---

##### `has(string $key): bool`

Checks if an argument exists.

```php
if ($action->has('email')) { /* ... */ }
```

---

##### `hasNot(string $key): bool`

Checks if an argument doesn't exist.

```php
if ($action->hasNot('optional_field')) { /* ... */ }
```

---

##### `add(string $key, mixed $value): self`

Adds an argument (for middleware).

```php
$action->add('timestamp', time());
```

---

##### `update(string $key, mixed $value): self`

Updates an argument.

```php
$action->update('email', strtolower($email));
```

---

##### `remove(string $key): self`

Removes an argument.

```php
$action->remove('sensitive_data');
```

---

##### `merge(array $args): self`

Merges multiple arguments.

```php
$action->merge(['extra' => 'data', 'more' => 'info']);
```

---

##### `isBadRequest(): bool`

Checks if the action is malformed.

```php
if ($action->isBadRequest()) {
    return new Reply(400, 'Bad Request');
}
```

---

### File

File upload handler.

#### Static Methods

##### `File::getAllFromCurrentRequest(): array<int, File>`

Gets all uploaded files from current request.

```php
$files = File::getAllFromCurrentRequest();
foreach ($files as $file) {
    $file->upload();
}
```

---

##### `File::getNotAllowedFileExtensions(): array<string>`

Gets list of disallowed file extensions.

```php
$blocked = File::getNotAllowedFileExtensions();
```

---

#### Instance Properties (Readonly)

```php
public readonly string $id;             // Field ID
public readonly string $name;           // Original filename
public readonly string $fileName;       // Name without extension
public readonly string $fileExtension;  // Extension only
public readonly string $type;           // MIME type
public readonly string $tmp_name;       // Temporary path
public readonly int $error;             // Upload error code
public readonly int $size;              // Size in bytes
```

---

#### Instance Methods

##### `upload(?string $path = null): void`

Uploads the file to WordPress uploads directory.

```php
$file->upload(); // Default upload dir
$file->upload('custom/path'); // Custom subdirectory
```

**Throws:** `FileHandlingError` on failure

---

##### `isFileExtensionAllowed(): bool`

Checks if file extension is allowed.

```php
if ($file->isFileExtensionAllowed()) {
    $file->upload();
}
```

---

##### `delete(): void`

Deletes the file from server.

```php
$file->delete();
```

---

##### `toArray(): array<string, mixed>`

Converts file to array.

```php
$data = $file->toArray();
// ['id', 'name', 'type', 'size', 'url']
```

---

##### Getters

```php
$file->getId(): string
$file->getName(): string
$file->getFullPath(): string
$file->getType(): string
$file->getTmpName(): string
$file->getError(): int
$file->getSize(): int
$file->getUrl(): string|null
$file->getFileName(): string
$file->getFileExtension(): string
```

---

## Controllers

### Controller Interface

All controllers must implement this interface.

```php
interface Controller {
    public function handle(Request $request): Reply;
    public static function getInstance(array ...$args): static;
}
```

---

### ViewController Pattern

Base class for view controllers.

```php
use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;

class MyController extends ViewController implements Controller {
    public static string $handle = 'page_id_or_post_type';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Template', $data));
    }
}
```

**Handle Options:**
- Page ID: `'42'`
- Post type: `'product'`
- Archive: `'archive_product'`
- Default: `'_default'`
- 404: `'_404'`

---

### AdminController Trait

For WordPress admin pages.

```php
use Fern\Core\Services\Controller\AdminController;

class SettingsController extends ViewController implements Controller {
    use AdminController;

    public static string $handle = 'my-settings';

    public function configure(): array {
        return [
            'page_title' => 'Settings',
            'menu_title' => 'Settings',
            'capability' => 'manage_options',
            'menu_slug' => 'my-settings',
            'icon_url' => 'dashicons-admin-settings',
            'position' => 99,
        ];
    }

    public function handle(Request $request): Reply {
        // Render admin page
    }
}
```

**Configure Options:**
- `page_title` (string) - Browser title
- `menu_title` (string) - Menu label
- `capability` (string) - Required capability
- `menu_slug` (string) - URL slug
- `icon_url` (string) - Menu icon (optional)
- `position` (int) - Menu position (optional)
- `parent_slug` (string) - Parent menu for submenu (optional)

---

## Security Attributes

### Nonce

CSRF protection attribute.

```php
use Fern\Core\Services\Actions\Attributes\Nonce;

#[Nonce('action_name')]
public function myAction(Request $request): Reply {
    // Nonce validated automatically
}
```

**Controller needs to pass to view:**

```php
'nonces' => [
    'action_name' => wp_create_nonce('action_name'),
]
```

```typescript
await callAction('myAction', args, nonce);
```

---

### RequireCapabilities

Permission checking attribute.

```php
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;

#[RequireCapabilities(['manage_options'])]
public function adminAction(Request $request): Reply {
    // Only admins can execute
}

#[RequireCapabilities(['edit_posts', 'publish_posts'])]
public function publishAction(Request $request): Reply {
    // Requires ALL capabilities
}
```

**Capabilities are from Wordpress itself:**
- `read` - Basic read access
- `edit_posts`, `publish_posts`, `delete_posts`
- `edit_pages`, `publish_pages`, `delete_pages`
- `edit_users`, `delete_users`, `create_users`
- `manage_options` - Settings access
- `upload_files` - Media uploads
... etc.

---

### CacheReply

Response caching attribute.

```php
use Fern\Core\Services\Actions\Attributes\CacheReply;

#[CacheReply(ttl: 3600)]
public function getStats(Request $request): Reply {
    // Cached for 1 hour
}

#[CacheReply(ttl: 1800, key: 'user_data', varyBy: ['user_id'])]
public function getUserData(Request $request): Reply {
    // Cache varies by user_id parameter
}
```

**Parameters:**
- `ttl` (int) - Time to live in seconds (default: 3600)
- `key` (string|null) - Custom cache key (default: auto-generated)
- `varyBy` (array<string>) - Parameters to vary cache by (default: [])

---

### Combining Attributes

Stack multiple attributes:

```php
#[Nonce('save_settings')]
#[RequireCapabilities(['manage_options'])]
#[CacheReply(ttl: 600)]
public function saveSettings(Request $request): Reply {
    // All validations applied
}
```

---

## Views System

### Views

Template rendering service.

#### Methods

##### `Views::render(string $template, array $data = []): string`

Renders a template with data.

```php
use Fern\Core\Services\Views\Views;

$html = Views::render('HomePage', [
    'title' => 'Welcome',
    'posts' => $posts,
]);

return new Reply(200, $html);
```

**Template Resolution:**
This is based on the rendering engine you use. Typically, when using the AstroEngine:
- File: `resources/src/pages/{$template}.astro`
- Data includes global context automatically

---

### RenderingEngine Interface

For custom rendering engines.

```php
interface RenderingEngine {
    public function render(string $template, array $data = []): string;
    public function renderBlock(string $block, array $data = []): string;
    public function boot(): void;
}
```

**Implementation Example:**

```php
class MyEngine implements RenderingEngine {
    public function boot(): void {
        // Initialize engine
    }

    public function render(string $template, array $data): string {
        // Render template
        return $html;
    }

    public function renderBlock(string $block, array $data): string {
        // Render block
        return $html;
    }
}
```

---

## WordPress Integration

### Events

WordPress actions wrapper.

#### Methods

##### `Events::on(string|array $events, callable $callback, int $priority = 10, int $args = -1): void`

Adds an event handler.

```php
use Fern\Core\Wordpress\Events;

// Single event
Events::on('init', function(): void {
    register_post_type('product', [/* ... */]);
});

// Multiple events
Events::on(['wp_enqueue_scripts', 'admin_enqueue_scripts'], function(): void {
    wp_enqueue_style('my-style', /* ... */);
});

// With priority and args
Events::on('save_post', function($postId): void {
    // ...
}, 20, 1);
```

**Parameters:**
- `$events` (string|array<string>) - Event name(s)
- `$callback` (callable) - Handler function. Always respect callback single responsability.
- `$priority` (int) - Execution priority (default: 10)
- `$args` (int) - Number of arguments (default: auto-detected)

---

##### `Events::trigger(string $event, mixed ...$args): void`

Triggers an event.

```php
Events::trigger('my_custom_event', $arg1, $arg2);
```

---

##### `Events::renderToString(string $event, array $args = []): string`

Captures event output as string.

```php
$html = Events::renderToString('wp_head', []);
```

---

##### `Events::removeHandlers(string|array $events): void`

Removes all handlers from event(s).

```php
Events::removeHandlers('init');
Events::removeHandlers(['wp_head', 'wp_footer']);
```

---

### Filters

WordPress filters wrapper.

#### Methods

##### `Filters::on(string|array $filters, callable $callback, int $priority = 10, int $args = -1): void`

Adds a filter handler.

```php
use Fern\Core\Wordpress\Filters;

// Single filter
Filters::on('the_content', function(string $content): string {
    return $content . '<p>Footer</p>';
});

// Multiple filters
Filters::on(['the_title', 'the_excerpt'], function(string $text): string {
    return strtoupper($text);
});
```

---

##### `Filters::apply(string $filter, mixed $value, mixed ...$args): mixed`

Applies a filter.

```php
$value = Filters::apply('my_custom_filter', $initial, $arg1, $arg2);
```

---

##### `Filters::removeHandlers(string|array $filters): void`

Removes all handlers from filter(s).

```php
Filters::removeHandlers('the_content');
```

---

## Utilities

### Cache

Caching system with in-memory and persistent storage.

#### Methods

##### `Cache::get(string $key): mixed`

Gets a cached value.

```php
use Fern\Core\Utils\Cache;

$data = Cache::get('my_key');
if ($data === null) {
    $data = expensiveOperation();
    Cache::set('my_key', $data, true, 3600);
}
```

---

##### `Cache::set(string $key, mixed $value, bool $persist = false, int $expiration = 14400): void`

Sets a cached value.

```php
Cache::set('my_key', $data); // In-memory only
Cache::set('my_key', $data, true); // Persistent (4 hours)
Cache::set('my_key', $data, true, 3600); // Persistent (1 hour)
```

**Parameters:**
- `$key` (string) - Cache key
- `$value` (mixed) - Value to cache
- `$persist` (bool) - Store across requests (default: false)
- `$expiration` (int) - TTL in seconds (default: 14400 = 4 hours)

---

##### `Cache::useMemo(callable $callback, array $dependencies = [], int $expiration = 14400, bool $persist = false): mixed`

Memoizes a callback result (React-like useMemo).

```php
$result = Cache::useMemo(
    fn() => expensiveCalculation(),
    [$userId, $date], // Dependencies
    3600, // TTL
    true  // Persist
);
```

**Parameters:**
- `$callback` (callable) - Function to memoize
- `$dependencies` (array<mixed>) - Cache invalidation triggers
- `$expiration` (int) - TTL in seconds
- `$persist` (bool) - Store across requests

---

##### `Cache::flush(): void`

Clears all caches.

```php
Cache::flush();
```

---

##### `Cache::save(): void`

Saves persistent cache to database.

```php
Cache::save(); // Called automatically on shutdown
```

---

### JSON

Type-safe JSON handling.

#### Methods

##### `JSON::encode(mixed $data, ?int $flags = null): string|false`

Encodes data to JSON.

```php
use Fern\Core\Utils\JSON;

$json = JSON::encode(['key' => 'value']);
```

**Default flags:** `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`

---

##### `JSON::decode(string $json, bool $associative = false, int $depth = 512, int $flags = 0): mixed`

Decodes JSON string.

```php
$data = JSON::decode($json, true); // Associative array
$obj = JSON::decode($json, false); // Object
```

---

##### `JSON::decodeToArray(string $json, int $depth = 512, int $flags = 0): array<mixed>`

Decodes JSON to array (throws if not array).

```php
$array = JSON::decodeToArray($json);
```

**Throws:** `JsonException` if result is not an array

---

##### `JSON::validate(string $json, int $depth = 512, int $flags = 0): bool`

Validates JSON string.

```php
if (JSON::validate($json)) {
    $data = JSON::decode($json);
}
```

---

##### `JSON::pretty(mixed $data): string|false`

Pretty-prints JSON.

```php
$formatted = JSON::pretty(['key' => 'value']);
```

---

## SEO & Services

### Helmet

SEO meta extraction from popular plugins.

#### Methods

##### `Helmet::getCurrent(): string|null`

Gets SEO metadata from active plugin.

```php
use Fern\Core\Services\SEO\Helmet;

$seoMeta = Helmet::getCurrent();
if ($seoMeta) {
    echo $seoMeta; // <meta> tags
}
```

**Supported Plugins:**
- Yoast SEO
- Rank Math SEO
- All in One SEO
- The SEO Framework
- SEOPress (limited)
- Squirrly SEO (limited)
- Jetpack SEO

---

### Mailer

SMTP configuration service.

#### Configuration

```php
Fern::defineConfig([
    'mailer' => [
        'from_name' => 'Site Name',
        'from_address' => 'noreply@example.com',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'user@example.com',
        'password' => 'password',
        'encryption' => 'tls', // or 'ssl'
    ],
]);
```

**Required Fields:**
- `from_name` (string)
- `from_address` (string)
- `host` (string)
- `port` (int)
- `username` (string)
- `password` (string)

**Optional:**
- `encryption` (string) - 'tls' or 'ssl'

---

## Hooks Reference

### Events (Actions)

Fern-specific WordPress actions.

#### Core Lifecycle

##### `fern:core:before_boot`

Triggered before Fern initializes.

```php
Events::on('fern:core:before_boot', function(): void {
    // Early initialization
});
```

---

##### `fern:core:after_boot`

Triggered after Fern initializes.

```php
Events::on('fern:core:after_boot', function(): void {
    // Late initialization
});
```

---

##### `fern:core:config:after_boot`

Triggered after configuration is loaded.

```php
Events::on('fern:core:config:after_boot', function(): void {
    // Configuration is ready
});
```

---

##### `fern:core:reply:has_been_sent`

Triggered after reply is sent, just before exiting.

```php
Events::on('fern:core:reply:has_been_sent', function(Reply $reply): void {
    // Log, cleanup, etc.
}, 10, 1);
```

---

### Filters

Fern-specific WordPress filters.

#### Configuration

##### `fern:core:config`

Modifies configuration before boot.

```php
Filters::on('fern:core:config', function(array $config): array {
    $config['custom'] = 'value';
    return $config;
});
```

---

#### Context

##### `fern:core:ctx`

Modifies global context.

```php
Filters::on('fern:core:ctx', function(array $ctx): array {
    $ctx['custom_data'] = 'value';
    return $ctx;
});
```

---

##### `fern:core:views:ctx`

Modifies view context.

```php
Filters::on('fern:core:views:ctx', function(array $ctx): array {
    $ctx['global_var'] = 'value';
    return $ctx;
});
```

---

##### `fern:core:views:data`

Modifies view data.

```php
Filters::on('fern:core:views:data', function(array $data): array {
    $data['timestamp'] = time();
    return $data;
});
```

---

##### `fern:core:views:result`

Modifies rendered result.

```php
Filters::on('fern:core:views:result', function(string $html): string {
    return minify($html);
});
```

---

#### Router

##### `fern:core:router:resolve_id`

Modifies resolved ID before controller lookup.

```php
Filters::on('fern:core:router:resolve_id', function(int $id, Request $req): ?int {
    // Polylang integration
    return pll_get_post($id);
}, 10, 2);
```

**Returns:** `int|null` - Resolved ID or null to skip ID-based resolution

---

##### `fern:core:router:get_archive_page_id`

Modifies archive page ID.

```php
Filters::on('fern:core:router:get_archive_page_id', function(int $id, ?string $type): int {
    if ($type === 'product') {
        return 123; // Custom products page
    }
    return $id;
}, 10, 2);
```

---

##### `fern:core:controller_resolve`

Modifies controller handle before resolution.

```php
Filters::on('fern:core:controller_resolve', function(string $handle, string $type): string {
    // Custom logic
    return $handle;
}, 10, 2);
```

---

#### Actions

##### `fern:core:action:can_run`

Controls whether an action can execute.

```php
Filters::on('fern:core:action:can_run', function(bool $canRun, Action $action, $controller): bool {
    if ($action->getName() === 'dangerousAction') {
        return current_user_can('administrator');
    }
    return $canRun;
}, 10, 3);
```

---

#### Reply

##### `fern:core:reply:headers`

Modifies reply headers before sending.

```php
Filters::on('fern:core:reply:headers', function(Reply $reply): void {
    $reply->setHeader('X-Powered-By', 'Fern');
});
```

---

##### `fern:core:reply:will_be_send`

Modifies body before sending.

```php
Filters::on('fern:core:reply:will_be_send', function(mixed $body, Reply $reply): mixed {
    // Modify body
    return $body;
}, 10, 2);
```

---

#### Files

##### `fern:core:file:disallowed_upload_extensions`

Modifies disallowed file extensions.

```php
Filters::on('fern:core:file:disallowed_upload_extensions', function(array $extensions): array {
    $extensions[] = 'exe';
    return $extensions;
});
```

---

##### `fern:core:file:allowed_mime_types`

Modifies allowed MIME types.

```php
Filters::on('fern:core:file:allowed_mime_types', function(array $types): array {
    $types[] = 'image/webp';
    return $types;
});
```

---

#### Views Engine

##### `fern:core:views:engines:remote_timeout`

Sets remote engine timeout.

```php
Filters::on('fern:core:views:engines:remote_timeout', function(): float {
    return 5.0; // 5 seconds
});
```

---

##### `fern:core:views:engines:remote_headers`

Modifies remote engine headers.

```php
Filters::on('fern:core:views:engines:remote_headers', function(array $headers): array {
    $headers['X-Custom'] = 'value';
    return $headers;
});
```

---

#### Controller Attributes

##### `fern:core:controller:attribute_handlers`

Registers custom attribute handlers.

```php
Filters::on('fern:core:controller:attribute_handlers', function(array $handlers): array {
    $handlers[] = new MyCustomHandler();
    return $handlers;
});
```

---

#### Gutenberg

##### `fern:gutenberg:blocks_register`

Registers Gutenberg block paths.

```php
Filters::on('fern:gutenberg:blocks_register', function(array $paths): array {
    $paths[] = '/path/to/blocks';
    return $paths;
});
```

---

##### `fern:gutenberg:block_categories`

Modifies block categories.

```php
Filters::on('fern:gutenberg:block_categories', function(array $categories): array {
    $categories[] = ['slug' => 'custom', 'title' => 'Custom Blocks'];
    return $categories;
});
```

---

##### `fern:gutenberg:render_block_data`

Modifies block render data.

```php
Filters::on('fern:gutenberg:render_block_data', function(array $data, array $blockData, string $view): array {
    $data['extra'] = 'value';
    return $data;
}, 10, 3);
```

---

##### `fern:gutenberg:render_block_html`

Modifies rendered block HTML.

```php
Filters::on('fern:gutenberg:render_block_html', function(string $html, array $blockData): string {
    return '<div class="wrapper">' . $html . '</div>';
}, 10, 2);
```

---

##### `fern:gutenberg:render_block_override`

Overrides block rendering.

```php
Filters::on('fern:gutenberg:render_block_override', function(?string $html, string $view, array $data): ?string {
    if ($view === 'MyBlock') {
        return '<div>Custom render</div>';
    }
    return $html;
}, 10, 3);
```

---

#### WooCommerce

##### `fern:woo:cart_meta_data`

Modifies cart metadata.

```php
Filters::on('fern:woo:cart_meta_data', function(array $meta): array {
    $meta['custom_field'] = 'value';
    return $meta;
});
```

---

##### `fern:woo:should_calculate_taxes`

Controls tax calculation.

```php
Filters::on('fern:woo:should_calculate_taxes', function(bool $calculate): bool {
    return false; // Disable taxes
});
```

---

### Hook Execution Order

0. theme function.php is called. This file is requiring `fern-config.php`
1. `fern:core:before_boot`
2. Configuration loaded
3. `fern:core:config` filter
4. `fern:core:config:after_boot`
5. Fern services boot
6. `fern:core:after_boot`
7. App::boot()
8. WordPress keep resolving the theme until `template_include` or `admin_init` is called.
9. Router resolves request
10. `fern:core:router:resolve_id` filter
11. `fern:core:controller_resolve` filter
12. Controller->handle()
13. `fern:core:views:ctx` filter
14. `fern:core:views:data` filter
15. View rendered
16. `fern:core:views:result` filter
17. `fern:core:reply:headers` filter
18. `fern:core:reply:will_be_send` filter
19. Reply sent
20. `fern:core:reply:has_been_sent` event

---

## Configuration Reference

### Root Configuration

```php
Fern::defineConfig([
    'root' => __DIR__,
    'app' => [...] // This is where you put your project specific config elements.
    'rendering_engine' => RenderingEngine,
    'core' => [...],
    'theme' => [...],
    'mailer' => [...],
]);
```

---

### Core Configuration

```php
'core' => [
    'routes' => [
        'disable' => [
            'author_archive' => bool,    // Disable author archives
            'tag_archive' => bool,       // Disable tag archives
            'category_archive' => bool,  // Disable category archives
            'date_archive' => bool,      // Disable date archives
            'feed' => bool,              // Disable feeds
            'search' => bool,            // Disable search
        ],
    ],
]
```

**Example:**

```php
'core' => [
    'routes' => [
        'disable' => [
            'author_archive' => true,
            'date_archive' => true,
            'feed' => true,
        ],
    ],
]
```

---

### Theme Configuration

```php
'theme' => [
    'support' => [
        'post-thumbnails' => bool|array,
        'title-tag' => bool,
        'html5' => array<string>,
        'custom-logo' => array,
        'custom-header' => array,
        'custom-background' => array,
        'editor-styles' => bool,
        'wp-block-styles' => bool,
        'responsive-embeds' => bool,
        'align-wide' => bool,
    ],
    'menus' => [
        'location_slug' => 'Menu Name',
    ],
]
```

**Example:**

```php
'theme' => [
    'support' => [
        'post-thumbnails' => true,
        'title-tag' => true,
        'html5' => ['search-form', 'comment-form', 'gallery'],
        'custom-logo' => [
            'height' => 100,
            'width' => 400,
            'flex-height' => true,
        ],
    ],
    'menus' => [
        'primary' => 'Primary Navigation',
        'footer' => 'Footer Navigation',
        'mobile' => 'Mobile Menu',
    ],
]
```

---

### Mailer Configuration

```php
'mailer' => [
    'from_name' => string,      // Sender name (required)
    'from_address' => string,   // Sender email (required)
    'host' => string,           // SMTP host (required)
    'port' => int,              // SMTP port (required)
    'username' => string,       // SMTP username (required)
    'password' => string,       // SMTP password (required)
    'encryption' => string,     // 'tls' or 'ssl' (optional)
]
```

**Example:**

```php
'mailer' => [
    'from_name' => 'My Website',
    'from_address' => 'noreply@example.com',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'user@gmail.com',
    'password' => 'app-specific-password',
    'encryption' => 'tls',
]
```

---

### Rendering Engine Configuration

```php
'rendering_engine' => new RemoteEngine([
    'url' => 'http://bun:3000',
    'timeout' => 2.5,
])
```

or

```php
'rendering_engine' => new VanillaEngine([
    'template_dir' => __DIR__ . '/templates',
])
```

---

## CLI Commands

Fern provides WP-CLI commands for scaffolding.

### Prerequisites

WP-CLI must be installed and accessible.

---

### fern:controller create

Creates a new controller file.

#### Syntax

```bash
wp fern:controller create <name> <handle> [options]
```

#### Arguments

- `<name>` (required) - Controller class name (without "Controller" suffix)
- `<handle>` (required) - Page ID, post type, taxonomy, or 'page'

#### Options

- `--subdir=<subdir>` - Place controller in subdirectory
- `--create-page` - Create WordPress page (when handle is 'page')
- `--light` - Use minimal template (no exemple action)

#### Examples

**Basic Page Controller:**

```bash
wp fern:controller create Home 42
```

Creates: `src/App/Controllers/HomeController.php` with handle `'42'`

---

**Post Type Controller:**

```bash
wp fern:controller create Product product
```

Creates: `src/App/Controllers/ProductController.php` with handle `'product'`

---

**With Subdirectory:**

```bash
wp fern:controller create Dashboard dashboard --subdir=Admin
```

Creates: `src/App/Controllers/Admin/DashboardController.php`

---

**Create Page Automatically:**

```bash
wp fern:controller create About page --create-page
```

Creates:
1. WordPress page titled "About"
2. Controller with the new page ID as handle

---

**Light Template:**

```bash
wp fern:controller create Simple 99 --light
```

Creates minimal controller without extra exemples and comments.

---

## Folder Structure

### Backend Structure (`src/`)

```
src/
├── App/                          # Application layer
│   ├── Actions/                  # Action traits
│   │   └── FormActions.php       # Example: Form handling
│   ├── Blocks/                   # Gutenberg blocks
│   │   └── hero/
│   │       └── button/
│   │           └── block.json
|   |       └── blocks-manifest.php # used for opcaching blocks.
│   ├── Controllers/              # Route handlers
│   │   ├── MyAccount/            # Subdirectory for organization
│   │   │   ├── DashboardController.php
│   │   │   └── Actions/
│   │   │       └── EditAccountActions.php
│   │   ├── HomeController.php    # Page ID controller
│   │   ├── ProductController.php # Post type controller
│   │   ├── NotFoundController.php # 404 handler
│   │   └── DefaultController.php # Default fallback
│   ├── Models/                   # Data transformers
│   │   ├── Post.php
│   │   └── Product.php
│   ├── Services/                 # Business logic
│   │   ├── Astro/                # Custom rendering engine
│   │   │   └── Astro.php
│   │   ├── Controllers/          # Controller base classes
│   │   │   └── ViewController.php
│   │   └── Woo/                  # WooCommerce integration
│   │       └── Woo.php
│   ├── Schemas/                  # Schemas. Used to define ACF fields.
│   ├── App.php                   # Application bootstrap
│   ├── _context.php              # Context setup (procedural)
│   ├── _postTypes.php            # Post type registration (procedural)
│   ├── _taxonomies.php           # Taxonomy registration (procedural)
│   ├── _shortcode.php            # Shortcodes (procedural)
│   └── _routes_cache.fern.php    # Auto-generated route cache (DO NOT TOUCH)
├── config/                       # Configuration files
│   ├── environments/             # Environment-specific config
│   │   ├── development.php
│   │   ├── staging.php
│   │   └── production.php
│   └── app.php                   # Main config
├── languages/                    # Translation files .po, .mo, etc.
├── logs/                         # Fern logs
│   └── fern.log
├── public/                       # Public assets
│   ├── assets/                   # Built frontend assets
│   ├── fonts/
│   ├── content/                  # wp-content folder.
│   ├── svg/
│   └── wp/                       # WordPress core
└── vendor/                       # Composer dependencies
```

---

### Frontend Structure (`resources/`)

```
resources/
├── dist/                         # Built assets (auto-generated)
│   ├── client/
│   └── server/
├── src/                          # Astro source files
│   ├── assets/                   # Static assets
│   │   ├── images/
│   │   └── styles/
│   ├── components/               # UI components
│   │   ├── Header.astro
│   │   ├── Footer.astro
│   │   ├── ClientSideComponent.tsx # Solidjs component
│   │   ├── ...
│   ├── forms/                    # Form definitions
│   ├── layouts/                  # Page layouts
│   │   └── Layout.astro
│   ├── middleware/               # Astro middleware
│   ├── pages/                    # Page templates (matched by Views::render)
│   │   ├── HomePage.astro        # Views::render('HomePage')
│   │   ├── Product.astro         # Views::render('Product')
│   │   └── AdminSettings.astro   # Views::render('AdminSettings')
│   ├── scripts/                  # Vanilla js client-side scripts
│   ├── stores/                   # State management (Nanostores)
│   │   └── cart.ts
│   ├── types/                    # TypeScript types
│   └── utils/                    # Utilities
├── public/                       # Static public files
├── astro.config.mjs              # Astro configuration
├── package.json
├── tsconfig.json
└── tailwind.config.js
```

---

### Naming Conventions

#### PHP Files

**Classes (PascalCase):**
- `HomeController.php`
- `ProductController.php`
- `FormActions.php`

**Procedural (underscore prefix):**
- `_context.php`
- `_postTypes.php`
- `_fields.php`

#### Frontend Files

**Components (PascalCase):**
- `Header.astro`
- `ContactForm.tsx`

**Pages (PascalCase, match Views::render):**
- `HomePage.astro` → `Views::render('HomePage')`
- `Product.astro` → `Views::render('Product')`

**Utilities (camelCase):**
- `formatDate.ts`
- `apiClient.ts`

---

### File Placement Guidelines

**Controllers:** `src/App/Controllers/`
- Organized by feature/section when needed
- Example: `MyAccount/DashboardController.php`

**Actions (Traits):** place in : `src/App/Actions/`

**Models:** `src/App/Models/`
- Named after the data they interact with
- Example: `Post.php`, `Product.php`, `User.php`

**Services:** `src/App/Services/`
- Business logic, integrations, utilities
- Organized by responsibility

**Views:** `resources/src/pages/`
- Must match `Views::render()` template name
- Can be in subdirectories: `Views::render('Admin/Settings')` → `pages/Admin/Settings.astro`

---

## Type Definitions

### PHPStan Types

```php
/**
 * @phpstan-type ConfigValue array<string, mixed>|mixed
 * @phpstan-type RouterConfig array{
 *     disable?: array{
 *         author_archive?: bool,
 *         tag_archive?: bool,
 *         category_archive?: bool,
 *         date_archive?: bool,
 *         feed?: bool,
 *         search?: bool
 *     }
 * }
 * @phpstan-type ControllerRegistry array{
 *     view: array<string, class-string<Controller>>,
 *     admin: array<string, class-string<Controller>>,
 *     default: ?class-string<Controller>,
 *     _404: ?class-string<Controller>
 * }
 */
```

---

### TypeScript Types

```typescript
// From @ferndev/core

type ActionResult<T = any> = Promise<{
  data?: T;
  error?: { message: string; status?: number };
  status: 'ok' | 'error';
}>;

type ActionArgs = Record<string, any> | FormData;

function callAction<T>(
  action: string,
  args?: ActionArgs,
  nonce?: string
): ActionResult<T>;
```

---

## Best Practices

### Type Safety

✅ **Always use type hints:**

```php
public function process(string $name, int $age): bool {
    return true;
}
```

❌ **Avoid:**

```php
public function process($name, $age) {
    return true;
}
```

---

### Error Handling

✅ **Return appropriate status codes:**

```php
if ($userId === 0) {
    return new Reply(400, ['error' => 'Invalid user ID']);
}
```

✅ **Use exceptions for exceptional cases:**

```php
if (!file_exists($path)) {
    throw new FileHandlingError('File not found');
}
```

---

### Security

✅ **Always validate nonces:**

```php
#[Nonce('my_action')]
public function myAction(Request $request): Reply { }
```

✅ **Always sanitize input:**

```php
$email = sanitize_email($action->get('email'));
$text = sanitize_text_field($action->get('text'));
```

✅ **Check capabilities:**

```php
#[RequireCapabilities(['manage_options'])]
public function adminAction(Request $request): Reply { }
```

---

### Performance

✅ **Use caching for expensive operations:**

```php
#[CacheReply(ttl: 3600)]
public function getStats(Request $request): Reply { }
```

✅ **Use transients for data caching:**

```php
if (false === ($data = get_transient('my_data'))) {
    $data = expensiveOperation();
    set_transient('my_data', $data, HOUR_IN_SECONDS);
}
```

---

## See Also

- [Fern Framework Documentation](fern-framework.md) - Complete guide with concepts and examples
- [Example Files](examples/) - Working code examples
- [Fern Documentation Plan](fern-doc-plan.md) - Documentation structure overview

---

**End of API Reference**
