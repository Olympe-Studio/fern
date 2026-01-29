# HTTP Layer

> Request handling, response building, action processing, and file uploads

## Overview

The HTTP layer provides a complete abstraction for handling incoming requests, building responses, processing frontend actions, and managing file uploads. These classes form the foundation of Fern's request/response cycle.

## Quick Start

```php
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\HTTP\Reply;

class MyController extends ViewController implements Controller {
    public static string $handle = '42';

    public function handle(Request $request): Reply {
        // Access request data
        $page = $request->getUrlParam('page', 1);

        if ($request->isPost()) {
            $email = $request->getBodyParam('email');
        }

        // Return response
        return new Reply(200, Views::render('MyPage', [
            'data' => $this->getData(),
        ]));
    }
}
```

---

## Request

HTTP request wrapper with WordPress context integration. Provides access to all request data including headers, body, files, URL parameters, cookies, and WordPress-specific conditionals.

### Getting the Request

```php
use Fern\Core\Services\HTTP\Request;

// In a controller (preferred)
public function handle(Request $request): Reply {
    // $request is injected automatically
}

// Anywhere else
$request = Request::getCurrent();
// or
$request = Request::getInstance();
```

### HTTP Method Checks

| Method | Description |
|--------|-------------|
| `isGet(): bool` | Check if GET request |
| `isPost(): bool` | Check if POST request |
| `isPut(): bool` | Check if PUT request |
| `isDelete(): bool` | Check if DELETE request |
| `isPatch(): bool` | Check if PATCH request |
| `isHead(): bool` | Check if HEAD request |
| `isOptions(): bool` | Check if OPTIONS request |

**Example:**
```php
if ($request->isPost()) {
    // Handle form submission
    $data = $request->getBody();
}

if ($request->isDelete()) {
    return new Reply(405, ['error' => 'Method not allowed']);
}
```

### Special Request Types

| Method | Description |
|--------|-------------|
| `isAction(): bool` | Fern action request (has X-Fern-Action header) |
| `isAjax(): bool` | WordPress AJAX request |
| `isREST(): bool` | WordPress REST API request |
| `isCRON(): bool` | WordPress cron request |
| `isCLI(): bool` | WP-CLI request |
| `isXMLRPC(): bool` | XML-RPC request |
| `isAutoSave(): bool` | WordPress autosave request |
| `isSitemap(): bool` | Sitemap request |
| `isSideRequest(): bool` | Any background request (AJAX, REST, CRON, CLI) |

**Example:**
```php
if ($request->isAction()) {
    // This is a frontend action call
    $action = $request->getAction();
}

if ($request->isSideRequest()) {
    // Don't render full HTML for background requests
    return new Reply(200, ['data' => $result]);
}
```

### URL Parameters

#### `getUrlParam(string $key, mixed $default = null): mixed`

Gets a URL query parameter.

```php
// URL: /products?page=2&sort=price
$page = $request->getUrlParam('page', 1);        // 2
$sort = $request->getUrlParam('sort');           // "price"
$filter = $request->getUrlParam('filter', 'all'); // "all" (default)
```

#### `getUrlParams(): array`

Gets all URL parameters.

```php
$params = $request->getUrlParams();
// ['page' => '2', 'sort' => 'price']
```

#### `hasUrlParam(string $name): bool`

Checks if a URL parameter exists.

```php
if ($request->hasUrlParam('search')) {
    $query = $request->getUrlParam('search');
}
```

#### `hasNotUrlParam(string $name): bool`

Checks if a URL parameter does NOT exist.

```php
if ($request->hasNotUrlParam('page')) {
    $page = 1; // Default to first page
}
```

#### `addUrlParam(string $name, mixed $value): Request`

Adds a URL parameter (for internal use).

```php
$request->addUrlParam('processed', true);
```

#### `removeUrlParam(string $name): Request`

Removes a URL parameter.

```php
$request->removeUrlParam('temp');
```

### Request Body

#### `getBody(): array|string`

Gets the parsed request body.

```php
// For JSON requests
$body = $request->getBody();
// ['email' => 'user@example.com', 'name' => 'John']

// For form submissions
$body = $request->getBody();
// ['field1' => 'value1', 'field2' => 'value2']
```

#### `getBodyParam(string $key, mixed $default = null): mixed`

Gets a specific body parameter.

```php
$email = $request->getBodyParam('email');
$name = $request->getBodyParam('name', 'Anonymous');
```

#### `getRawBody(): string`

Gets the raw, unparsed request body.

```php
$rawBody = $request->getRawBody();
$signature = hash_hmac('sha256', $rawBody, $secret);
```

### Headers

#### `getHeader(string $key): string|null`

Gets a specific request header.

```php
$contentType = $request->getHeader('Content-Type');
$authorization = $request->getHeader('Authorization');
$customHeader = $request->getHeader('X-Custom-Header');
```

#### `getHeaders(): array`

Gets all request headers.

```php
$headers = $request->getHeaders();
foreach ($headers as $name => $value) {
    error_log("$name: $value");
}
```

#### `hasHeader(string $header): bool`

Checks if a header exists.

```php
if ($request->hasHeader('Authorization')) {
    $token = $request->getHeader('Authorization');
}
```

#### `getUserAgent(): string`

Gets the User-Agent header.

```php
$ua = $request->getUserAgent();
if (str_contains($ua, 'Mobile')) {
    // Mobile user
}
```

### Cookies

#### `getCookie(string $name): string|null`

Gets a specific cookie.

```php
$sessionId = $request->getCookie('session_id');
$preferences = $request->getCookie('user_prefs');
```

#### `getCookies(): array`

Gets all cookies.

```php
$cookies = $request->getCookies();
```

#### `hasCookie(string $name): bool`

Checks if a cookie exists.

```php
if ($request->hasCookie('remember_me')) {
    // Auto-login user
}
```

### WordPress Context

#### `getId(): int`

Gets the current WordPress object ID (post, page, or term).

```php
$postId = $request->getId();
$post = get_post($postId);
```

#### `getCurrentId(): int`

Gets the current queried object ID.

```php
$id = $request->getCurrentId();
```

#### `getPostType(): string|null`

Gets the current post type.

```php
$postType = $request->getPostType();
// 'post', 'page', 'product', null
```

#### `getTaxonomy(): string|null`

Gets the current taxonomy (for term pages).

```php
$taxonomy = $request->getTaxonomy();
// 'category', 'post_tag', 'product_cat', null
```

### WordPress Conditionals

| Method | Description |
|--------|-------------|
| `isHome(): bool` | Home or front page |
| `isFrontPage(): bool` | Front page specifically |
| `isSingle(): bool` | Single post |
| `isPage(): bool` | WordPress page |
| `isArchive(): bool` | Any archive page |
| `isTerm(): bool` | Taxonomy term page |
| `isCategory(): bool` | Category archive |
| `isTag(): bool` | Tag archive |
| `isAuthor(): bool` | Author archive |
| `isDate(): bool` | Date archive |
| `isSearch(): bool` | Search results |
| `is404(): bool` | 404 page |
| `isAttachment(): bool` | Attachment page |
| `isFeed(): bool` | RSS feed |
| `isAdmin(): bool` | Admin area |
| `isPagination(): bool` | Paginated page |
| `isBlog(): bool` | Blog page |
| `isPostTypeArchive(): bool` | Post type archive |
| `isTax(): bool` | Custom taxonomy archive |

**Example:**
```php
if ($request->isArchive()) {
    $posts = Timber::get_posts([
        'post_type' => $request->getPostType(),
        'paged' => $request->getUrlParam('paged', 1),
    ]);
}

if ($request->is404()) {
    return new Reply(404, Views::render('NotFound'));
}
```

### Action Methods

#### `getAction(): Action`

Gets the Action object for processing frontend action calls.

```php
$action = $request->getAction();
$name = $action->getName();
$email = $action->get('email');
```

### Other Methods

#### `getUrl(): string`

Gets the full request URL.

```php
$url = $request->getUrl();
// "https://example.com/products?page=2"
```

#### `getUri(): string`

Gets the request URI path.

```php
$uri = $request->getUri();
// "/products?page=2"
```

#### `getMethod(): string`

Gets the HTTP method.

```php
$method = $request->getMethod();
// "GET", "POST", etc.
```

#### `getContentType(): string`

Gets the request content type.

```php
$contentType = $request->getContentType();
// "application/json", "multipart/form-data", etc.
```

#### `getCode(): int`

Gets the expected response code.

```php
$code = $request->getCode();
// 200 or 404
```

#### `getCountryFrom(): string|null`

Attempts to determine country from Accept-Language header.

```php
$country = $request->getCountryFrom();
// "US", "FR", etc.
```

#### `set404(): never`

Forces a 404 response and redirects.

```php
if (!$postExists) {
    $request->set404();
}
```

#### `toArray(): array`

Converts request to array (useful for debugging).

```php
$debug = $request->toArray();
dd($debug);
```

---

## Reply

HTTP response wrapper with fluent interface for building responses.

### Constructor

```php
new Reply(int $status = 200, mixed $body = '', ?string $contentType = null, array $headers = [])
```

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$status` | `int` | No | HTTP status code (default: 200) |
| `$body` | `mixed` | No | Response body |
| `$contentType` | `string|null` | No | Content-Type (auto-detected for arrays) |
| `$headers` | `array` | No | Additional headers |

**Example:**
```php
// HTML response
$reply = new Reply(200, '<h1>Hello</h1>');

// JSON response (auto-detected from array)
$reply = new Reply(200, ['success' => true, 'data' => $data]);

// Custom content type
$reply = new Reply(200, $xml, 'application/xml');

// With status code
$reply = new Reply(404, Views::render('NotFound'));

// With headers
$reply = new Reply(200, $data, null, [
    'Cache-Control' => 'max-age=3600',
]);
```

### Sending Responses

#### `send(?mixed $data = null): never`

Sends the response and exits.

```php
$reply = new Reply(200, $data);
$reply->send(); // Outputs and exits

// Override body at send time
$reply->send(['updated' => true]);
```

**Notes:**
- This method exits the script
- Triggers `fern:core:reply:has_been_sent` event before exiting
- Automatically handles chunked transfer encoding if trailers are set

### Status Code

#### `code(int $code): self`

Sets the HTTP status code.

```php
$reply->code(201); // Created
$reply->code(404); // Not Found
```

#### `status(int $code): self` / `statusCode(int $code): self`

Aliases for `code()`.

```php
$reply->status(201);
$reply->statusCode(201);
```

### Content Type

#### `contentType(string $type): self`

Sets the Content-Type header.

```php
$reply->contentType('application/json');
$reply->contentType('text/plain');
$reply->contentType('application/xml');
```

#### `type(string $type): self`

Alias for `contentType()`.

#### `getContentType(): string`

Gets the current content type.

```php
$type = $reply->getContentType();
```

### Body

#### `getBody(): mixed`

Gets the response body.

```php
$body = $reply->getBody();
```

#### `setBody(mixed $body): self`

Sets the response body.

```php
$reply->setBody(['updated' => true]);
$reply->setBody('<h1>New Content</h1>');
```

### Headers

#### `setHeader(string $key, mixed $value): self`

Sets a response header.

```php
$reply->setHeader('X-Custom-Header', 'value');
$reply->setHeader('Cache-Control', 'no-cache');
$reply->setHeader('X-Request-Id', uniqid());
```

#### `getHeader(string $key): mixed`

Gets a header value.

```php
$cacheControl = $reply->getHeader('Cache-Control');
```

#### `getHeaders(): array`

Gets all headers.

```php
$headers = $reply->getHeaders();
```

#### `hasHeader(string $key): bool`

Checks if a header exists.

```php
if ($reply->hasHeader('Cache-Control')) {
    // Already set
}
```

#### `removeHeader(string $key): self`

Removes a header.

```php
$reply->removeHeader('X-Powered-By');
```

#### `resetHeader(): self`

Removes all headers.

```php
$reply->resetHeader();
```

### Trailers

HTTP trailers for chunked transfer encoding.

#### `addTrailer(string $name, mixed $value): self`

Adds a trailer header.

```php
$reply->addTrailer('X-Checksum', $checksum);
```

#### `getTrailers(): array`

Gets all trailers.

```php
$trailers = $reply->getTrailers();
```

#### `hasTrailer(string $name): bool`

Checks if a trailer exists.

```php
if ($reply->hasTrailer('X-Checksum')) {
    // Trailer is set
}
```

#### `removeTrailer(string $name): self`

Removes a trailer.

```php
$reply->removeTrailer('X-Checksum');
```

#### `resetTrailers(): self`

Removes all trailers.

```php
$reply->resetTrailers();
```

### Redirects

#### `redirect(string $to, int $code = 302): never`

Redirects to a URL and exits.

```php
$reply->redirect('https://example.com');
$reply->redirect('/login', 301); // Permanent redirect
$reply->redirect(home_url('/dashboard'));
```

### Hijacking

#### `hijack(): self`

Prevents the reply from being sent automatically. Use for streaming or custom handling.

```php
$reply->hijack();

// Manual streaming
header('Content-Type: text/event-stream');
while ($condition) {
    echo "data: " . json_encode($data) . "\n\n";
    flush();
    sleep(1);
}
```

#### `resetHijack(): self`

Re-enables automatic sending.

```php
$reply->resetHijack();
$reply->send();
```

### Serialization

#### `toArray(): array`

Converts the reply to an array (for caching).

```php
$data = $reply->toArray();
set_transient('cached_reply', $data, HOUR_IN_SECONDS);
```

#### `fromArray(array $data): Reply`

Creates a Reply from stored data.

```php
$data = get_transient('cached_reply');
if ($data) {
    $reply = Reply::fromArray($data);
    $reply->send();
}
```

### Fluent Interface

Chain methods for concise response building:

```php
return (new Reply(200, $data))
    ->code(201)
    ->setHeader('X-Custom', 'value')
    ->setHeader('Cache-Control', 'max-age=3600')
    ->contentType('application/json');
```

---

## Action

Handler for frontend action calls via `callAction()`.

### Getting the Action

```php
// In a controller action method
public function submitForm(Request $request): Reply {
    $action = $request->getAction();
    // ...
}

// Or directly
$action = Action::getCurrent();
```

### Methods

#### `getName(): string|null`

Gets the action name.

```php
$name = $action->getName();
// "submitForm", "addToCart", etc.
```

#### `isBadRequest(): bool`

Checks if the action request is malformed.

```php
if ($action->isBadRequest()) {
    return new Reply(400, ['error' => 'Bad Request']);
}
```

#### `get(string $key, mixed $default = null): mixed`

Gets an action argument.

```php
$email = $action->get('email');
$count = $action->get('count', 1);
$options = $action->get('options', []);
```

#### `getRawArgs(): array`

Gets all action arguments.

```php
$args = $action->getRawArgs();
// ['email' => 'user@example.com', 'name' => 'John']
```

#### `has(string $key): bool`

Checks if an argument exists.

```php
if ($action->has('email')) {
    $email = sanitize_email($action->get('email'));
}
```

#### `hasNot(string $key): bool`

Checks if an argument does NOT exist.

```php
if ($action->hasNot('optional_field')) {
    // Use default value
}
```

#### `add(string $key, mixed $value): self`

Adds an argument (useful in middleware).

```php
$action->add('timestamp', time());
$action->add('user_id', get_current_user_id());
```

#### `update(string $key, mixed $value): self`

Updates an existing argument.

```php
$action->update('email', strtolower($action->get('email')));
```

#### `remove(string $key): self`

Removes an argument.

```php
$action->remove('sensitive_data');
```

#### `merge(array $data): self`

Merges multiple arguments.

```php
$action->merge([
    'processed_at' => time(),
    'ip_address' => $_SERVER['REMOTE_ADDR'],
]);
```

#### `setName(string $name): self`

Sets the action name (rarely needed).

```php
$action->setName('newActionName');
```

---

## File

File upload handler with security validations.

### Getting Files

#### `File::getAllFromCurrentRequest(): array`

Gets all uploaded files from the current request.

```php
use Fern\Core\Services\HTTP\File;

$files = File::getAllFromCurrentRequest();
foreach ($files as $file) {
    if ($file->isFileExtensionAllowed()) {
        $file->upload();
        $url = $file->getUrl();
    }
}
```

### File Properties (Readonly)

| Property | Type | Description |
|----------|------|-------------|
| `$id` | `string` | Field ID from form |
| `$name` | `string` | Original filename |
| `$fileName` | `string` | Filename without extension |
| `$fileExtension` | `string` | File extension |
| `$type` | `string` | MIME type |
| `$tmp_name` | `string` | Temporary file path |
| `$error` | `int` | Upload error code |
| `$size` | `int` | Size in bytes |

### Getters

```php
$file->getId();           // "avatar"
$file->getName();         // "photo.jpg"
$file->getFileName();     // "photo"
$file->getFileExtension(); // "jpg"
$file->getType();         // "image/jpeg"
$file->getTmpName();      // "/tmp/phpXXXXXX"
$file->getFullPath();     // Full path after upload
$file->getUrl();          // URL after upload
$file->getError();        // UPLOAD_ERR_OK (0)
$file->getSize();         // 102400
```

### Upload Methods

#### `upload(?string $path = null): void`

Uploads the file to WordPress uploads directory.

```php
// Default upload directory
$file->upload();

// Custom subdirectory
$file->upload('avatars/2024');
$file->upload('documents');
```

**Throws:** `FileHandlingError` on validation failure or upload error

**Security Checks:**
- File extension validation against blocklist
- MIME type validation
- WordPress upload permissions

#### `isFileExtensionAllowed(): bool`

Checks if the file extension is allowed.

```php
if (!$file->isFileExtensionAllowed()) {
    return new Reply(400, ['error' => 'File type not allowed']);
}
```

#### `delete(): void`

Deletes the file from the server.

```php
$file->delete();
```

### Static Methods

#### `File::getNotAllowedFileExtensions(): array`

Gets the list of blocked file extensions.

```php
$blocked = File::getNotAllowedFileExtensions();
// ['php', 'exe', 'sh', 'bat', ...]
```

**Filter:** `fern:core:file:disallowed_upload_extensions`

### Serialization

#### `toArray(): array`

Converts file to array.

```php
$data = $file->toArray();
// ['id', 'name', 'type', 'size', 'url']
```

### Complete Upload Example

```php
public function uploadAvatar(Request $request): Reply {
    $files = File::getAllFromCurrentRequest();

    if (empty($files)) {
        return new Reply(400, ['error' => 'No file uploaded']);
    }

    $file = $files[0];

    if (!$file->isFileExtensionAllowed()) {
        return new Reply(400, ['error' => 'Invalid file type']);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file->getType(), $allowedTypes)) {
        return new Reply(400, ['error' => 'Only images allowed']);
    }

    try {
        $file->upload('avatars/' . get_current_user_id());

        return new Reply(200, [
            'success' => true,
            'url' => $file->getUrl(),
        ]);
    } catch (FileHandlingError $e) {
        return new Reply(500, [
            'error' => 'Upload failed',
        ]);
    }
}
```

---

## Common Patterns

### Form Submission with Validation

```php
#[Nonce('contact_form')]
public function submitContact(Request $request): Reply {
    $action = $request->getAction();

    // Validate required fields
    $name = sanitize_text_field($action->get('name', ''));
    $email = sanitize_email($action->get('email', ''));
    $message = sanitize_textarea_field($action->get('message', ''));

    if (empty($name) || empty($email) || empty($message)) {
        return new Reply(400, [
            'success' => false,
            'error' => 'All fields are required',
        ]);
    }

    if (!is_email($email)) {
        return new Reply(400, [
            'success' => false,
            'error' => 'Invalid email address',
        ]);
    }

    // Process form...

    return new Reply(200, [
        'success' => true,
        'message' => 'Thank you for your message!',
    ]);
}
```

### Conditional Response Types

```php
public function handle(Request $request): Reply {
    $data = $this->getData();

    // Return JSON for AJAX/API requests
    if ($request->isAjax() || $request->hasHeader('Accept') === 'application/json') {
        return new Reply(200, $data);
    }

    // Return HTML for normal requests
    return new Reply(200, Views::render('Page', $data));
}
```

### File Upload with Multiple Files

```php
public function uploadImages(Request $request): Reply {
    $files = File::getAllFromCurrentRequest();
    $uploaded = [];
    $errors = [];

    foreach ($files as $file) {
        try {
            $file->upload('gallery');
            $uploaded[] = [
                'name' => $file->getName(),
                'url' => $file->getUrl(),
            ];
        } catch (FileHandlingError $e) {
            $errors[] = $file->getName() . ': ' . $e->getMessage();
        }
    }

    return new Reply(200, [
        'uploaded' => $uploaded,
        'errors' => $errors,
    ]);
}
```

---

## See Also

- [Core Classes](./core.md) - Fern, Config, Context, Singleton
- [Controllers](./controllers.md) - Controller implementation
- [Security Attributes](./attributes.md) - Nonce, RequireCapabilities, CacheReply
- [Frontend Integration](./frontend.md) - callAction usage
