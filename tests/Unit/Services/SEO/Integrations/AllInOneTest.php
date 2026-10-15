<?php declare(strict_types=1);

use AIOSEO\Plugin\Common\Main\Head as AioseoHead;
use Brain\Monkey\Actions;
use Fern\Core\Services\SEO\Integrations\AllInOne;
use Fern\Core\Services\SEO\SEOIntegration;

/*
 * The AIOSEO Head double is loaded lazily (see doubles/aioseo-head.php) so the
 * absent-path assertion below runs while the class is still undefined. This
 * mirrors the Yoast/RankMath suites, whose doubles are likewise defined only
 * once the present-path tests opt in.
 */

it('returns an empty string when All in One SEO is not installed', function (): void {
  if (class_exists('AIOSEO\Plugin\Common\Main\Head')) {
    $this->markTestSkipped('AIOSEO Head double already loaded for this process.');
  }

  expect(AllInOne::getHelmet())->toBe('');
});

describe('AllInOne::getHelmet', function (): void {
  beforeEach(function (): void {
    require_once __DIR__ . '/doubles/aioseo-head.php';
    AioseoHead::$emit = '';
  });

  it('implements the SEOIntegration contract', function (): void {
    expect(is_subclass_of(AllInOne::class, SEOIntegration::class))->toBeTrue();
  });

  it('buffers the AIOSEO Head output', function (): void {
    AioseoHead::$emit = '<meta property="og:title" content="aioseo">';

    expect(AllInOne::getHelmet())->toBe('<meta property="og:title" content="aioseo">');
  });

  it('returns an empty string when the Head outputs nothing', function (): void {
    AioseoHead::$emit = '';

    expect(AllInOne::getHelmet())->toBe('');
  });

  it('detaches the AIOSEO wpHead action from wp_head', function (): void {
    AioseoHead::$emit = 'aio';

    Actions\expectRemoved('wp_head')->once();

    expect(AllInOne::getHelmet())->toBe('aio');
  });
});
