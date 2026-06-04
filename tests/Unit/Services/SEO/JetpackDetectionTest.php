<?php declare(strict_types=1);

use Fern\Core\Services\SEO\Helmet;

if (!class_exists('Jetpack')) {
  class Jetpack {
    public static bool $seoToolsActive = false;

    public static function is_module_active(string $module): bool {
      return self::$seoToolsActive;
    }
  }
}

beforeEach(function (): void {
  Jetpack::$seoToolsActive = false;
});

afterEach(function (): void {
  Jetpack::$seoToolsActive = false;
});

function resolveSeoPlugin(): string|false {
  return (new ReflectionMethod(Helmet::class, 'resolvePlugin'))->invoke(null);
}

it('detects Jetpack SEO tools when the module is active', function (): void {
  Jetpack::$seoToolsActive = true;

  expect(resolveSeoPlugin())->toBe('jetpack');
});

it('does not detect Jetpack when the SEO module is inactive', function (): void {
  Jetpack::$seoToolsActive = false;

  expect(resolveSeoPlugin())->toBeFalse();
});

it('dispatches getCurrent to the Jetpack integration when active', function (): void {
  Jetpack::$seoToolsActive = true;

  expect(Helmet::getCurrent())
    ->toBe(\Fern\Core\Services\SEO\Integrations\Jetpack::getHelmet());
});
