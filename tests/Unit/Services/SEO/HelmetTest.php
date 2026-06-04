<?php declare(strict_types=1);

use Fern\Core\Services\SEO\Helmet;
use Fern\Core\Services\SEO\Integrations\AllInOne;
use Fern\Core\Services\SEO\Integrations\Jetpack;
use Fern\Core\Services\SEO\Integrations\RankMath;
use Fern\Core\Services\SEO\Integrations\SeoPress;
use Fern\Core\Services\SEO\Integrations\Squirrly;
use Fern\Core\Services\SEO\Integrations\TheSeoFramework;
use Fern\Core\Services\SEO\Integrations\Yoast;
use Fern\Core\Services\SEO\SEOIntegration;

function helmetResolvePlugin(): string|false {
  $method = new ReflectionMethod(Helmet::class, 'resolvePlugin');

  return $method->invoke(null);
}

describe('Helmet::MAP', function (): void {
  it('maps every supported slug to its integration class', function (): void {
    expect(Helmet::MAP)->toBe([
      'yoast' => Yoast::class,
      'all-in-one' => AllInOne::class,
      'rank-math' => RankMath::class,
      'the-seo-framework' => TheSeoFramework::class,
      'seopress' => SeoPress::class,
      'squirrly' => Squirrly::class,
      'jetpack' => Jetpack::class,
    ]);
  });

  it('points every slug to a loadable SEOIntegration implementation', function (string $class): void {
    expect(class_exists($class))->toBeTrue()
      ->and(is_subclass_of($class, SEOIntegration::class))->toBeTrue();
  })->with(array_values(Helmet::MAP));
});

describe('Helmet::resolvePlugin (no plugin constants defined)', function (): void {
  it('returns false when no known SEO plugin is detected', function (): void {
    expect(helmetResolvePlugin())->toBeFalse();
  });

  /*
   * The constant-gated branches (WPSEO_VERSION, RANK_MATH_VERSION, etc.) are
   * each covered in their own process via PluginDetectionTest (define() leaks
   * across a shared process). The Jetpack branch is covered by
   * JetpackDetectionTest.
   */
});

describe('Helmet::getCurrent (no plugin detected)', function (): void {
  it('returns null when resolvePlugin finds no plugin', function (): void {
    expect(Helmet::getCurrent())->toBeNull();
  });

  it('is a Singleton subclass exposing static resolution helpers', function (): void {
    expect(is_subclass_of(Helmet::class, \Fern\Core\Factory\Singleton::class))->toBeTrue();
  });
});
