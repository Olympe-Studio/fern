<?php

declare(strict_types=1);

namespace Fern\Core\Services\Wordpress;

use Fern\Core\Config;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use WP_Admin_Bar;

class Wordpress {
  /**
   * Boot the Wordpress service
   */
  public static function boot(): void {
    if (!wp_doing_ajax() && !wp_doing_cron()) {
      self::bootExcerpt();
      self::bootUploadMimes();
      self::bootDashboardWidgets();
      self::bootAdminMenuRemovals();
      self::bootAdminToolbarRemovals();
    }
  }

  /**
   * Setup excerpt filter
   */
  private static function bootExcerpt(): void {
    $config = Config::get('core.excerpt');

    if (isset($config['length'])) {
      Filters::add('excerpt_length', function () use ($config) {
        return $config['length'];
      });
    }

    if (isset($config['more'])) {
      Filters::add('excerpt_more', function () use ($config) {
        return $config['more'];
      });
    }
  }

  /**
   * Setup upload_mimes filter
   */
  private static function bootUploadMimes(): void {
    $config = Config::get('core.upload_mimes');

    if (!empty($config)) {
      Filters::add('upload_mimes', function (array $mimes) use ($config) {
        foreach ($config as $extension => $mime_type) {
          if ($mime_type === false) {
            unset($mimes[$extension]);
          } else {
            $mimes[$extension] = $mime_type;
          }
        }

        return $mimes;
      });
    }
  }

  /**
   * Setup dashboard widgets
   */
  private static function bootDashboardWidgets(): void {
    $config = Config::get('core.dashboard_widgets');

    if (isset($config['disable']) && !empty($config['disable'])) {
      Events::addHandlers('wp_dashboard_setup', function () use ($config) {
        foreach ($config['disable'] as $widget => $isDisabled) {
          if ($isDisabled === true) {
            self::removeDashboardWidget($widget);
          }
        }
      });
    }
  }

  /**
   * Remove a specific dashboard widget
   */
  private static function removeDashboardWidget(string $widget): void {
    switch ($widget) {
      case 'site_health':
        remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
        break;

      case 'activity':
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        break;

      case 'quick_press':
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        break;

      case 'primary':
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        break;
    }
  }

  /**
   * Setup admin menu removals
   */
  private static function bootAdminMenuRemovals(): void {
    $config = Config::get('core.admin_menu');

    if (isset($config['disable']) && is_array($config['disable'])) {
      Events::addHandlers('admin_init', function () use ($config) {
        foreach ($config['disable'] as $item => $shouldRemove) {
          if ($shouldRemove === true) {
            switch ($item) {
              case 'tags':
                remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=post_tag');
                break;

              case 'comments':
                remove_menu_page('edit-comments.php');
                break;

              case 'pages':
                remove_menu_page('edit.php?post_type=page');
                break;

              case 'posts':
                remove_menu_page('edit.php');
                break;

              case 'dashboard':
                remove_menu_page('index.php');
                break;

              case 'media':
                remove_menu_page('upload.php');
                break;
                // Add more cases as needed
            }
          }
        }
      });
    }
  }

  /**
   * Setup admin toolbar removals
   */
  private static function bootAdminToolbarRemovals(): void {
    $config = Config::get('core.admin_toolbar');

    if (isset($config['disable']) && is_array($config['disable'])) {
      Events::addHandlers('admin_bar_menu', function (WP_Admin_Bar $menu) use ($config) {
        foreach ($config['disable'] as $item => $shouldRemove) {
          if ($shouldRemove === true) {
            $menu->remove_node($item);
          }
        }
      }, 999);
    }
  }
}
