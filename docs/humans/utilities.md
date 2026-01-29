# Utilities

> Helper classes for caching and JSON operations

## Overview

Fern provides two utility classes: `Cache` for in-memory and persistent caching with expiration, and `JSON` for safe JSON encoding/decoding with modern PHP features.

---

## Cache

A two-level caching system with:
1. **In-memory cache** - Fast, request-scoped storage
2. **Persistent cache** - Survives across requests via WordPress options

### Quick Start

```php
use Fern\Core\Utils\Cache;

// Simple in-memory cache
Cache::set('user_prefs', $preferences);
$prefs = Cache::get('user_prefs');

// Persistent cache with expiration
Cache::set('api_data', $data, persist: true, expiration: 3600);

// Memoize expensive computations
$getStats = Cache::useMemo(
    fn() => $this->calculateExpensiveStats(),
    [$userId, $dateRange],
    expiration: 1800
);
$stats = $getStats();
```

### Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `DEFAULT_EXPIRATION` | `14400` | Default TTL: 4 hours (in seconds) |
| `PERSISTENT_CACHE_OPTION` | `'fern:core:persistent_cache'` | WordPress option name |

### Methods

#### `Cache::get(string $key): mixed`

Retrieves a value from cache.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$key` | `string` | Yes | Cache key |

**Returns:** `mixed` - Cached value or `null` if not found/expired

**Example:**
```php
$userData = Cache::get('user_123');
if ($userData === null) {
    $userData = $this->fetchUserData(123);
    Cache::set('user_123', $userData);
}
```

#### `Cache::set(string $key, mixed $value, bool $persist = false, int $expiration = 14400): void`

Stores a value in cache.

**Parameters:**
| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `$key` | `string` | Yes | - | Cache key |
| `$value` | `mixed` | Yes | - | Value to cache |
| `$persist` | `bool` | No | `false` | Save to persistent cache |
| `$expiration` | `int` | No | `14400` | TTL in seconds |

**Example:**
```php
// In-memory only (request-scoped)
Cache::set('temp_data', $data);

// Persistent cache (4 hours default)
Cache::set('api_result', $result, persist: true);

// Persistent with custom expiration (1 hour)
Cache::set('short_lived', $data, persist: true, expiration: 3600);

// Persistent with long expiration (24 hours)
Cache::set('long_lived', $data, persist: true, expiration: 86400);
```

#### `Cache::useMemo(callable $callback, array $dependencies = [], int $expiration = 14400, bool $persist = false): callable`

Memoizes a callback based on dependencies (similar to React's `useMemo`).

**Parameters:**
| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `$callback` | `callable` | Yes | - | Function to memoize |
| `$dependencies` | `array<mixed>` | No | `[]` | Values that trigger recalculation when changed |
| `$expiration` | `int` | No | `14400` | TTL in seconds |
| `$persist` | `bool` | No | `false` | Save to persistent cache |

**Returns:** `callable` - Memoized function

**Throws:** `InvalidArgumentException` if dependencies are not serializable

**Example:**
```php
// Memoize based on user ID
$getProfile = Cache::useMemo(
    fn(int $userId) => $this->fetchUserProfile($userId),
    [$userId] // Recalculate when userId changes
);
$profile = $getProfile($userId);

// Memoize with multiple dependencies
$getReport = Cache::useMemo(
    fn() => $this->generateReport($startDate, $endDate, $category),
    [$startDate, $endDate, $category],
    expiration: 1800, // 30 minutes
    persist: true
);
$report = $getReport();

// Memoize database query
$getProducts = Cache::useMemo(
    function() {
        return Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
        ]);
    },
    [wp_cache_get_last_changed('posts')], // Invalidate when posts change
    persist: true
);
$products = $getProducts();
```

#### `Cache::flush(): void`

Clears all caches (in-memory and persistent).

**Example:**
```php
// Clear all cached data
Cache::flush();

// Typical use: after data updates
update_option('site_settings', $newSettings);
Cache::flush();
```

#### `Cache::save(): void`

Saves persistent cache to WordPress options. Called automatically on shutdown.

**Example:**
```php
// Usually not needed, but can force save
Cache::save();
```

### Cache Behavior

```
Request 1:
├─ Cache::set('key', $value)           → In-memory only
├─ Cache::set('key', $value, true)     → In-memory + persistent
└─ [shutdown]                          → Persistent cache saved to DB

Request 2:
├─ Cache::get('key')                   → Checks in-memory, then persistent
├─ Expired items                       → Automatically removed
└─ [shutdown]                          → Dirty cache saved to DB
```

### Common Patterns

#### Caching API Responses

```php
$apiData = Cache::get('external_api_data');

if ($apiData === null) {
    $apiData = $this->fetchFromExternalAPI();
    Cache::set('external_api_data', $apiData, persist: true, expiration: 3600);
}

return $apiData;
```

#### Caching Computed Values

```php
// Cache key includes all parameters
$cacheKey = "product_stats_{$productId}_{$dateRange}";
$stats = Cache::get($cacheKey);

if ($stats === null) {
    $stats = $this->calculateProductStats($productId, $dateRange);
    Cache::set($cacheKey, $stats, persist: true, expiration: 1800);
}

return $stats;
```

#### Request-Scoped Cache

```php
// Avoid re-fetching within same request
$currentUser = Cache::get('current_user');

if ($currentUser === null) {
    $currentUser = wp_get_current_user();
    Cache::set('current_user', $currentUser); // In-memory only
}

return $currentUser;
```

---

## JSON

Type-safe JSON utilities with error handling.

### Quick Start

```php
use Fern\Core\Utils\JSON;

// Encode
$json = JSON::encode(['name' => 'Product', 'price' => 99.99]);
// '{"name":"Product","price":99.99}'

// Decode
$data = JSON::decode('{"name":"Product"}', associative: true);
// ['name' => 'Product']

// Validate
if (JSON::validate($input)) {
    $data = JSON::decode($input);
}

// Pretty print
$formatted = JSON::pretty(['key' => 'value']);
// {
//     "key": "value"
// }
```

### Methods

#### `JSON::encode(mixed $data, ?int $flags = null): string|false`

Encodes data to JSON string.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$data` | `mixed` | Yes | Data to encode |
| `$flags` | `int\|null` | No | JSON flags (defaults to unicode-safe settings) |

**Returns:** `string|false` - JSON string or `false` on failure

**Default Flags:** `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`

**Example:**
```php
// Basic encoding
$json = JSON::encode(['hello' => 'world']);
// '{"hello":"world"}'

// With special characters (preserved)
$json = JSON::encode(['text' => 'Héllo Wörld']);
// '{"text":"Héllo Wörld"}'

// Arrays
$json = JSON::encode([1, 2, 3]);
// '[1,2,3]'

// Custom flags
$json = JSON::encode($data, JSON_PRETTY_PRINT);
```

#### `JSON::decode(string $json, bool $associative = false, int $depth = 512, int $flags = 0): mixed`

Decodes JSON string to PHP data.

**Parameters:**
| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `$json` | `string` | Yes | - | JSON string |
| `$associative` | `bool` | No | `false` | Return arrays instead of objects |
| `$depth` | `int` | No | `512` | Max nesting depth |
| `$flags` | `int` | No | `0` | JSON flags |

**Returns:** `mixed` - Decoded value or `null` on error

**Example:**
```php
// Decode to object
$obj = JSON::decode('{"name":"John"}');
echo $obj->name; // "John"

// Decode to array
$arr = JSON::decode('{"name":"John"}', associative: true);
echo $arr['name']; // "John"

// Handle errors
$data = JSON::decode($input);
if ($data === null && $input !== 'null') {
    // Invalid JSON
}
```

#### `JSON::decodeToArray(string $json, int $depth = 512, int $flags = 0): array`

Decodes JSON to array, throws on non-array result.

**Parameters:**
| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `$json` | `string` | Yes | - | JSON string |
| `$depth` | `int` | No | `512` | Max nesting depth |
| `$flags` | `int` | No | `0` | JSON flags |

**Returns:** `array<mixed>` - Decoded array

**Throws:** `JsonException` if empty, invalid, or not an array

**Example:**
```php
try {
    $items = JSON::decodeToArray('[1, 2, 3]');
    // [1, 2, 3]
} catch (JsonException $e) {
    // Handle error
}

// Guaranteed to be array
$config = JSON::decodeToArray(file_get_contents('config.json'));
```

#### `JSON::validate(string $json, int $depth = 512, int $flags = 0): bool`

Validates JSON string without decoding.

**Parameters:**
| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `$json` | `string` | Yes | - | JSON string to validate |
| `$depth` | `int` | No | `512` | Max nesting depth |
| `$flags` | `int` | No | `0` | Only `JSON_INVALID_UTF8_IGNORE` is valid |

**Returns:** `bool` - `true` if valid JSON

**Example:**
```php
// Validate before decoding
if (!JSON::validate($userInput)) {
    return new Reply(400, ['error' => 'Invalid JSON']);
}

$data = JSON::decode($userInput);

// Quick validation
$isValid = JSON::validate('{"key": "value"}'); // true
$isValid = JSON::validate('{invalid}');         // false
$isValid = JSON::validate('');                  // false
```

**Note:** Uses native `json_validate()` in PHP 8.3+, falls back to `json_decode()` otherwise.

#### `JSON::pretty(mixed $data): string|false`

Encodes data with pretty printing.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `$data` | `mixed` | Yes | Data to encode |

**Returns:** `string|false` - Formatted JSON string

**Example:**
```php
$formatted = JSON::pretty([
    'name' => 'Product',
    'price' => 99.99,
    'tags' => ['sale', 'featured'],
]);

// {
//     "name": "Product",
//     "price": 99.99,
//     "tags": [
//         "sale",
//         "featured"
//     ]
// }
```

### Common Patterns

#### Safe API Response Handling

```php
$response = wp_remote_get('https://api.example.com/data');
$body = wp_remote_retrieve_body($response);

if (!JSON::validate($body)) {
    throw new \RuntimeException('Invalid API response');
}

$data = JSON::decode($body, associative: true);
```

#### Writing JSON Files

```php
$config = [
    'version' => '1.0',
    'settings' => $settings,
];

$json = JSON::pretty($config);
file_put_contents($path, $json);
```

#### Reading JSON Files

```php
$content = file_get_contents($path);

if ($content === false) {
    throw new \RuntimeException('Cannot read file');
}

try {
    $config = JSON::decodeToArray($content);
} catch (JsonException $e) {
    throw new \RuntimeException('Invalid config file: ' . $e->getMessage());
}
```

---

## Best Practices

### Cache Keys

```php
// Good: Descriptive and unique
Cache::set("user_profile_{$userId}", $profile);
Cache::set("product_list_{$category}_{$page}", $products);
Cache::set("api_response_" . md5($apiEndpoint), $response);

// Avoid: Generic keys
Cache::set('data', $data);
Cache::set('result', $result);
```

### Expiration Times

```php
// Short-lived (frequent updates)
Cache::set('live_data', $data, true, 60);        // 1 minute

// Medium (API responses)
Cache::set('api_data', $data, true, 3600);       // 1 hour

// Long-lived (static data)
Cache::set('static_data', $data, true, 86400);   // 24 hours

// Default (4 hours)
Cache::set('standard_data', $data, true);
```

### Error Handling

```php
// Always validate external JSON
$input = $_POST['json_data'] ?? '';

if (!JSON::validate($input)) {
    return new Reply(400, ['error' => 'Invalid JSON data']);
}

try {
    $data = JSON::decodeToArray($input);
} catch (JsonException $e) {
    error_log('JSON decode error: ' . $e->getMessage());
    return new Reply(400, ['error' => 'Malformed data']);
}
```

---

## See Also

- [Core Classes](./core.md) - Singleton pattern used by Cache
- [HTTP Layer](./http.md) - Reply uses JSON encoding
- [WordPress Integration](./wordpress.md) - Cache uses WordPress options
