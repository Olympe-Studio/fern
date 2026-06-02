<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Fern\Core\Services\SEO\Integrations\RankMath;
use Fern\Core\Services\SEO\SEOIntegration;

describe('RankMath::getHelmet', function (): void {
  it('implements the SEOIntegration contract', function (): void {
    expect(is_subclass_of(RankMath::class, SEOIntegration::class))->toBeTrue();
  });

  it('returns an empty string when rank_math_head is not available', function (): void {
    if (function_exists('rank_math_head')) {
      $this->markTestSkipped('rank_math_head already defined for this process.');
    }

    expect(RankMath::getHelmet())->toBe('');
  });

  it('buffers the rank_math/head action output', function (): void {
    if (!function_exists('rank_math_head')) {
      function rank_math_head(): void {}
    }

    Functions\when('remove_all_actions')->justReturn(true);

    Actions\expectDone('rank_math/head')->once()->whenHappen(function (): void {
      echo '<title>Rank Math</title>';
    });

    expect(RankMath::getHelmet())->toBe('<title>Rank Math</title>');
  });

  it('returns an empty string when the action echoes nothing', function (): void {
    if (!function_exists('rank_math_head')) {
      function rank_math_head(): void {}
    }

    Functions\when('remove_all_actions')->justReturn(true);

    Actions\expectDone('rank_math/head')->once();

    expect(RankMath::getHelmet())->toBe('');
  });

  it('clears the rank_math/head handlers after buffering', function (): void {
    if (!function_exists('rank_math_head')) {
      function rank_math_head(): void {}
    }

    Functions\expect('remove_all_actions')->once()->with('rank_math/head');

    Actions\expectDone('rank_math/head')->once()->whenHappen(function (): void {
      echo 'rm';
    });

    expect(RankMath::getHelmet())->toBe('rm');
  });
});
