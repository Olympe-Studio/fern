<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Services\I18N\I18N;

function resetI18NState(): void {
  foreach (['hasLoadedTextDomain' => false, 'storedLanguagesPath' => '', 'storedDomain' => ''] as $name => $value) {
    $property = new ReflectionProperty(I18N::class, $name);
    $property->setValue(null, $value);
  }
}

function i18nLanguagesDir(): string {
  return sys_get_temp_dir() . '/fern-i18n-' . uniqid();
}

beforeEach(function (): void {
  resetI18NState();
  Functions\when('untrailingslashit')->returnArg();
});

afterEach(function (): void {
  resetI18NState();
});

describe('boot', function (): void {
  it('does nothing when the i18n config is missing', function (): void {
    Config::getInstance()->setConfig([]);

    Actions\expectAdded('pll_language_defined')->never();
    Actions\expectAdded('wp')->never();

    I18N::boot();

    expect((new ReflectionProperty(I18N::class, 'storedDomain'))->getValue())->toBe('');
  });

  it('does nothing when the i18n config is not an array', function (): void {
    Config::getInstance()->setConfig(['i18n' => 'nope']);

    Actions\expectAdded('pll_language_defined')->never();

    I18N::boot();

    expect((new ReflectionProperty(I18N::class, 'storedDomain'))->getValue())->toBe('');
  });

  it('registers the Polylang and wp hooks and stores the resolved path and domain', function (): void {
    $dir = i18nLanguagesDir();
    mkdir($dir, 0o777, true);
    Config::getInstance()->setConfig([
      'i18n' => ['languages_folder_path' => $dir, 'domain' => 'myapp'],
    ]);

    Actions\expectAdded('pll_language_defined')->once()->with([I18N::class, 'loadTextDomainLate'], 0, Mockery::any());
    Actions\expectAdded('wp')->once()->with([I18N::class, 'loadTextDomainLate'], 20, Mockery::any());

    I18N::boot();

    expect((new ReflectionProperty(I18N::class, 'storedLanguagesPath'))->getValue())->toBe($dir)
      ->and((new ReflectionProperty(I18N::class, 'storedDomain'))->getValue())->toBe('myapp');

    rmdir($dir);
  });

  it('falls back to the default domain and the root /languages path', function (): void {
    $root = i18nLanguagesDir();
    mkdir($root . '/languages', 0o777, true);
    Config::getInstance()->setConfig(['root' => $root, 'i18n' => ['foo' => 'bar']]);

    Actions\expectAdded('pll_language_defined')->once();
    Actions\expectAdded('wp')->once();

    I18N::boot();

    expect((new ReflectionProperty(I18N::class, 'storedLanguagesPath'))->getValue())->toBe($root . '/languages')
      ->and((new ReflectionProperty(I18N::class, 'storedDomain'))->getValue())->toBe('fern');

    rmdir($root . '/languages');
    rmdir($root);
  });

  it('creates the languages directory when it does not exist', function (): void {
    $dir = i18nLanguagesDir();
    Config::getInstance()->setConfig(['i18n' => ['languages_folder_path' => $dir]]);

    Actions\expectAdded('pll_language_defined')->once();
    Actions\expectAdded('wp')->once();
    Functions\expect('wp_mkdir_p')->once()->with($dir)->andReturn(true);

    I18N::boot();
  });
});

describe('loadTextDomainLate', function (): void {
  it('returns early and does not load when nothing has been stored', function (): void {
    Functions\expect('determine_locale')->never();

    I18N::loadTextDomainLate();

    expect((new ReflectionProperty(I18N::class, 'hasLoadedTextDomain'))->getValue())->toBeFalse();
  });

  it('loads the text domain once and flips the guard flag', function (): void {
    $dir = i18nLanguagesDir();
    mkdir($dir, 0o777, true);
    file_put_contents($dir . '/myapp-fr_FR.mo', 'x');

    (new ReflectionProperty(I18N::class, 'storedLanguagesPath'))->setValue(null, $dir);
    (new ReflectionProperty(I18N::class, 'storedDomain'))->setValue(null, 'myapp');

    Functions\when('determine_locale')->justReturn('fr_FR');
    Functions\expect('load_textdomain')->once()->with('myapp', $dir . '/myapp-fr_FR.mo');

    I18N::loadTextDomainLate();

    expect((new ReflectionProperty(I18N::class, 'hasLoadedTextDomain'))->getValue())->toBeTrue();

    unlink($dir . '/myapp-fr_FR.mo');
    rmdir($dir);
  });

  it('does not load a second time once the guard flag is set', function (): void {
    (new ReflectionProperty(I18N::class, 'hasLoadedTextDomain'))->setValue(null, true);
    (new ReflectionProperty(I18N::class, 'storedLanguagesPath'))->setValue(null, '/tmp/x');
    (new ReflectionProperty(I18N::class, 'storedDomain'))->setValue(null, 'myapp');

    Functions\expect('determine_locale')->never();

    I18N::loadTextDomainLate();
  });
});

describe('loadTextDomain', function (): void {
  beforeEach(function (): void {
    $this->dir = i18nLanguagesDir();
    mkdir($this->dir, 0o777, true);
  });

  afterEach(function (): void {
    foreach (glob($this->dir . '/*') ?: [] as $file) {
      unlink($file);
    }
    rmdir($this->dir);
  });

  it('returns early when the locale is empty', function (): void {
    Functions\when('determine_locale')->justReturn('');
    Functions\expect('load_textdomain')->never();

    I18N::loadTextDomain($this->dir, 'myapp');
  });

  it('loads the exact locale .mo file when it exists', function (): void {
    file_put_contents($this->dir . '/myapp-de_DE.mo', 'x');
    Functions\when('determine_locale')->justReturn('de_DE');

    Functions\expect('load_textdomain')->once()->with('myapp', $this->dir . '/myapp-de_DE.mo');

    I18N::loadTextDomain($this->dir, 'myapp');
  });

  it('falls back to the base locale .mo file', function (): void {
    file_put_contents($this->dir . '/myapp-de.mo', 'x');
    Functions\when('determine_locale')->justReturn('de_DE');

    Functions\expect('load_textdomain')->once()->with('myapp', $this->dir . '/myapp-de.mo');

    I18N::loadTextDomain($this->dir, 'myapp');
  });

  it('falls back to a wildcard locale .mo file', function (): void {
    file_put_contents($this->dir . '/myapp-de_AT.mo', 'x');
    Functions\when('determine_locale')->justReturn('de_DE');

    Functions\expect('load_textdomain')->once()->with('myapp', $this->dir . '/myapp-de_AT.mo');

    I18N::loadTextDomain($this->dir, 'myapp');
  });

  it('loads nothing when no matching .mo file is found', function (): void {
    Functions\when('determine_locale')->justReturn('de_DE');
    Functions\expect('load_textdomain')->never();

    I18N::loadTextDomain($this->dir, 'myapp');
  });
});
