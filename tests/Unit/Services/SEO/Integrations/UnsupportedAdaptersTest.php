<?php declare(strict_types=1);

use Fern\Core\Services\SEO\Integrations\Jetpack;
use Fern\Core\Services\SEO\Integrations\SeoPress;
use Fern\Core\Services\SEO\Integrations\Squirrly;
use Fern\Core\Services\SEO\SEOIntegration;

describe('not-supported SEO adapters', function (): void {
  it('returns a static unsupported comment without touching WordPress', function (string $class, string $expected): void {
    expect($class::getHelmet())->toBe($expected);
  })->with([
    'SeoPress' => [
      SeoPress::class,
      '<!-- SEOPress is not supported because it doesn\'t provide a PHP API -->',
    ],
    'Squirrly' => [
      Squirrly::class,
      '<!-- Squirrly is not supported because it doesn\'t provide a PHP API -->',
    ],
    'Jetpack' => [
      Jetpack::class,
      '<!-- Jetpack is not supported because it doesn\'t provide a PHP API -->',
    ],
  ]);

  it('implements the SEOIntegration contract', function (string $class): void {
    expect(is_subclass_of($class, SEOIntegration::class))->toBeTrue();
  })->with([
    'SeoPress' => [SeoPress::class],
    'Squirrly' => [Squirrly::class],
    'Jetpack' => [Jetpack::class],
  ]);

  it('is idempotent across repeated calls', function (string $class): void {
    expect($class::getHelmet())->toBe($class::getHelmet());
  })->with([
    'SeoPress' => [SeoPress::class],
    'Squirrly' => [Squirrly::class],
    'Jetpack' => [Jetpack::class],
  ]);
});
