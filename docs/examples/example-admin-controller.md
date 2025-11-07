# Example: Admin Controller

This example demonstrates creating a WordPress admin settings page with the `AdminController` trait.

## Use Case

You need a custom settings page in the WordPress admin to manage plugin/theme options.

## File Structure

```
src/App/Controllers/SettingsController.php
resources/src/pages/AdminSettings.astro
```

## Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class SettingsController extends ViewController implements Controller {
    use AdminController;

    public static string $handle = 'my-plugin-settings';

    /**
     * Configure the admin menu page
     *
     * @return array<string, mixed> Admin menu configuration
     */
    public function configure(): array {
        return [
            'page_title' => 'My Plugin Settings',
            'menu_title' => 'Plugin Settings',
            'capability' => 'manage_options',
            'menu_slug' => 'my-plugin-settings',
            'icon_url' => 'dashicons-admin-settings',
            'position' => 99,
        ];
    }

    /**
     * Handle the admin settings page request
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $settings = $this->getSettings();

        return new Reply(200, Views::render('AdminSettings', [
            'title' => 'Plugin Settings',
            'settings' => $settings,
            'nonces' => [
                'save_settings' => wp_create_nonce('save_settings'),
            ],
        ]));
    }

    /**
     * Save settings action
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with save result
     */
    #[Nonce('save_settings')]
    #[RequireCapabilities(['manage_options'])]
    public function saveSettings(Request $request): Reply {
        $action = $request->getAction();
        $settings = $action->get('settings', []);

        if (!is_array($settings)) {
            return new Reply(400, [
                'success' => false,
                'message' => 'Invalid settings format',
            ]);
        }

        $sanitized = $this->sanitizeSettings($settings);
        update_option('my_plugin_settings', $sanitized);

        return new Reply(200, [
            'success' => true,
            'message' => 'Settings saved successfully',
            'settings' => $sanitized,
        ]);
    }

    /**
     * Get current settings from database
     *
     * @return array<string, mixed> Current settings
     */
    private function getSettings(): array {
        $defaults = [
            'api_key' => '',
            'enable_feature' => false,
            'items_per_page' => 10,
            'color_scheme' => 'light',
        ];

        $saved = get_option('my_plugin_settings', []);

        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    /**
     * Sanitize settings array
     *
     * @param array<string, mixed> $settings Raw settings from request
     *
     * @return array<string, mixed> Sanitized settings
     */
    private function sanitizeSettings(array $settings): array {
        return [
            'api_key' => sanitize_text_field($settings['api_key'] ?? ''),
            'enable_feature' => (bool) ($settings['enable_feature'] ?? false),
            'items_per_page' => absint($settings['items_per_page'] ?? 10),
            'color_scheme' => in_array($settings['color_scheme'] ?? '', ['light', 'dark'])
                ? $settings['color_scheme']
                : 'light',
        ];
    }
}
```

## Frontend Component (SolidJS)

```tsx
// resources/src/components/SettingsForm.tsx
import { createSignal } from 'solid-js';
import { callAction } from '@ferndev/core';

interface Settings {
  api_key: string;
  enable_feature: boolean;
  items_per_page: number;
  color_scheme: 'light' | 'dark';
}

interface SettingsFormProps {
  initialSettings: Settings;
  nonce: string;
}

export default function SettingsForm(props: SettingsFormProps) {
  const [settings, setSettings] = createSignal<Settings>(props.initialSettings);
  const [saving, setSaving] = createSignal(false);
  const [message, setMessage] = createSignal<string | null>(null);
  const [error, setError] = createSignal<string | null>(null);

  const updateSetting = (key: keyof Settings, value: any) => {
    setSettings({ ...settings(), [key]: value });
  };

  const handleSave = async () => {
    setSaving(true);
    setMessage(null);
    setError(null);

    const { data, error: actionError } = await callAction(
      'saveSettings',
      { settings: settings() },
      props.nonce
    );

    setSaving(false);

    if (actionError) {
      setError(actionError.message);
      return;
    }

    if (data?.success) {
      setMessage(data.message);
      setSettings(data.settings);
    } else {
      setError(data?.message || 'Failed to save settings');
    }
  };

  return (
    <div class="settings-form">
      {message() && (
        <div class="notice notice-success">
          <p>{message()}</p>
        </div>
      )}

      {error() && (
        <div class="notice notice-error">
          <p>{error()}</p>
        </div>
      )}

      <table class="form-table">
        <tbody>
          <tr>
            <th scope="row">
              <label for="api_key">API Key</label>
            </th>
            <td>
              <input
                id="api_key"
                type="text"
                class="regular-text"
                value={settings().api_key}
                onInput={(e) => updateSetting('api_key', e.currentTarget.value)}
              />
              <p class="description">Enter your API key for external services</p>
            </td>
          </tr>

          <tr>
            <th scope="row">Enable Feature</th>
            <td>
              <label>
                <input
                  type="checkbox"
                  checked={settings().enable_feature}
                  onChange={(e) => updateSetting('enable_feature', e.currentTarget.checked)}
                />
                Enable advanced features
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="items_per_page">Items Per Page</label>
            </th>
            <td>
              <input
                id="items_per_page"
                type="number"
                class="small-text"
                min="1"
                max="100"
                value={settings().items_per_page}
                onInput={(e) => updateSetting('items_per_page', parseInt(e.currentTarget.value))}
              />
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="color_scheme">Color Scheme</label>
            </th>
            <td>
              <select
                id="color_scheme"
                value={settings().color_scheme}
                onChange={(e) => updateSetting('color_scheme', e.currentTarget.value)}
              >
                <option value="light">Light</option>
                <option value="dark">Dark</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>

      <p class="submit">
        <button
          type="button"
          class="button button-primary"
          onClick={handleSave}
          disabled={saving()}
        >
          {saving() ? 'Saving...' : 'Save Settings'}
        </button>
      </p>
    </div>
  );
}
```

## Frontend Template (Astro)

```astro
---
// resources/src/pages/AdminSettings.astro
import SettingsForm from '../components/SettingsForm';

interface Props {
  title: string;
  settings: {
    api_key: string;
    enable_feature: boolean;
    items_per_page: number;
    color_scheme: 'light' | 'dark';
  };
  nonces: {
    save_settings: string;
  };
}

const { title, settings, nonces } = Astro.props;
---

<div class="wrap">
  <h1>{title}</h1>

  <SettingsForm
    client:load
    initialSettings={settings}
    nonce={nonces.save_settings}
  />
</div>

<style>
  .wrap {
    margin: 20px 20px 0 0;
  }
</style>
```

## How It Works

1. **Admin Menu Registration**: The `configure()` method defines the admin menu item
2. **Auto-Registration**: Fern automatically calls `add_menu_page()` with your configuration
3. **Page Rendering**: When the admin page is accessed, `handle()` is called
4. **Settings Display**: Current settings are loaded and passed to the view
5. **Save Action**: The form calls `saveSettings` action which validates and saves
6. **Capability Check**: `#[RequireCapabilities(['manage_options'])]` ensures only admins can save
7. **Nonce Protection**: `#[Nonce('save_settings')]` prevents CSRF attacks

## Admin Menu Configuration Options

```php
public function configure(): array {
    return [
        // Required
        'page_title' => 'Page Title',       // Browser title
        'menu_title' => 'Menu Title',       // Menu item label
        'capability' => 'manage_options',   // Required capability
        'menu_slug' => 'my-page',           // URL slug

        // Optional
        'icon_url' => 'dashicons-admin-generic', // Menu icon
        'position' => 99,                   // Menu position
    ];
}
```

## Submenu Page

To add a submenu page instead:

```php
public function configure(): array {
    return [
        'parent_slug' => 'options-general.php', // Parent menu slug
        'page_title' => 'My Settings',
        'menu_title' => 'My Settings',
        'capability' => 'manage_options',
        'menu_slug' => 'my-settings',
    ];
}
```

Common parent slugs from wordpress code:
- `options-general.php` - Settings
- `tools.php` - Tools
- `edit.php?post_type=page` - Pages
- `themes.php` - Appearance
- `plugins.php` - Plugins

## Security Best Practices

1. **Capability Checks**: Always require appropriate capabilities
2. **Nonce Validation**: Use `#[Nonce]` for all save actions
3. **Input Sanitization**: Sanitize all user input
4. **Validation**: Validate data types and allowed values
5. **Escape Output**: WordPress admin pages automatically escape, but be careful with custom HTML

## Key Points

- `AdminController` trait is required for admin pages
- `configure()` method returns admin menu configuration
- `$handle` must match the menu slug
- Actions work the same as regular controllers
- Settings are typically stored in WordPress options table
- Use WordPress admin CSS classes for consistent styling
