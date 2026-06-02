<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Services\Wordpress\Wordpress;

if (!class_exists('WP_Admin_Bar')) {
  eval('class WP_Admin_Bar { public array $removed = []; public function remove_node($id) { $this->removed[] = $id; } }');
}

function invokeWordpress(string $method): void {
  $ref = new ReflectionMethod(Wordpress::class, $method);
  $ref->invoke(null);
}

function stubRequestConstruction(string $method = 'GET'): void {
  $_SERVER['REQUEST_METHOD'] = $method;
  Functions\when('get_the_ID')->justReturn(5);
  Functions\when('get_queried_object')->justReturn(null);
  Functions\when('get_queried_object_id')->justReturn(0);
  Functions\when('untrailingslashit')->returnArg();
  Functions\when('trailingslashit')->returnArg();
  Functions\when('get_home_url')->justReturn('https://acme.test');
  Functions\when('getallheaders')->justReturn([]);
}

describe('getHeadAsString / getFooterAsString', function (): void {
  it('captures the wp_head output as a string', function (): void {
    Functions\when('wp_head')->alias(static function (): void {
      echo '<meta name="head">';
    });

    expect(Wordpress::getHeadAsString())->toBe('<meta name="head">');
  });

  it('captures the wp_footer output as a string', function (): void {
    Functions\when('wp_footer')->alias(static function (): void {
      echo '<script src="footer.js"></script>';
    });

    expect(Wordpress::getFooterAsString())->toBe('<script src="footer.js"></script>');
  });

  it('returns an empty string when nothing is echoed', function (): void {
    Functions\when('wp_head')->justReturn(null);

    expect(Wordpress::getHeadAsString())->toBe('');
  });
});

describe('boot gating', function (): void {
  it('boots the registration helpers on a plain GET request', function (): void {
    stubRequestConstruction('GET');
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('wp_doing_cron')->justReturn(false);
    Config::getInstance()->setConfig(['core' => [
      'excerpt' => ['length' => 20],
      'dashboard_widgets' => ['disable' => ['dashboard_quick_press' => true]],
    ]]);

    Filters\expectAdded('excerpt_length')->once();
    Actions\expectAdded('wp_dashboard_setup')->once();

    Wordpress::boot();
  });

  it('skips the registration helpers during an ajax request', function (): void {
    stubRequestConstruction('GET');
    Functions\when('wp_doing_ajax')->justReturn(true);
    Functions\when('wp_doing_cron')->justReturn(false);
    Config::getInstance()->setConfig(['core' => ['excerpt' => ['length' => 20]]]);

    Filters\expectAdded('excerpt_length')->never();

    Wordpress::boot();
  });

  it('skips the registration helpers on a non-GET request', function (): void {
    stubRequestConstruction('POST');
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('wp_doing_cron')->justReturn(false);
    Config::getInstance()->setConfig(['core' => ['excerpt' => ['length' => 20]]]);

    Filters\expectAdded('excerpt_length')->never();

    Wordpress::boot();
  });
});

describe('bootExcerpt', function (): void {
  it('registers excerpt_length and excerpt_more filters from config', function (): void {
    Config::getInstance()->setConfig(['core' => ['excerpt' => ['length' => 30, 'more' => '...']]]);

    Filters\expectAdded('excerpt_length')->once();
    Filters\expectAdded('excerpt_more')->once();

    invokeWordpress('bootExcerpt');
  });

  it('does nothing when the excerpt config is absent', function (): void {
    Config::getInstance()->setConfig([]);

    Filters\expectAdded('excerpt_length')->never();
    Filters\expectAdded('excerpt_more')->never();

    invokeWordpress('bootExcerpt');
  });
});

describe('bootUploadMimes', function (): void {
  it('registers the upload_mimes filter when mimes are configured', function (): void {
    Config::getInstance()->setConfig(['core' => ['upload_mimes' => ['svg' => 'image/svg+xml']]]);

    Filters\expectAdded('upload_mimes')->once();

    invokeWordpress('bootUploadMimes');
  });

  it('adds and removes mime types through the registered callback', function (): void {
    Config::getInstance()->setConfig(['core' => ['upload_mimes' => ['svg' => 'image/svg+xml', 'exe' => false]]]);

    $captured = null;
    Filters\expectAdded('upload_mimes')->once()->whenHappen(static function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    invokeWordpress('bootUploadMimes');

    $result = $captured(['exe' => 'application/x-msdownload', 'jpg' => 'image/jpeg']);

    expect($result)->toBe(['jpg' => 'image/jpeg', 'svg' => 'image/svg+xml']);
  });

  it('does nothing when the upload_mimes config is empty', function (): void {
    Config::getInstance()->setConfig(['core' => ['upload_mimes' => []]]);

    Filters\expectAdded('upload_mimes')->never();

    invokeWordpress('bootUploadMimes');
  });
});

describe('bootDashboardWidgets', function (): void {
  it('removes the configured dashboard widgets when the setup action fires', function (): void {
    Config::getInstance()->setConfig(['core' => ['dashboard_widgets' => ['disable' => [
      'dashboard_quick_press' => true,
      'dashboard_primary' => 'side',
      'dashboard_activity' => false,
    ]]]]);

    $captured = null;
    Actions\expectAdded('wp_dashboard_setup')->once()->whenHappen(static function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    invokeWordpress('bootDashboardWidgets');

    Functions\expect('remove_meta_box')->once()->with('dashboard_quick_press', 'dashboard', 'normal');
    Functions\expect('remove_meta_box')->once()->with('dashboard_primary', 'dashboard', 'side');

    $captured();
  });

  it('does nothing when there are no widgets to disable', function (): void {
    Config::getInstance()->setConfig(['core' => ['dashboard_widgets' => ['disable' => []]]]);

    Actions\expectAdded('wp_dashboard_setup')->never();

    invokeWordpress('bootDashboardWidgets');
  });
});

describe('bootAdminMenuRemovals', function (): void {
  it('removes the configured admin menus when admin_init fires', function (): void {
    Config::getInstance()->setConfig(['core' => ['admin_menu' => ['disable' => [
      'comments' => true,
      'pages' => true,
      'posts' => true,
      'dashboard' => true,
      'media' => true,
      'tags' => true,
      'unknown' => true,
      'media_kept' => false,
    ]]]]);

    $captured = null;
    Actions\expectAdded('admin_init')->once()->whenHappen(static function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    invokeWordpress('bootAdminMenuRemovals');

    Functions\expect('remove_submenu_page')->once()->with('edit.php', 'edit-tags.php?taxonomy=post_tag');
    Functions\expect('remove_menu_page')->once()->with('edit-comments.php');
    Functions\expect('remove_menu_page')->once()->with('edit.php?post_type=page');
    Functions\expect('remove_menu_page')->once()->with('edit.php');
    Functions\expect('remove_menu_page')->once()->with('index.php');
    Functions\expect('remove_menu_page')->once()->with('upload.php');

    $captured();
  });

  it('does nothing when admin_menu config is absent', function (): void {
    Config::getInstance()->setConfig([]);

    Actions\expectAdded('admin_init')->never();

    invokeWordpress('bootAdminMenuRemovals');
  });
});

describe('bootAdminToolbarRemovals', function (): void {
  it('removes the configured toolbar nodes when admin_bar_menu fires', function (): void {
    Config::getInstance()->setConfig(['core' => ['admin_toolbar' => ['disable' => [
      'wp-logo' => true,
      'comments' => false,
    ]]]]);

    $captured = null;
    Actions\expectAdded('admin_bar_menu')->once()->whenHappen(static function ($callback) use (&$captured): void {
      $captured = $callback;
    });

    invokeWordpress('bootAdminToolbarRemovals');

    $menu = new WP_Admin_Bar();

    $captured($menu);

    expect($menu->removed)->toBe(['wp-logo']);
  });

  it('does nothing when admin_toolbar config is absent', function (): void {
    Config::getInstance()->setConfig([]);

    Actions\expectAdded('admin_bar_menu')->never();

    invokeWordpress('bootAdminToolbarRemovals');
  });
});
