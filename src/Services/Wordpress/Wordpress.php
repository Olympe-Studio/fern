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
      Filters::on('excerpt_length', fn() => $config['length']);
    }

    if (isset($config['more'])) {
      Filters::on('excerpt_more', fn() => $config['more']);
    }
  }

  /**
   * Setup upload_mimes filter
   */
  private static function bootUploadMimes(): void {
    $config = Config::get('core.upload_mimes');

    if (!empty($config)) {
      Filters::on('upload_mimes', function (array $mimes) use ($config) {
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
      Events::on('wp_dashboard_setup', function () use ($config): void {
        /**
         * If disabled is not a boolean it means we want to force the context.
         */
        foreach ($config['disable'] as $widget => $isDisabled) {
          if (is_bool($isDisabled) && $isDisabled === false) {
            continue;
          }

          $context = $isDisabled === true ? 'normal' : $isDisabled;
          self::removeDashboardWidget($widget, $context);
        }
      }, 10, 0);
    }
  }

  /**
   * Get the head as a string
   */
  public static function getHeadAsString(): string {
    ob_start();
    wp_head();
    return ob_get_clean();
  }

  /**
   * Get the footer as a string
   */
  public static function getFooterAsString(): string {
    ob_start();
    wp_footer();
    return ob_get_clean();
  }

  /**
   * Remove a specific dashboard widget
   *
   * @param string $widget
   * @param string $context
   *
   * @return void
   */
  private static function removeDashboardWidget(string $widget, string $context = 'normal'): void {
    remove_meta_box($widget, 'dashboard', $context);
  }

  /**
   * Setup admin menu removals
   */
  private static function bootAdminMenuRemovals(): void {
    $config = Config::get('core.admin_menu');

    if (isset($config['disable']) && is_array($config['disable'])) {
      Events::on('admin_init', function () use ($config): void {
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
      }, 20, 0);
    }
  }

  /**
   * Setup admin toolbar removals
   */
  private static function bootAdminToolbarRemovals(): void {
    $config = Config::get('core.admin_toolbar');

    if (isset($config['disable']) && is_array($config['disable'])) {
      Events::on('admin_bar_menu', function (WP_Admin_Bar $menu) use ($config): void {
        foreach ($config['disable'] as $item => $shouldRemove) {
          if ($shouldRemove === true) {
            $menu->remove_node($item);
          }
        }
      }, 999);
    }
  }
}
