<?php declare(strict_types=1);

namespace AIOSEO\Plugin\Common\Main {
  if (!class_exists('AIOSEO\Plugin\Common\Main\Head')) {
    class Head {
      public static string $emit = '';

      public function output(): void {
        echo self::$emit;
      }

      public function wpHead(): void {}
    }
  }
}

namespace {
  use AIOSEO\Plugin\Common\Main\Head as AioseoHead;
  use Brain\Monkey\Actions;
  use Fern\Core\Services\SEO\Integrations\AllInOne;
  use Fern\Core\Services\SEO\SEOIntegration;

  describe('AllInOne::getHelmet', function (): void {
    beforeEach(function (): void {
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
}
