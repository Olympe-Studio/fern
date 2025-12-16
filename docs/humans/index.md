# Fern Framework Documentation

> A modern PHP framework for WordPress with Astro/SolidJS frontend integration

## Overview

Fern is a WordPress framework that provides:
- **MVC Architecture** - Controllers, Views, and Actions
- **Type Safety** - Full PHP 8+ type hints and PHPDoc
- **Modern Frontend** - Astro/SolidJS with TypeScript
- **Security** - Built-in nonce validation and capability checks
- **Performance** - Caching, lazy loading, and optimized rendering

## Quick Start

```php
// fern-config.php
Fern::defineConfig([
    'root' => __DIR__,
    'rendering_engine' => new \App\Services\Astro\Astro(),
]);
```

```php
// src/App/Controllers/HomeController.php
class HomeController extends ViewController implements Controller {
    public static string $handle = '2';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Home', [
            'title' => 'Welcome',
        ]));
    }
}
```

## Documentation

### Core Framework

| Document | Description |
|----------|-------------|
| [Core Classes](./core.md) | Fern, Config, Context, Singleton |
| [HTTP Layer](./http.md) | Request, Reply, Action, File |
| [Controllers](./controllers.md) | Route handling, actions, attributes |
| [Views](./views.md) | Template rendering |

### Integration

| Document | Description |
|----------|-------------|
| [WordPress](./wordpress.md) | Events, Filters, hooks |
| [Frontend](./frontend.md) | @ferndev/core, @ferndev/woo |
| [Utilities](./utilities.md) | Cache, JSON |

### Tools

| Document | Description |
|----------|-------------|
| [CLI Commands](./cli.md) | WP-CLI scaffolding commands |

## Architecture

```
Request Flow:
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   Request    │ -> │   Router     │ -> │  Controller  │
└──────────────┘    └──────────────┘    └──────────────┘
                                               │
                                               v
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│    Reply     │ <- │    Views     │ <- │    Data      │
└──────────────┘    └──────────────┘    └──────────────┘
```

## Key Concepts

### Controllers
Handle requests and return responses. Match by page ID, post type, or taxonomy.

### Actions
Public controller methods callable from frontend via `callAction()`.

### Views
Render templates (Astro) with data from controllers.

### Events & Filters
WordPress hooks with cleaner API via `Events::on()` and `Filters::apply()`.

## File Structure

```
src/
├── App/
│   ├── Controllers/      # Route handlers
│   ├── Actions/          # Shared action traits
│   ├── Models/           # Data models
│   └── Services/         # Business logic
├── fern/                 # Framework core
└── fern-config.php       # Configuration

resources/
├── src/
│   ├── pages/            # Astro templates
│   ├── components/       # Reusable components
│   └── layouts/          # Page layouts
└── tailwind.config.js    # Styling
```

## Examples

See the [examples directory](../examples/) for complete working examples:
- [Basic Controller](../examples/example-basic-controller.md)
- [Action with Nonce](../examples/example-action-with-nonce.md)
