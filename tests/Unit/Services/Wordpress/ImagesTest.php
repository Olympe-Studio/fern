<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Services\Wordpress\Images;

function makeImages(array $config): Images {
  return new Images($config);
}

describe('construction and validation', function (): void {
  it('always registers the jpeg_quality filter', function (): void {
    Filters\expectAdded('jpeg_quality')->once();

    makeImages([]);
  });

  it('throws when disabled is not a boolean', function (): void {
    expect(static function (): void {
      makeImages(['disabled' => 'yes']);
    })->toThrow(InvalidArgumentException::class, "The 'disabled' option must be a boolean.");
  });

  it('throws when a boolean setting is not a boolean', function (): void {
    expect(static function (): void {
      makeImages(['settings' => ['disable_image_sizes' => 'yes']]);
    })->toThrow(InvalidArgumentException::class, "The 'disable_image_sizes' option must be a boolean.");
  });

  it('throws when jpeg_quality is out of range', function (mixed $quality): void {
    expect(static function () use ($quality): void {
      makeImages(['settings' => ['jpeg_quality' => $quality]]);
    })->toThrow(InvalidArgumentException::class, 'JPEG quality must be an integer between 0 and 100.');
  })->with([
    'too high' => [101],
    'negative' => [-1],
    'not int' => ['80'],
  ]);

  it('throws when a custom size is missing width or height', function (): void {
    expect(static function (): void {
      makeImages(['settings' => ['custom_sizes' => ['hero' => ['width' => 100]]]]);
    })->toThrow(InvalidArgumentException::class, "Invalid custom size configuration for 'hero'");
  });

  it('throws when a custom size crop is not a boolean', function (): void {
    expect(static function (): void {
      makeImages(['settings' => ['custom_sizes' => ['hero' => ['width' => 1, 'height' => 1, 'crop' => 'x']]]]);
    })->toThrow(InvalidArgumentException::class, "The 'crop' option for 'hero' must be a boolean.");
  });
});

describe('boot', function (): void {
  it('reads core.images from Config and constructs the service', function (): void {
    Config::getInstance()->setConfig(['core' => ['images' => []]]);

    Filters\expectAdded('jpeg_quality')->once();

    Images::boot();
  });

  it('falls back to an empty config when core.images is not an array', function (): void {
    Config::getInstance()->setConfig(['core' => ['images' => 'nope']]);

    Filters\expectAdded('jpeg_quality')->once();

    Images::boot();
  });
});

describe('fully disabled processing', function (): void {
  it('registers the full set of disabling hooks when disabled is true', function (): void {
    Filters\expectAdded('intermediate_image_sizes_advanced')->twice();
    Filters\expectAdded('big_image_size_threshold')->once();
    Actions\expectAdded('init')->once();
    Filters\expectAdded('wp_image_editors')->once();
    Filters\expectAdded('jpeg_quality')->once();
    Filters\expectAdded('max_srcset_image_width')->once();
    Filters\expectAdded('wp_generate_attachment_metadata')->once()->with(Mockery::any(), 10, 1);

    makeImages(['disabled' => true]);
  });
});

describe('selective settings', function (): void {
  it('registers size-disabling hooks when disable_image_sizes is on', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Filters\expectAdded('intermediate_image_sizes_advanced')->once();
    Filters\expectAdded('big_image_size_threshold')->once();

    makeImages(['settings' => ['disable_image_sizes' => true]]);
  });

  it('hooks init when disable_other_image_sizes is on', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Actions\expectAdded('init')->once();

    makeImages(['settings' => ['disable_other_image_sizes' => true]]);
  });

  it('hooks wp_image_editors when disable_image_editing is on', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Filters\expectAdded('wp_image_editors')->once();

    makeImages(['settings' => ['disable_image_editing' => true]]);
  });

  it('hooks intermediate_image_sizes_advanced when remove_default_image_sizes is on', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Filters\expectAdded('intermediate_image_sizes_advanced')->once();

    makeImages(['settings' => ['remove_default_image_sizes' => true]]);
  });

  it('hooks max_srcset_image_width when disable_responsive_images is on', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Filters\expectAdded('max_srcset_image_width')->once();

    makeImages(['settings' => ['disable_responsive_images' => true]]);
  });

  it('hooks wp_generate_attachment_metadata when prevent_image_resizes_on_upload is on', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Filters\expectAdded('wp_generate_attachment_metadata')->once()->with(Mockery::any(), 10, 1);

    makeImages(['settings' => ['prevent_image_resizes_on_upload' => true]]);
  });

  it('registers custom-size hooks when custom_sizes are provided', function (): void {
    Filters\expectAdded('jpeg_quality')->once();
    Actions\expectAdded('after_setup_theme')->once();
    Filters\expectAdded('image_size_names_choose')->once();

    makeImages(['settings' => ['custom_sizes' => ['hero' => ['width' => 100, 'height' => 50]]]]);
  });
});

describe('filter callbacks', function (): void {
  it('returns an empty array to disable intermediate image sizes', function (): void {
    expect(makeImages([])->disableImageSizes())->toBe([]);
  });

  it('returns an empty array to disable image editing', function (): void {
    expect(makeImages([])->disableImageEditing())->toBe([]);
  });

  it('returns 1 to disable responsive images', function (): void {
    expect(makeImages([])->disableResponsiveImages())->toBe(1);
  });

  it('reads jpeg quality from settings with a default fallback', function (): void {
    expect(makeImages(['settings' => ['jpeg_quality' => 70]])->setJpegQuality())->toBe(70)
      ->and(makeImages([])->setJpegQuality())->toBe(100);
  });

  it('removes 1536 and 2048 sizes', function (): void {
    Functions\expect('remove_image_size')->once()->with('1536x1536');
    Functions\expect('remove_image_size')->once()->with('2048x2048');

    makeImages([])->disableOtherImageSizes();
  });

  it('strips the default sizes from the sizes array', function (): void {
    $result = makeImages([])->removeDefaultImageSizes([
      'thumbnail' => 1, 'medium' => 1, 'medium_large' => 1, 'large' => 1, 'hero' => 1,
    ]);

    expect($result)->toBe(['hero' => 1]);
  });

  it('empties the sizes metadata to prevent resizes', function (): void {
    $result = makeImages([])->preventImageResizesOnUpload(['sizes' => ['x' => 1], 'width' => 10]);

    expect($result['sizes'])->toBe([])
      ->and($result['width'])->toBe(10);
  });

  it('registers each custom image size via add_image_size', function (): void {
    Functions\expect('add_image_size')->once()->with('hero', 1200, 600, false);
    Functions\expect('add_image_size')->once()->with('square', 300, 300, true);

    makeImages([
      'settings' => ['custom_sizes' => [
        'hero' => ['width' => 1200, 'height' => 600],
        'square' => ['width' => 300, 'height' => 300, 'crop' => true],
      ]],
    ])->addCustomImageSizes();
  });

  it('adds custom sizes to the editor list with a derived or explicit label', function (): void {
    $sizes = makeImages([
      'settings' => ['custom_sizes' => [
        'hero_banner' => ['width' => 1, 'height' => 1],
        'square' => ['width' => 1, 'height' => 1, 'label' => 'Square'],
      ]],
    ])->addCustomImageSizesToEditor(['full' => 'Full Size']);

    expect($sizes)->toBe([
      'full' => 'Full Size',
      'hero_banner' => 'Hero banner',
      'square' => 'Square',
    ]);
  });
});
