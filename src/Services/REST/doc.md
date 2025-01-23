# REST API Service

Service to handle WordPress REST API endpoints with typed responses.

## Usage

```php
// ./App/API/_api.php

use Fern\Services\REST\API;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\HTTP\Reply;

// Use in a bare file, no hooks required.
API::config([
  'namespace' => 'my-namespace',
  'useFernReply' => true
]);

API::get('/endpoint', function(Request $request): Reply {
  return new Reply(200, [
    'success' => true,
    'data' => ['items' => []]
  ]);
});

// The above will register a GET endpoint at https://example.com/wp-json/my-namespace/v1/endpoint
// You can test it with `curl -X GET https://example.com/wp-json/my-namespace/v1/endpoint`
```

## API Methods

- `get(string $path, callable $callback, ?callable $permission)`
- `post(string $path, callable $callback, ?callable $permission)`
- `put(string $path, callable $callback, ?callable $permission)`
- `delete(string $path, callable $callback, ?callable $permission)`
- `patch(string $path, callable $callback, ?callable $permission)`

## Configuration

```php
array{
  namespace?: string,    // API namespace (default: 'fern')
  version?: string,      // API version (default: '1')
  useFernReply?: bool   // Use Fern Reply format (default: true)
}
```

## Response Format

When `useFernReply` is `true` (which is the default), responses are formatted as follows:
```json
{
  "success": true,
  "code": 200,
  "data": {}
}
```

And an error response:
```json
{
  "success": false,
  "code": 500,
  "message": "Error message"
}
```

When `useFernReply` is `false`, the response is a standard WordPress REST API response.