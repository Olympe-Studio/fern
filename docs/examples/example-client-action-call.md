# Example: Client Action Call

This example demonstrates making AJAX requests from the frontend to backend actions using `callAction` from `@ferndev/core`.

## Use Case

You have a "Like" button that updates a post's like count without page reload.

## File Structure

```
src/App/Controllers/PostController.php
src/App/Services/LikeService.php
resources/src/pages/Post.astro
resources/src/components/LikeButton.tsx
```

## Service Implementation

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Fern\Core\Factory\Singleton;

class LikeService extends Singleton {
    /**
     * Get like count for post
     *
     * @param int $postId Post ID
     *
     * @return int Like count
     */
    public function getLikeCount(int $postId): int {
        return (int) get_post_meta($postId, '_like_count', true);
    }

    /**
     * Check if user has liked post
     *
     * @param int      $postId Post ID
     * @param int|null $userId User ID (null = current user)
     *
     * @return bool True if user has liked
     */
    public function hasUserLiked(int $postId, ?int $userId = null): bool {
        $userId = $userId ?? get_current_user_id();

        if ($userId === 0) {
            return false;
        }

        $likes = get_post_meta($postId, '_user_likes', true) ?: [];

        return in_array($userId, $likes, true);
    }

    /**
     * Toggle like for user
     *
     * @param int $postId Post ID
     * @param int $userId User ID
     *
     * @return array<string, mixed> Result with liked status and count
     */
    public function toggleLike(int $postId, int $userId): array {
        if ($this->hasUserLiked($postId, $userId)) {
            $this->removeLike($postId, $userId);
            $liked = false;
        } else {
            $this->addLike($postId, $userId);
            $liked = true;
        }

        return [
            'liked' => $liked,
            'count' => $this->getLikeCount($postId),
        ];
    }

    /**
     * Add like from user
     *
     * @param int $postId Post ID
     * @param int $userId User ID
     */
    private function addLike(int $postId, int $userId): void {
        $likes = get_post_meta($postId, '_user_likes', true) ?: [];
        $likes[] = $userId;
        $likes = array_unique($likes);

        update_post_meta($postId, '_user_likes', $likes);
        update_post_meta($postId, '_like_count', count($likes));
    }

    /**
     * Remove like from user
     *
     * @param int $postId Post ID
     * @param int $userId User ID
     */
    private function removeLike(int $postId, int $userId): void {
        $likes = get_post_meta($postId, '_user_likes', true) ?: [];
        $likes = array_filter($likes, fn($id) => $id !== $userId);

        update_post_meta($postId, '_user_likes', $likes);
        update_post_meta($postId, '_like_count', count($likes));
    }

    /**
     * Check if post exists
     *
     * @param int $postId Post ID
     *
     * @return bool True if valid
     */
    public function isValidPost(int $postId): bool {
        return get_post_status($postId) !== false;
    }
}
```

## Controller with Action

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class PostController extends ViewController implements Controller {
    public static string $handle = 'post';

    private LikeService $likeService;

    public function __construct() {
        $this->likeService = LikeService::getInstance();
    }

    /**
     * Handle single post page
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $post = Timber::get_post();

        return new Reply(200, Views::render('Post', [
            'title' => $post->title(),
            'content' => $post->content(),
            'postId' => $post->ID,
            'likes' => $this->likeService->getLikeCount($post->ID),
            'userHasLiked' => $this->likeService->hasUserLiked($post->ID),
            'nonces' => [
                'like_post' => wp_create_nonce('like_post'),
            ],
        ]));
    }

    /**
     * Handle like post action
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with like result
     */
    #[Nonce('like_post')]
    public function likePost(Request $request): Reply {
        $action = $request->getAction();
        $postId = absint($action->get('postId', 0));

        if ($postId === 0) {
            return new Reply(400, [
                'success' => false,
                'message' => 'Invalid post ID',
            ]);
        }

        if (!$this->likeService->isValidPost($postId)) {
            return new Reply(404, [
                'success' => false,
                'message' => 'Post not found',
            ]);
        }

        $userId = get_current_user_id();
        $result = $this->likeService->toggleLike($postId, $userId);

        return new Reply(200, [
            'success' => true,
            'liked' => $result['liked'],
            'count' => $result['count'],
        ]);
    }
}
```

## SolidJS Component

```tsx
// resources/src/components/LikeButton.tsx
import { createSignal } from 'solid-js';
import { callAction } from '@ferndev/core';

interface LikeButtonProps {
  postId: number;
  initialLikes: number;
  initialLiked: boolean;
  nonce: string;
}

export default function LikeButton(props: LikeButtonProps) {
  const [likes, setLikes] = createSignal(props.initialLikes);
  const [liked, setLiked] = createSignal(props.initialLiked);
  const [loading, setLoading] = createSignal(false);
  const [error, setError] = createSignal<string | null>(null);

  const handleLike = async () => {
    setLoading(true);
    setError(null);

    const { data, error: actionError } = await callAction(
      'likePost',
      { postId: props.postId },
      props.nonce
    );

    setLoading(false);

    if (actionError) {
      setError('Failed to update like. Please try again.');
      console.error(actionError);
      return;
    }

    if (data?.success) {
      setLiked(data.liked);
      setLikes(data.count);
    } else {
      setError(data?.message || 'An error occurred');
    }
  };

  return (
    <div class="like-button-container">
      <button
        class={`like-button ${liked() ? 'liked' : ''}`}
        onClick={handleLike}
        disabled={loading()}
        aria-label={liked() ? 'Unlike post' : 'Like post'}
      >
        <svg
          class="heart-icon"
          fill={liked() ? 'currentColor' : 'none'}
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
        <span class="like-count">{likes()}</span>
      </button>

      {error() && (
        <p class="error-message">{error()}</p>
      )}
    </div>
  );
}
```

## Astro Template

```astro
---
// resources/src/pages/Post.astro
import LikeButton from '../components/LikeButton';

interface Props {
  title: string;
  content: string;
  postId: number;
  likes: number;
  userHasLiked: boolean;
  nonces: {
    like_post: string;
  };
}

const { title, content, postId, likes, userHasLiked, nonces } = Astro.props;
---

<Layout title={title}>
  <article>
    <h1>{title}</h1>
    <div set:html={content} />

    <div class="post-actions">
      <LikeButton
        client:load
        postId={postId}
        initialLikes={likes}
        initialLiked={userHasLiked}
        nonce={nonces.like_post}
      />
    </div>
  </article>
</Layout>
```

## Advanced: Form Data Upload

For file uploads, use FormData:

```tsx
// Component with file upload
import { callAction } from '@ferndev/core';

async function uploadAvatar(file: File, nonce: string) {
  const formData = new FormData();
  formData.append('avatar', file);
  // FormData automatically includes action and nonce

  const { data, error } = await callAction('uploadAvatar', formData, nonce);

  if (error) {
    console.error('Upload failed:', error);
    return;
  }

  console.log('Avatar uploaded:', data);
}
```

## Controller for File Upload

```php
#[Nonce('upload_avatar')]
#[RequireCapabilities(['upload_files'])]
public function uploadAvatar(Request $request): Reply {
    $files = $request->getFiles();

    if (!isset($files['avatar'])) {
        return new Reply(400, [
            'success' => false,
            'message' => 'No file provided',
        ]);
    }

    $file = $files['avatar'];

    // Handle WordPress file upload
    $uploadedFile = wp_handle_upload($file, ['test_form' => false]);

    if (isset($uploadedFile['error'])) {
        return new Reply(500, [
            'success' => false,
            'message' => $uploadedFile['error'],
        ]);
    }

    return new Reply(200, [
        'success' => true,
        'url' => $uploadedFile['url'],
    ]);
}
```

## Error Handling

```typescript
const { data, error, status } = await callAction('myAction', args, nonce);

if (status === 'error') {
  // Network or parsing error
  console.error('Error:', error?.message);
  console.error('Status:', error?.status);
}

if (status === 'ok' && !data?.success) {
  // Application error (action returned success: false)
  console.error('Application error:', data?.message);
}

if (status === 'ok' && data?.success) {
  // Success!
  console.log('Data:', data);
}
```

## TypeScript Types

```typescript
// Define action response type
interface LikeResponse {
  success: boolean;
  liked?: boolean;
  count?: number;
  message?: string;
}

// Use with callAction
const { data } = await callAction<LikeResponse>(
  'likePost',
  { postId: 123 },
  nonce
);

if (data?.success && data.liked !== undefined) {
  // TypeScript knows data.liked exists
  setLiked(data.liked);
}
```

## Best Practices

1. **Always Use Nonces**: Protect all state-changing actions
2. **Loading States**: Show loading indicator during request
3. **Error Handling**: Display user-friendly error messages
4. **Optimistic Updates**: Update UI immediately, rollback on error
5. **Type Safety**: Use TypeScript interfaces for responses
6. **Validation**: Validate on both client and server
7. **HTTP Status Codes**: Return appropriate codes (400, 404, 500)
8. **User Feedback**: Show success/error messages

## Key Points

- **Separation of Concerns**: Controller delegates business logic to `LikeService`
- **Thin Controllers**: Controller only handles HTTP concerns (request/response)
- **Service Layer**: Business logic lives in dedicated service classes
- **Reusability**: Service methods can be used by multiple controllers
- `callAction` sends POST requests with `X-Fern-Action` header
- Nonce is sent as `_nonce` in request body
- Actions can return any JSON-serializable data
- FormData is supported for file uploads
- Errors are caught and returned in `error` property
- Works from any client-side component (React, Solid, Vue, etc.)

## Architecture Benefits

**Controller Responsibilities:**
- Validate HTTP request parameters
- Check authentication/authorization
- Call service methods
- Format HTTP responses

**Service Responsibilities:**
- Business logic (toggle like, add/remove)
- Data validation (post exists)
- Data persistence (update meta)
- Data retrieval (get likes count)
