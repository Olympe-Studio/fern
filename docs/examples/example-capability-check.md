# Example: Capability Check

This example demonstrates using the `#[RequireCapabilities]` attribute to protect actions with WordPress user capabilities.

## Use Case

You have admin-only actions for managing users, content moderation, and settings that should only be accessible to users with specific capabilities.

## File Structure

```
src/App/Controllers/AdminUsersController.php
resources/src/pages/AdminUsers.astro
resources/src/components/UserManager.tsx
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

class AdminUsersController extends ViewController implements Controller {
    use AdminController;

    public static string $handle = 'manage-users';

    /**
     * Configure admin menu
     *
     * @return array<string, mixed> Menu configuration
     */
    public function configure(): array {
        return [
            'page_title' => 'User Management',
            'menu_title' => 'Manage Users',
            'capability' => 'list_users',
            'menu_slug' => 'manage-users',
            'icon_url' => 'dashicons-admin-users',
        ];
    }

    /**
     * Handle admin page
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $users = $this->getUsers();

        return new Reply(200, Views::render('AdminUsers', [
            'title' => 'User Management',
            'users' => $users,
            'nonces' => [
                'create_user' => wp_create_nonce('create_user'),
                'update_user' => wp_create_nonce('update_user'),
                'delete_user' => wp_create_nonce('delete_user'),
                'promote_user' => wp_create_nonce('promote_user'),
            ],
        ]));
    }

    /**
     * Create new user (requires create_users capability)
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with creation result
     */
    #[Nonce('create_user')]
    #[RequireCapabilities(['create_users'])]
    public function createUser(Request $request): Reply {
        $action = $request->getAction();

        $username = sanitize_user($action->get('username', ''));
        $email = sanitize_email($action->get('email', ''));
        $role = sanitize_key($action->get('role', 'subscriber'));

        if (empty($username) || empty($email)) {
            return new Reply(400, [
                'success' => false,
                'message' => 'Username and email are required',
            ]);
        }

        if (!is_email($email)) {
            return new Reply(400, [
                'success' => false,
                'message' => 'Invalid email address',
            ]);
        }

        $userId = wp_create_user($username, wp_generate_password(), $email);

        if (is_wp_error($userId)) {
            return new Reply(500, [
                'success' => false,
                'message' => $userId->get_error_message(),
            ]);
        }

        $user = new \WP_User($userId);
        $user->set_role($role);

        return new Reply(200, [
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userId,
        ]);
    }

    /**
     * Update user (requires edit_users capability)
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with update result
     */
    #[Nonce('update_user')]
    #[RequireCapabilities(['edit_users'])]
    public function updateUser(Request $request): Reply {
        $action = $request->getAction();
        $userId = absint($action->get('user_id', 0));

        if ($userId === 0) {
            return new Reply(400, [
                'success' => false,
                'message' => 'User ID is required',
            ]);
        }

        $updateData = [
            'ID' => $userId,
            'user_email' => sanitize_email($action->get('email', '')),
            'display_name' => sanitize_text_field($action->get('display_name', '')),
        ];

        $result = wp_update_user($updateData);

        if (is_wp_error($result)) {
            return new Reply(500, [
                'success' => false,
                'message' => $result->get_error_message(),
            ]);
        }

        return new Reply(200, [
            'success' => true,
            'message' => 'User updated successfully',
        ]);
    }

    /**
     * Delete user (requires delete_users capability)
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with deletion result
     */
    #[Nonce('delete_user')]
    #[RequireCapabilities(['delete_users'])]
    public function deleteUser(Request $request): Reply {
        $action = $request->getAction();
        $userId = absint($action->get('user_id', 0));

        if ($userId === 0) {
            return new Reply(400, [
                'success' => false,
                'message' => 'User ID is required',
            ]);
        }

        if ($userId === get_current_user_id()) {
            return new Reply(403, [
                'success' => false,
                'message' => 'You cannot delete yourself',
            ]);
        }

        $deleted = wp_delete_user($userId);

        if (!$deleted) {
            return new Reply(500, [
                'success' => false,
                'message' => 'Failed to delete user',
            ]);
        }

        return new Reply(200, [
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Promote user to editor (requires promote_users and edit_users)
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with promotion result
     */
    #[Nonce('promote_user')]
    #[RequireCapabilities(['promote_users', 'edit_users'])]
    public function promoteUser(Request $request): Reply {
        $action = $request->getAction();
        $userId = absint($action->get('user_id', 0));
        $newRole = sanitize_key($action->get('role', 'editor'));

        if ($userId === 0) {
            return new Reply(400, [
                'success' => false,
                'message' => 'User ID is required',
            ]);
        }

        $allowedRoles = ['subscriber', 'contributor', 'author', 'editor'];

        if (!in_array($newRole, $allowedRoles, true)) {
            return new Reply(400, [
                'success' => false,
                'message' => 'Invalid role',
            ]);
        }

        $user = new \WP_User($userId);
        $user->set_role($newRole);

        return new Reply(200, [
            'success' => true,
            'message' => "User promoted to {$newRole}",
        ]);
    }

    /**
     * Get all users
     *
     * @return array<int, array<string, mixed>> Users data
     */
    private function getUsers(): array {
        $users = get_users(['number' => 50]);

        return array_map(fn($user) => [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'role' => $user->roles[0] ?? 'subscriber',
        ], $users);
    }
}
```

## Frontend Component

```tsx
// resources/src/components/UserManager.tsx
import { createSignal, For } from 'solid-js';
import { callAction } from '@ferndev/core';

interface User {
  id: number;
  username: string;
  email: string;
  display_name: string;
  role: string;
}

interface UserManagerProps {
  initialUsers: User[];
  nonces: {
    create_user: string;
    update_user: string;
    delete_user: string;
    promote_user: string;
  };
}

export default function UserManager(props: UserManagerProps) {
  const [users, setUsers] = createSignal<User[]>(props.initialUsers);
  const [loading, setLoading] = createSignal(false);
  const [message, setMessage] = createSignal<string | null>(null);
  const [error, setError] = createSignal<string | null>(null);

  const deleteUser = async (userId: number) => {
    if (!confirm('Are you sure you want to delete this user?')) {
      return;
    }

    setLoading(true);
    setError(null);
    setMessage(null);

    const { data, error: actionError } = await callAction(
      'deleteUser',
      { user_id: userId },
      props.nonces.delete_user
    );

    setLoading(false);

    if (actionError) {
      setError('Failed to delete user. You may not have permission.');
      return;
    }

    if (data?.success) {
      setMessage(data.message);
      setUsers(users().filter(u => u.id !== userId));
    } else {
      setError(data?.message || 'Failed to delete user');
    }
  };

  const promoteUser = async (userId: number, newRole: string) => {
    setLoading(true);
    setError(null);
    setMessage(null);

    const { data, error: actionError } = await callAction(
      'promoteUser',
      { user_id: userId, role: newRole },
      props.nonces.promote_user
    );

    setLoading(false);

    if (actionError) {
      setError('Failed to promote user. You may not have permission.');
      return;
    }

    if (data?.success) {
      setMessage(data.message);
      // Update user in list
      setUsers(users().map(u =>
        u.id === userId ? { ...u, role: newRole } : u
      ));
    } else {
      setError(data?.message || 'Failed to promote user');
    }
  };

  return (
    <div class="user-manager">
      {message() && (
        <div class="notice notice-success">{message()}</div>
      )}

      {error() && (
        <div class="notice notice-error">{error()}</div>
      )}

      <table class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Display Name</th>
            <th>Role</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <For each={users()}>
            {(user) => (
              <tr>
                <td>{user.username}</td>
                <td>{user.email}</td>
                <td>{user.display_name}</td>
                <td>{user.role}</td>
                <td>
                  <select
                    onChange={(e) => promoteUser(user.id, e.currentTarget.value)}
                    value={user.role}
                    disabled={loading()}
                  >
                    <option value="subscriber">Subscriber</option>
                    <option value="contributor">Contributor</option>
                    <option value="author">Author</option>
                    <option value="editor">Editor</option>
                  </select>

                  <button
                    onClick={() => deleteUser(user.id)}
                    class="button button-small button-link-delete"
                    disabled={loading()}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            )}
          </For>
        </tbody>
      </table>
    </div>
  );
}
```

## WordPress Capabilities Reference

### Common Capabilities

```php
// Read content
'read' // Basic read access

// Posts
'edit_posts'
'publish_posts'
'delete_posts'

// Pages
'edit_pages'
'publish_pages'
'delete_pages'

// Users
'list_users'
'create_users'
'edit_users'
'delete_users'
'promote_users'

// Administration
'manage_options' // Settings access
'activate_plugins'
'edit_theme_options'
'manage_categories'

// Uploads
'upload_files'
```

### Role Capabilities

```php
// Subscriber: read
// Contributor: edit_posts, delete_posts
// Author: publish_posts, upload_files
// Editor: publish_pages, edit_others_posts
// Administrator: ALL capabilities
```

## Multiple Capabilities (AND Logic)

```php
// User MUST have ALL listed capabilities
#[RequireCapabilities(['edit_posts', 'publish_posts', 'upload_files'])]
public function createPost(Request $request): Reply {
    // Only executes if user has all three capabilities
}
```

## Custom Capabilities

You can create custom capabilities for your plugin/theme:

```php
// Add custom capability to a role
$role = get_role('editor');
$role->add_cap('manage_products');

// Use in attribute
#[RequireCapabilities(['manage_products'])]
public function updateProduct(Request $request): Reply {
    // ...
}
```

## Error Responses

When a user lacks required capabilities:

```json
{
  "success": false,
  "message": "You do not have permission to perform this action",
  "status": 403
}
```

The `#[RequireCapabilities]` attribute automatically returns a 403 Forbidden response.

## Best Practices

1. **Principle of Least Privilege**: Only require necessary capabilities
2. **Check Multiple Capabilities**: Use array for AND logic
3. **Custom Capabilities**: Create specific capabilities for your features
4. **Clear Error Messages**: Frontend should handle 403 errors gracefully
5. **Role-Based UI**: Hide UI elements users can't access
6. **Server-Side Only**: Never rely on client-side capability checks alone

## Frontend Capability Checking

Pass user capabilities to frontend for UI hiding:

```php
public function handle(Request $request): Reply {
    $currentUser = wp_get_current_user();

    return new Reply(200, Views::render('AdminUsers', [
        'users' => $this->getUsers(),
        'userCan' => [
            'create_users' => current_user_can('create_users'),
            'delete_users' => current_user_can('delete_users'),
            'promote_users' => current_user_can('promote_users'),
        ],
        'nonces' => [/* ... */],
    ]));
}
```

```tsx
// Hide delete button if user can't delete
{props.userCan.delete_users && (
  <button onClick={() => deleteUser(user.id)}>
    Delete
  </button>
)}
```

## Key Points

- `#[RequireCapabilities]` validates WordPress user capabilities
- Requires array of capability strings
- ALL capabilities must be satisfied (AND logic)
- Returns 403 Forbidden automatically if check fails
- Works with built-in and custom capabilities
- Combine with `#[Nonce]` for complete security
- Check capabilities on server, hide UI on client
- Use WordPress's built-in role and capability system
