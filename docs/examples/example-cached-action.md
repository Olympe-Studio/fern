# Example: Cached Action

This example demonstrates using the `#[CacheReply]` attribute to cache expensive action responses.

## Use Case

You have an analytics dashboard that makes expensive database queries. Cache the results to improve performance.

## File Structure

```
src/App/Controllers/DashboardController.php
resources/src/pages/Dashboard.astro
resources/src/components/Analytics.tsx
```

## Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Actions\Attributes\CacheReply;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Actions\Attributes\RequireCapabilities;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class DashboardController extends ViewController implements Controller {
    public static string $handle = 'dashboard';

    /**
     * Handle dashboard page
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Dashboard', [
            'title' => 'Analytics Dashboard',
            'nonces' => [
                'get_stats' => wp_create_nonce('get_stats'),
                'get_user_stats' => wp_create_nonce('get_user_stats'),
            ],
        ]));
    }

    /**
     * Get overall statistics (cached for 1 hour)
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with statistics
     */
    #[Nonce('get_stats')]
    #[RequireCapabilities(['read'])]
    #[CacheReply(ttl: 3600)]
    public function getStats(Request $request): Reply {
        $stats = $this->calculateStats();

        return new Reply(200, [
            'success' => true,
            'data' => $stats,
            'cached_at' => time(),
        ]);
    }

    /**
     * Get user-specific statistics (cached for 30 minutes, varies by user)
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with user statistics
     */
    #[Nonce('get_user_stats')]
    #[RequireCapabilities(['read'])]
    #[CacheReply(ttl: 1800, varyBy: ['user_id'])]
    public function getUserStats(Request $request): Reply {
        $action = $request->getAction();
        $userId = absint($action->get('user_id', 0));

        if ($userId === 0) {
            return new Reply(400, [
                'success' => false,
                'message' => 'User ID is required',
            ]);
        }

        $stats = $this->calculateUserStats($userId);

        return new Reply(200, [
            'success' => true,
            'data' => $stats,
            'user_id' => $userId,
        ]);
    }

    /**
     * Calculate overall statistics (expensive operation)
     *
     * @return array<string, mixed> Statistics data
     */
    private function calculateStats(): array {
        global $wpdb;

        $totalPosts = wp_count_posts('post');
        $totalPages = wp_count_posts('page');
        $totalUsers = count_users();

        $recentPosts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return [
            'total_posts' => $totalPosts->publish ?? 0,
            'total_pages' => $totalPages->publish ?? 0,
            'total_users' => $totalUsers['total_users'] ?? 0,
            'recent_posts' => (int) $recentPosts,
            'popular_posts' => $this->getPopularPosts(),
        ];
    }

    /**
     * Get popular posts
     *
     * @return array<int, array<string, mixed>> Popular posts
     */
    private function getPopularPosts(): array {
        $posts = Timber::get_posts([
            'post_type' => 'post',
            'posts_per_page' => 5,
            'orderby' => 'comment_count',
            'order' => 'DESC',
        ]);

        if (!$posts) {
            return [];
        }

        return array_map(fn($post) => [
            'id' => $post->ID,
            'title' => $post->title(),
            'url' => $post->link(),
            'comments' => $post->comment_count,
        ], $posts->to_array());
    }

    /**
     * Calculate user-specific statistics
     *
     * @param int $userId User ID
     *
     * @return array<string, mixed> User statistics
     */
    private function calculateUserStats(int $userId): array {
        global $wpdb;

        $userPosts = count_user_posts($userId);

        $userComments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d",
            $userId
        ));

        $lastLogin = get_user_meta($userId, 'last_login', true);

        return [
            'user_id' => $userId,
            'total_posts' => (int) $userPosts,
            'total_comments' => (int) $userComments,
            'last_login' => $lastLogin ?: null,
            'recent_activity' => $this->getUserRecentActivity($userId),
        ];
    }

    /**
     * Get user recent activity
     *
     * @param int $userId User ID
     *
     * @return array<int, array<string, mixed>> Recent activity
     */
    private function getUserRecentActivity(int $userId): array {
        $posts = Timber::get_posts([
            'author' => $userId,
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!$posts) {
            return [];
        }

        return array_map(fn($post) => [
            'title' => $post->title(),
            'date' => $post->date('F j, Y'),
            'url' => $post->link(),
        ], $posts->to_array());
    }
}
```

## SolidJS Component

```tsx
// resources/src/components/Analytics.tsx
import { createSignal, onMount } from 'solid-js';
import { callAction } from '@ferndev/core';

interface AnalyticsProps {
  statsNonce: string;
  userStatsNonce: string;
}

interface Stats {
  total_posts: number;
  total_pages: number;
  total_users: number;
  recent_posts: number;
  popular_posts: Array<{
    id: number;
    title: string;
    url: string;
    comments: number;
  }>;
}

export default function Analytics(props: AnalyticsProps) {
  const [stats, setStats] = createSignal<Stats | null>(null);
  const [loading, setLoading] = createSignal(true);
  const [error, setError] = createSignal<string | null>(null);
  const [cachedAt, setCachedAt] = createSignal<number | null>(null);

  const loadStats = async () => {
    setLoading(true);
    setError(null);

    const { data, error: actionError } = await callAction(
      'getStats',
      {},
      props.statsNonce
    );

    setLoading(false);

    if (actionError) {
      setError('Failed to load statistics');
      return;
    }

    if (data?.success) {
      setStats(data.data);
      setCachedAt(data.cached_at);
    }
  };

  const refreshStats = async () => {
    // Force refresh by calling action again
    // Cache will serve stale data until TTL expires
    await loadStats();
  };

  onMount(() => {
    loadStats();
  });

  return (
    <div class="analytics-dashboard">
      {loading() && <p>Loading statistics...</p>}

      {error() && (
        <div class="error-message">{error()}</div>
      )}

      {stats() && (
        <>
          <div class="stats-header">
            <h2>Analytics Overview</h2>
            <button onClick={refreshStats} class="refresh-btn">
              Refresh
            </button>
            {cachedAt() && (
              <small class="cache-info">
                Cached at: {new Date(cachedAt()! * 1000).toLocaleTimeString()}
              </small>
            )}
          </div>

          <div class="stats-grid">
            <div class="stat-card">
              <h3>Total Posts</h3>
              <p class="stat-value">{stats()!.total_posts}</p>
            </div>

            <div class="stat-card">
              <h3>Total Pages</h3>
              <p class="stat-value">{stats()!.total_pages}</p>
            </div>

            <div class="stat-card">
              <h3>Total Users</h3>
              <p class="stat-value">{stats()!.total_users}</p>
            </div>

            <div class="stat-card">
              <h3>Posts (Last 30 Days)</h3>
              <p class="stat-value">{stats()!.recent_posts}</p>
            </div>
          </div>

          <div class="popular-posts">
            <h3>Popular Posts</h3>
            <ul>
              {stats()!.popular_posts.map(post => (
                <li>
                  <a href={post.url}>{post.title}</a>
                  <span class="comment-count">
                    {post.comments} comments
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </>
      )}
    </div>
  );
}
```

## User-Specific Analytics Component

```tsx
// resources/src/components/UserAnalytics.tsx
import { createSignal, createEffect } from 'solid-js';
import { callAction } from '@ferndev/core';

interface UserAnalyticsProps {
  userId: number;
  nonce: string;
}

export default function UserAnalytics(props: UserAnalyticsProps) {
  const [stats, setStats] = createSignal(null);
  const [loading, setLoading] = createSignal(false);

  createEffect(async () => {
    // Cache will vary by user_id parameter
    setLoading(true);

    const { data } = await callAction(
      'getUserStats',
      { user_id: props.userId },
      props.nonce
    );

    setLoading(false);

    if (data?.success) {
      setStats(data.data);
    }
  });

  return (
    <div class="user-analytics">
      {loading() && <p>Loading user statistics...</p>}

      {stats() && (
        <div class="user-stats">
          <h3>Your Statistics</h3>
          <p>Total Posts: {stats()!.total_posts}</p>
          <p>Total Comments: {stats()!.total_comments}</p>
          {/* ... */}
        </div>
      )}
    </div>
  );
}
```

## Cache Configuration

### Basic Cache (Fixed TTL)

```php
#[CacheReply(ttl: 3600)] // Cache for 1 hour
public function myAction(Request $request): Reply {
    // ...
}
```

### Cache with Custom Key

```php
#[CacheReply(ttl: 1800, key: 'my_custom_key')]
public function myAction(Request $request): Reply {
    // Cache stored with specific key
}
```

### Cache Varying by Parameters

```php
// Cache varies by user_id and date parameters
#[CacheReply(ttl: 900, varyBy: ['user_id', 'date'])]
public function getUserData(Request $request): Reply {
    $action = $request->getAction();
    $userId = $action->get('user_id');
    $date = $action->get('date');

    // Different cache for each user_id/date combination
}
```

## Manual Cache Invalidation

If you need to manually clear cached action responses:

```php
use Fern\Core\Utils\Cache;

// Clear specific cache key
Cache::delete('action_cache_key');

// Clear all caches
Cache::flush();
```

## Cache Key Generation

Cache keys are auto-generated based on:
1. Action name
2. Controller class
3. `varyBy` parameters (if specified)

Example generated key: `fern_action_cache_DashboardController_getUserStats_user_id_42`

## Performance Considerations

1. **Choose Appropriate TTL**: Balance freshness vs performance
2. **VaryBy Wisely**: Only vary by necessary parameters
3. **Monitor Cache Size**: Too many variations can bloat cache
4. **Clear on Update**: Invalidate cache when underlying data changes

## Best Practices

1. **Cache Expensive Operations**: Database queries, API calls, calculations
2. **Short TTL for Dynamic Data**: User-specific, frequently changing data
3. **Long TTL for Static Data**: Configuration, rarely changing content
4. **Document Cache Behavior**: Note TTL in comments
5. **Test Cache Invalidation**: Ensure caches clear when data updates
6. **Monitor Performance**: Measure impact of caching

## Key Points

- `#[CacheReply]` caches the entire Reply object
- TTL is in seconds
- `varyBy` creates separate caches for parameter combinations
- Caches are stored using WordPress transients by default
- Combine with `#[Nonce]` and `#[RequireCapabilities]` for security
- Cache validation happens before action execution
- Cached responses bypass action execution entirely
