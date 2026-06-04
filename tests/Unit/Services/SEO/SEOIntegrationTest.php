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

describe('SEOIntegration contract', function (): void {
  it('declares a static getHelmet returning a string', function (): void {
    $method = new ReflectionMethod(SEOIntegration::class, 'getHelmet');

    expect($method->isStatic())->toBeTrue()
      ->and($method->getNumberOfParameters())->toBe(0)
      ->and((string) $method->getReturnType())->toBe('string');
  });

  it('is implemented by every shipped integration', function (string $class): void {
    expect(is_subclass_of($class, SEOIntegration::class))->toBeTrue()
      ->and((new ReflectionMethod($class, 'getHelmet'))->getReturnType()?->__toString())->toBe('string');
  })->with([
    'Yoast' => [Yoast::class],
    'RankMath' => [RankMath::class],
    'AllInOne' => [AllInOne::class],
    'TheSeoFramework' => [TheSeoFramework::class],
    'SeoPress' => [SeoPress::class],
    'Squirrly' => [Squirrly::class],
    'Jetpack' => [Jetpack::class],
  ]);
});

describe('adapter selection (no plugin detected)', function (): void {
  it('falls back to null through Helmet when nothing is installed', function (): void {
    expect(Helmet::getCurrent())->toBeNull();
  });

  it('keeps the never-supported adapters returning a string even when dispatched directly', function (string $class): void {
    expect($class::getHelmet())->toBeString();
  })->with([
    'SeoPress' => [SeoPress::class],
    'Squirrly' => [Squirrly::class],
    'Jetpack' => [Jetpack::class],
  ]);
});
