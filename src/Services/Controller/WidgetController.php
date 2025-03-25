<?php

declare(strict_types=1);

namespace Fern\Core\Services\Controller;

/** @phpstan-ignore-next-line */
trait WidgetController {
  /**
   * Checks if the current controller is an admin controller
   */
  public function __isWidgetController(): bool {
    return true;
  }

  /**
   * Configure the widget.
   */
  abstract public function configure(): array;
}
