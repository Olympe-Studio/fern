# Example: Model Transformer

This example demonstrates creating a Model class for data transformation, following the single responsibility principle.

## Use Case

You need to transform WordPress posts into a consistent, typed data structure for frontend consumption.

## File Structure

```
src/App/Models/Post.php
src/App/Models/Author.php
src/App/Controllers/BlogController.php
```

## Post Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Timber\Post as TimberPost;

class Post {
    /**
     * Transform multiple Timber posts to arrays
     *
     * @param array<TimberPost> $posts Timber posts
     *
     * @return array<int, array<string, mixed>> Transformed posts
     */
    public static function toArray(array $posts): array {
        return array_map(fn(TimberPost $post) => self::transform($post), $posts);
    }

    /**
     * Transform a single Timber post to array
     *
     * @param TimberPost $post Timber post
     *
     * @return array<string, mixed> Transformed post data
     */
    public static function transform(TimberPost $post): array {
        return [
            'id' => $post->ID,
            'title' => $post->title(),
            'excerpt' => $post->preview(40),
            'content' => $post->content(),
            'url' => $post->link(),
            'date' => self::formatDate($post),
            'author' => Author::transform($post->author()),
            'thumbnail' => self::getThumbnail($post),
            'categories' => self::getCategories($post),
            'tags' => self::getTags($post),
            'readingTime' => self::calculateReadingTime($post),
        ];
    }

    /**
     * Get formatted post date
     *
     * @param TimberPost $post Timber post
     *
     * @return array<string, string> Date information
     */
    private static function formatDate(TimberPost $post): array {
        return [
            'published' => $post->date('F j, Y'),
            'iso' => $post->date('c'),
            'timestamp' => $post->timestamp(),
        ];
    }

    /**
     * Get post thumbnail data
     *
     * @param TimberPost $post Timber post
     *
     * @return array<string, string>|null Thumbnail data
     */
    private static function getThumbnail(TimberPost $post): ?array {
        $thumbnail = $post->thumbnail();

        if (!$thumbnail) {
            return null;
        }

        return [
            'url' => $thumbnail->src(),
            'alt' => $thumbnail->alt(),
            'width' => $thumbnail->width(),
            'height' => $thumbnail->height(),
        ];
    }

    /**
     * Get post categories
     *
     * @param TimberPost $post Timber post
     *
     * @return array<int, array<string, mixed>> Categories
     */
    private static function getCategories(TimberPost $post): array {
        $terms = $post->terms('category');

        if (empty($terms)) {
            return [];
        }

        return array_map(fn($term) => [
            'id' => $term->id,
            'name' => $term->name,
            'slug' => $term->slug,
            'url' => $term->link(),
        ], $terms);
    }

    /**
     * Get post tags
     *
     * @param TimberPost $post Timber post
     *
     * @return array<int, array<string, string>> Tags
     */
    private static function getTags(TimberPost $post): array {
        $terms = $post->terms('post_tag');

        if (empty($terms)) {
            return [];
        }

        return array_map(fn($term) => [
            'name' => $term->name,
            'slug' => $term->slug,
            'url' => $term->link(),
        ], $terms);
    }

    /**
     * Calculate estimated reading time
     *
     * @param TimberPost $post Timber post
     *
     * @return int Reading time in minutes
     */
    private static function calculateReadingTime(TimberPost $post): int {
        $wordCount = str_word_count(strip_tags($post->content()));
        $wordsPerMinute = 200;

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }
}
```

## Author Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Timber\User;

class Author {
    /**
     * Transform Timber user to author array
     *
     * @param User|null $user Timber user
     *
     * @return array<string, mixed>|null Author data
     */
    public static function transform(?User $user): ?array {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->ID,
            'name' => $user->name(),
            'url' => $user->link(),
            'avatar' => self::getAvatar($user),
            'bio' => get_user_meta($user->ID, 'description', true),
            'socialLinks' => self::getSocialLinks($user),
        ];
    }

    /**
     * Get author avatar data
     *
     * @param User $user Timber user
     *
     * @return array<string, mixed> Avatar data
     */
    private static function getAvatar(User $user): array {
        $avatarUrl = get_avatar_url($user->ID, ['size' => 96]);

        return [
            'url' => $avatarUrl ?: '',
            'size' => 96,
        ];
    }

    /**
     * Get author social links
     *
     * @param User $user Timber user
     *
     * @return array<string, string> Social media links
     */
    private static function getSocialLinks(User $user): array {
        return [
            'twitter' => get_user_meta($user->ID, 'twitter', true) ?: '',
            'linkedin' => get_user_meta($user->ID, 'linkedin', true) ?: '',
            'github' => get_user_meta($user->ID, 'github', true) ?: '',
        ];
    }
}
```

## Usage in Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class BlogController extends ViewController implements Controller {
    public static string $handle = 'archive_post';

    /**
     * Handle the blog archive page request
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $posts = Timber::get_posts([
            'post_type' => 'post',
            'posts_per_page' => 10,
        ]);

        $postsArray = $posts ? $posts->to_array() : [];

        return new Reply(200, Views::render('BlogArchive', [
            'title' => 'Blog',
            'posts' => Post::toArray($postsArray),
        ]));
    }
}
```

## Single Post Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class SinglePostController extends ViewController implements Controller {
    public static string $handle = 'post';

    /**
     * Handle single post view
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $post = Timber::get_post();

        if (!$post) {
            return new Reply(404, 'Post not found');
        }

        return new Reply(200, Views::render('SinglePost', [
            'post' => Post::transform($post),
            'relatedPosts' => $this->getRelatedPosts($post),
        ]));
    }

    /**
     * Get related posts based on categories
     *
     * @param \Timber\Post $post Current post
     *
     * @return array<int, array<string, mixed>> Related posts
     */
    private function getRelatedPosts(\Timber\Post $post): array {
        $categories = $post->terms('category');

        if (empty($categories)) {
            return [];
        }

        $categoryIds = array_map(fn($cat) => $cat->id, $categories);

        $relatedPosts = Timber::get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
            'post__not_in' => [$post->ID],
            'category__in' => $categoryIds,
        ]);

        $postsArray = $relatedPosts ? $relatedPosts->to_array() : [];

        return Post::toArray($postsArray);
    }
}
```

## Frontend Template

```astro
---
// resources/src/pages/SinglePost.astro
interface Props {
  post: {
    id: number;
    title: string;
    content: string;
    excerpt: string;
    url: string;
    date: {
      published: string;
      iso: string;
      timestamp: number;
    };
    author: {
      id: number;
      name: string;
      url: string;
      avatar: { url: string; size: number };
      bio: string;
    } | null;
    thumbnail: {
      url: string;
      alt: string;
      width: number;
      height: number;
    } | null;
    categories: Array<{ id: number; name: string; slug: string; url: string }>;
    tags: Array<{ name: string; slug: string; url: string }>;
    readingTime: number;
  };
  relatedPosts: Array<any>;
}

const { post, relatedPosts } = Astro.props;
---

<Layout title={post.title}>
  <article class="single-post">
    <header class="post-header">
      {post.thumbnail && (
        <img
          src={post.thumbnail.url}
          alt={post.thumbnail.alt}
          class="post-thumbnail"
        />
      )}

      <h1>{post.title}</h1>

      <div class="post-meta">
        {post.author && (
          <div class="author-info">
            <img src={post.author.avatar.url} alt={post.author.name} />
            <a href={post.author.url}>{post.author.name}</a>
          </div>
        )}

        <time datetime={post.date.iso}>{post.date.published}</time>
        <span>{post.readingTime} min read</span>
      </div>

      {post.categories.length > 0 && (
        <div class="categories">
          {post.categories.map(cat => (
            <a href={cat.url} class="category-badge">
              {cat.name}
            </a>
          ))}
        </div>
      )}
    </header>

    <div class="post-content" set:html={post.content} />

    {post.tags.length > 0 && (
      <footer class="post-footer">
        <div class="tags">
          {post.tags.map(tag => (
            <a href={tag.url} class="tag">#{tag.name}</a>
          ))}
        </div>
      </footer>
    )}
  </article>

  {relatedPosts.length > 0 && (
    <section class="related-posts">
      <h2>Related Posts</h2>
      <div class="posts-grid">
        {relatedPosts.map(relatedPost => (
          <article class="post-card">
            <a href={relatedPost.url}>
              <h3>{relatedPost.title}</h3>
              <p>{relatedPost.excerpt}</p>
            </a>
          </article>
        ))}
      </div>
    </section>
  )}
</Layout>
```

## Advanced: Cached Transformations

For expensive transformations, add caching:

```php
use Fern\Core\Utils\Cache;

class Post {
    public static function transform(TimberPost $post): array {
        $cacheKey = "post_transform_{$post->ID}";

        return Cache::get($cacheKey, function() use ($post) {
            return [
                'id' => $post->ID,
                // ... rest of transformation
            ];
        }, 3600); // Cache for 1 hour
    }
}
```

## Best Practices

1. **Static Methods**: Models use static methods for transformation
2. **Type Hints**: Always specify input/output types
3. **Null Safety**: Handle null values gracefully
4. **Single Responsibility**: Each method does one thing
5. **Reusable**: Models can be used across multiple controllers
6. **Consistent Structure**: All transformed data has the same shape
7. **Frontend-Friendly**: Output structure matches frontend needs
8. **Performance**: Consider caching for expensive operations

## Key Points

- Models are transformers, not active records
- Static methods for pure transformations
- Nested transformations (Author within Post)
- Defensive programming (check for null, empty arrays)
- Clear separation: Controller fetches, Model transforms, View renders
- Type safety throughout the chain
- Follows KISS and single responsibility principles
