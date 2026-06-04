<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Fern\Core\Services\SEO\Integrations\Yoast;
use Fern\Core\Services\SEO\SEOIntegration;

describe('Yoast::getHelmet', function (): void {
  it('implements the SEOIntegration contract', function (): void {
    expect(is_subclass_of(Yoast::class, SEOIntegration::class))->toBeTrue();
  });

  it('returns an empty string when wpseo_head is not available', function (): void {
    if (function_exists('wpseo_head')) {
      $this->markTestSkipped('wpseo_head already defined for this process.');
    }

    expect(Yoast::getHelmet())->toBe('');
  });

  it('buffers the wpseo_head action output and clears its handlers', function (): void {
    if (!function_exists('wpseo_head')) {
      function wpseo_head(): void {}
    }

    Functions\when('remove_all_actions')->justReturn(true);

    Actions\expectDone('wpseo_head')->once()->whenHappen(function (): void {
      echo '<meta name="description" content="from yoast">';
    });

    expect(Yoast::getHelmet())->toBe('<meta name="description" content="from yoast">');
  });

  it('returns an empty string when the action echoes nothing', function (): void {
    if (!function_exists('wpseo_head')) {
      function wpseo_head(): void {}
    }

    Functions\when('remove_all_actions')->justReturn(true);

    Actions\expectDone('wpseo_head')->once();

    expect(Yoast::getHelmet())->toBe('');
  });

  it('removes all wpseo_head handlers after buffering', function (): void {
    if (!function_exists('wpseo_head')) {
      function wpseo_head(): void {}
    }

    Functions\expect('remove_all_actions')->once()->with('wpseo_head');

    Actions\expectDone('wpseo_head')->once()->whenHappen(function (): void {
      echo 'head';
    });

    expect(Yoast::getHelmet())->toBe('head');
  });
});
