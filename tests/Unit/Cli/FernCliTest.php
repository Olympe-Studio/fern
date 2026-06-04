<?php declare(strict_types=1);

use Fern\Core\CLI\FernCLI;
use Fern\Core\CLI\FernControllerCommand;

if (!class_exists('WP_CLI')) {
  /**
   * Recording stub for the WP_CLI facade. Unlike the real one, none of these
   * methods exit the process, so the command code under test keeps executing
   * and we can assert on what it tried to report.
   */
  class WP_CLI {
    /** @var array<int, string> */
    public static array $errors = [];

    /** @var array<int, string> */
    public static array $success = [];

    /** @var array<int, string> */
    public static array $lines = [];

    /** @var array<int, string> */
    public static array $warnings = [];

    /** @var array<int, array{0: string, 1: mixed}> */
    public static array $commands = [];

    public static bool $confirmReturn = true;

    public static function reset(): void {
      self::$errors = [];
      self::$success = [];
      self::$lines = [];
      self::$warnings = [];
      self::$commands = [];
      self::$confirmReturn = true;
    }

    public static function error(string $message): void {
      self::$errors[] = $message;
    }

    public static function success(string $message): void {
      self::$success[] = $message;
    }

    public static function line(string $message = ''): void {
      self::$lines[] = $message;
    }

    public static function warning(string $message): void {
      self::$warnings[] = $message;
    }

    public static function confirm(string $question): bool {
      return self::$confirmReturn;
    }

    public static function add_command(string $name, mixed $callable): void {
      self::$commands[] = [$name, $callable];
    }
  }
}

beforeEach(function (): void {
  WP_CLI::reset();
});

describe('boot', function (): void {
  it('throws when the WP_CLI constant is not defined or falsy', function (): void {
    expect(fn (): FernCLI => FernCLI::boot())
      ->toThrow(RuntimeException::class, 'WP CLI is not available.');
  });
});

describe('command registration', function (): void {
  it('registers the fern:controller command on construction', function (): void {
    new FernCLI();

    expect(WP_CLI::$commands)->toHaveCount(1)
      ->and(WP_CLI::$commands[0][0])->toBe('fern:controller')
      ->and(WP_CLI::$commands[0][1])->toBe(FernControllerCommand::class);
  });
});
