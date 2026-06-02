<?php declare(strict_types=1);

namespace The_SEO_Framework\Front {
  if (!class_exists('The_SEO_Framework\Front\Title')) {
    class Title {
      public static string $title = '';

      public static function set_document_title(): string {
        return self::$title;
      }
    }
  }
}

namespace {
  use Fern\Core\Services\SEO\Integrations\TheSeoFramework;
  use Fern\Core\Services\SEO\SEOIntegration;
  use The_SEO_Framework\Front\Title as TsfTitle;

  class TsfFake {
    public string $emit = '';

    public function print_seo_meta_tags(): void {
      echo $this->emit;
    }
  }

  if (!function_exists('tsf')) {
    function tsf(): ?TsfFake {
      return $GLOBALS['__tsf_instance'] ?? null;
    }
  }

  describe('TheSeoFramework::getHelmet', function (): void {
    beforeEach(function (): void {
      TsfTitle::$title = '';
      $fake = new TsfFake();
      $fake->emit = '';
      $GLOBALS['__tsf_instance'] = $fake;
    });

    afterEach(function (): void {
      unset($GLOBALS['__tsf_instance']);
    });

    it('implements the SEOIntegration contract', function (): void {
      expect(is_subclass_of(TheSeoFramework::class, SEOIntegration::class))->toBeTrue();
    });

    it('wraps the document title in a title tag and appends the meta tags', function (): void {
      TsfTitle::$title = 'My Page Title';
      $GLOBALS['__tsf_instance']->emit = '<meta name="description" content="tsf">';

      expect(TheSeoFramework::getHelmet())
        ->toBe('<title>My Page Title</title><meta name="description" content="tsf">');
    });

    it('html-entity-decodes the document title', function (): void {
      TsfTitle::$title = 'Tom &amp; Jerry &#8211; Home';

      expect(TheSeoFramework::getHelmet())->toBe('<title>Tom & Jerry – Home</title>');
    });

    it('emits only the title tag when print_seo_meta_tags echoes nothing', function (): void {
      TsfTitle::$title = 'Solo';

      expect(TheSeoFramework::getHelmet())->toBe('<title>Solo</title>');
    });

    it('emits an empty title tag when the title is empty', function (): void {
      TsfTitle::$title = '';

      expect(TheSeoFramework::getHelmet())->toBe('<title></title>');
    });

    it('skips meta-tag printing when tsf() returns a non-object', function (): void {
      $GLOBALS['__tsf_instance'] = null;
      TsfTitle::$title = 'No Instance';

      expect(TheSeoFramework::getHelmet())->toBe('<title>No Instance</title>');
    });
  });
}
