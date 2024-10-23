<?php

declare(strict_types=1);

namespace Fern\Core\Services\Actions\Attributes;

use Fern\Core\Fern;
use Fern\Core\Services\Controller\AttributesHandler;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Utils\Cache;
use ReflectionAttribute;
use Throwable;

/**
 * Handler for Cache attribute
 */
class CacheHandler implements AttributesHandler {
  /**
   * @var string The prefix for all cache keys
   */
  private const CACHE_PREFIX = 'fern:action:cache:';

  /**
   * Handle the Cache attribute
   *
   * @param ReflectionAttribute $attribute  The attribute instance
   * @param object              $controller The controller instance
   * @param string              $methodName The method name
   * @param Request             $request    The current request
   *
   * @return bool|string Returns true if the attribute is valid, or an error message
   */
  public function handle(
      ReflectionAttribute $attribute,
      object $controller,
      string $methodName,
      Request $request,
  ): bool|string {
    $reflection = $attribute->newInstance();

    $ttl = $reflection->ttl;
    $key = $reflection->key;
    $varyBy = $reflection->varyBy;

    $cacheKey = $this->generateCacheKey($key, $controller, $varyBy, $request);
    $cachedResponse = Cache::get($cacheKey);

    // Hijack the reply
    if ($cachedResponse && !Fern::isDev()) {
      try {
        $reply = Reply::fromArray($cachedResponse);
        $reply->send();
      } catch (Throwable $e) {
        // If the cached response is not a valid reply, execute the action
        $reply = $controller->{$methodName}($request);
        Cache::set($cacheKey, $reply->toArray(), true, $ttl);
        $reply->send();
      }

      return true;
    }

    // Execute the action
    $reply = $controller->{$methodName}($request);
    Cache::set($cacheKey, $reply->toArray(), true, $ttl);
    $reply->send();

    return true;
  }

  /**
   * Generate a cache key
   */
  private function generateCacheKey(?string $key, object $controller, array $varyBy, Request $request): string {
    $action = $request->getAction();

    if (is_null($key)) {
      $key = $controller::class . ':' . $action->getName();
    }

    $cacheKey = self::CACHE_PREFIX . $key;

    foreach ($varyBy as $param) {
      $cacheKey .= ':' . $action->get($param);
    }

    return $cacheKey;
  }
}
