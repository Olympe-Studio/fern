<?php declare(strict_types=1);

use Fern\Core\Services\Controller\AdminController;
use Fern\Core\Services\Controller\WidgetController;

class AdminTraitFixture {
  use AdminController;

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return [
      'page_title' => 'Reports',
      'menu_title' => 'Reports',
      'capability' => 'manage_options',
      'icon' => 'dashicons-chart-bar',
    ];
  }
}

class SubmenuAdminTraitFixture {
  use AdminController;

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return [
      'page_title' => 'Child',
      'menu_title' => 'Child',
      'parent_slug' => 'reports',
    ];
  }
}

class WidgetTraitFixture {
  use WidgetController;

  /**
   * @return array<string, mixed>
   */
  public function configure(): array {
    return [
      'id_base' => 'fern_promo',
      'name' => 'Fern Promo',
      'description' => 'Shows a promo block.',
    ];
  }
}

describe('AdminController trait', function (): void {
  it('marks the using class as an admin controller', function (): void {
    expect((new AdminTraitFixture())->__isAdminController())->toBeTrue();
  });

  it('exposes the concrete configure() implementation', function (): void {
    $config = (new AdminTraitFixture())->configure();

    expect($config)->toBe([
      'page_title' => 'Reports',
      'menu_title' => 'Reports',
      'capability' => 'manage_options',
      'icon' => 'dashicons-chart-bar',
    ]);
  });

  it('supports a submenu configuration via parent_slug', function (): void {
    $config = (new SubmenuAdminTraitFixture())->configure();

    expect($config)->toHaveKey('parent_slug')
      ->and($config['parent_slug'])->toBe('reports')
      ->and((new SubmenuAdminTraitFixture())->__isAdminController())->toBeTrue();
  });

  it('declares configure() as an abstract trait requirement', function (): void {
    $method = new ReflectionMethod(AdminController::class, 'configure');

    expect($method->isAbstract())->toBeTrue()
      ->and($method->isPublic())->toBeTrue();
  });
});

describe('WidgetController trait', function (): void {
  it('marks the using class as a widget controller', function (): void {
    expect((new WidgetTraitFixture())->__isWidgetController())->toBeTrue();
  });

  it('exposes the concrete configure() implementation', function (): void {
    $config = (new WidgetTraitFixture())->configure();

    expect($config)->toBe([
      'id_base' => 'fern_promo',
      'name' => 'Fern Promo',
      'description' => 'Shows a promo block.',
    ]);
  });

  it('declares configure() as an abstract trait requirement', function (): void {
    $method = new ReflectionMethod(WidgetController::class, 'configure');

    expect($method->isAbstract())->toBeTrue()
      ->and($method->isPublic())->toBeTrue();
  });
});

describe('trait independence', function (): void {
  it('only the admin trait answers the admin marker', function (): void {
    $admin = new AdminTraitFixture();
    $widget = new WidgetTraitFixture();

    expect(method_exists($admin, '__isAdminController'))->toBeTrue()
      ->and(method_exists($admin, '__isWidgetController'))->toBeFalse()
      ->and(method_exists($widget, '__isWidgetController'))->toBeTrue()
      ->and(method_exists($widget, '__isAdminController'))->toBeFalse();
  });
});
