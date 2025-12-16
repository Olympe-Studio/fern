# CLI Commands

> WP-CLI commands for Fern framework scaffolding

## Overview

Fern provides WP-CLI commands for generating boilerplate code. These commands are available when running WP-CLI in your WordPress installation.

## Prerequisites

- WP-CLI installed and configured
- Fern framework installed
- Access to WordPress installation via CLI

## Commands

### fern:controller create

Creates a new controller file with boilerplate code.

```bash
wp fern:controller create <name> <handle> [--subdir=<subdir>] [--create-page] [--light]
```

**Arguments:**

| Argument | Required | Description |
|----------|----------|-------------|
| `<name>` | Yes | Controller class name (e.g., `Product`, `Home`, `Contact`) |
| `<handle>` | Yes | Controller handle - page ID, post type, taxonomy, or `page` |

**Options:**

| Option | Description |
|--------|-------------|
| `--subdir=<subdir>` | Place controller in a subdirectory of `App/Controllers/` |
| `--create-page` | When handle is `page`, automatically create a WordPress page |
| `--light` | Use minimal template without example actions |

---

## Examples

### Create a Post Type Controller

```bash
wp fern:controller create Product product
```

Creates: `src/App/Controllers/ProductController.php`

```php
<?php
namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class ProductController extends Singleton implements Controller {
    public static string $handle = 'product';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Product', []));
    }

    public function sayHelloWorld(Request $request): Reply {
        $action = $request->getAction();
        $greeting = $action->get('greeting');
        return new Reply(200, "Hello, {$greeting}!");
    }

    #[RequireCapabilities(['manage_options'])]
    public function optionsManagerOnlyAction(Request $request): Reply {
        return new Reply(200, 'Hello, Options Manager!');
    }
}
```

### Create a Page Controller with New Page

```bash
wp fern:controller create Contact page --create-page
```

This will:
1. Create a new WordPress page titled "Contact"
2. Get the new page ID (e.g., `42`)
3. Create `src/App/Controllers/ContactController.php` with `$handle = '42'`

Output:
```
Success: Page 'Contact' created with ID: 42
Success: You can edit the page at : /wp-admin/post.php?post=42&action=edit
Success: Controller Contact created successfully in /path/to/App/Controllers/ContactController.php
```

### Create a Page Controller (Interactive)

```bash
wp fern:controller create About page
```

If `--create-page` is not specified, you'll be prompted:
```
Would you like to create a new page and assign its ID to the controller? [y/n]
```

### Create Controller in Subdirectory

```bash
wp fern:controller create Dashboard admin --subdir=Admin
```

Creates: `src/App/Controllers/Admin/DashboardController.php`

```php
<?php
namespace App\Controllers\Admin;
// ...
```

### Create a Light Controller

```bash
wp fern:controller create Simple post --light
```

Creates a minimal controller without example actions:

```php
<?php
namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class SimpleController extends Singleton implements Controller {
    public static string $handle = 'post';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Simple', []));
    }
}
```

### Create a Taxonomy Controller

```bash
wp fern:controller create ProductCategory product_cat
```

Creates controller for product category term pages.

### Create Archive Controller

```bash
wp fern:controller create ProductArchive archive_product
```

Creates controller for product archive page.

---

## Handle Types Reference

| Handle Value | Type | Description |
|--------------|------|-------------|
| `42` | Page ID | Specific WordPress page |
| `product` | Post Type | All posts of `product` type |
| `post` | Post Type | All blog posts |
| `page` | Post Type | All pages (or creates new page) |
| `product_cat` | Taxonomy | Product category terms |
| `category` | Taxonomy | Post category terms |
| `archive_product` | Archive | Product post type archive |
| `_default` | Special | Fallback controller |
| `_404` | Special | 404 error handler |

---

## Generated File Structure

### Full Template (Default)

```php
<?php
namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class NameController extends Singleton implements Controller {
    public static string $handle = 'handle';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Name', []));
    }

    // Example action
    public function sayHelloWorld(Request $request): Reply {
        $action = $request->getAction();
        $greeting = $action->get('greeting');
        return new Reply(200, "Hello, {$greeting}!");
    }

    // Example protected action
    #[RequireCapabilities(['manage_options'])]
    public function optionsManagerOnlyAction(Request $request): Reply {
        return new Reply(200, 'Hello, Options Manager!');
    }
}
```

### Light Template (`--light`)

```php
<?php
namespace App\Controllers;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class NameController extends Singleton implements Controller {
    public static string $handle = 'handle';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Name', []));
    }
}
```

---

## Common Workflows

### Creating a New Feature

```bash
# 1. Create the page and controller
wp fern:controller create ContactForm page --create-page

# 2. Create the Astro template
# resources/src/pages/ContactForm.astro

# 3. Add actions to the controller
# src/App/Controllers/ContactFormController.php
```

### Setting Up Admin Pages

```bash
# Create admin controllers in subdirectory
wp fern:controller create Settings my-settings --subdir=Admin
wp fern:controller create Reports my-reports --subdir=Admin
```

### Quick Prototyping

```bash
# Use light template for minimal boilerplate
wp fern:controller create Test product --light
```

---

## Troubleshooting

### Command Not Found

**Problem:** `Error: 'fern:controller' is not a registered wp command.`

**Solution:** Ensure Fern is properly loaded:
1. Check `fern-config.php` is included in theme's `functions.php`
2. Verify WP-CLI is running in correct directory
3. Run `wp cache flush` to clear WP-CLI cache

### Controller Already Exists

**Problem:** `Error: A controller named X already exists.`

**Solution:** Either:
- Choose a different name
- Delete existing controller first
- Use a subdirectory: `--subdir=NewDir`

### Template Not Found

**Problem:** `Error: Template file not found`

**Solution:** Verify Fern framework installation:
```bash
ls src/fern/src/CLI/templates/
# Should show: Controller.php, LightController.php
```

### Page Creation Failed

**Problem:** `Error: Failed to create the page: ...`

**Solution:** Check:
1. WordPress database connectivity
2. User has `publish_pages` capability
3. No duplicate page titles if enforced by plugins

---

## See Also

- [Controllers](./controllers.md) - Controller patterns and configuration
- [Views](./views.md) - Creating Astro templates
- [Attributes](./controllers.md#security-attributes) - Nonce and capability attributes
