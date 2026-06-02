<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Logger\Logger;

if (!defined('WP_DEBUG_LOG')) {
  define('WP_DEBUG_LOG', sys_get_temp_dir() . '/fern-logger-test-' . uniqid() . '/debug.log');
}

function loggerTempDir(): string {
  return dirname((string) constant('WP_DEBUG_LOG'));
}

function readLog(string $fileName = 'debug.log'): string {
  $path = loggerTempDir() . '/' . $fileName;

  return is_file($path) ? (string) file_get_contents($path) : '';
}

function clearLogs(): void {
  $dir = loggerTempDir();

  if (!is_dir($dir)) {
    return;
  }

  foreach (glob($dir . '/*') ?: [] as $file) {
    @unlink($file);
  }
}

beforeEach(function (): void {
  Functions\when('current_time')->justReturn('2026-06-02 10:00:00');
  Logger::useLogger('default');
  clearLogs();
});

afterAll(function (): void {
  clearLogs();
  @rmdir(loggerTempDir());
});

describe('log levels', function (): void {
  it('writes a correctly formatted entry per level', function (string $method, string $level): void {
    Logger::{$method}('a message');

    expect(readLog())->toContain("[2026-06-02 10:00:00 - {$level}]: a message");
  })->with([
    'error' => ['error', 'ERROR'],
    'warning' => ['warning', 'WARNING'],
    'info' => ['info', 'INFO'],
    'debug' => ['debug', 'DEBUG'],
  ]);

  it('skips empty messages', function (): void {
    Logger::info('');

    expect(readLog())->toBe('');
  });
});

describe('context formatting', function (): void {
  it('appends a JSON-encoded context when an array is given', function (): void {
    Logger::info('with ctx', ['user' => 1, 'role' => 'admin']);

    expect(readLog())->toContain('with ctx Context: {"user":1,"role":"admin"}');
  });

  it('combines multiple scalar context arguments into an array', function (): void {
    Logger::info('multi', 'first', 'second');

    expect(readLog())->toContain('multi Context: ["first","second"]');
  });

  it('logs without a context segment when none is provided', function (): void {
    Logger::info('bare');

    $line = trim(readLog());

    expect($line)->toBe('[2026-06-02 10:00:00 - INFO]: bare');
  });

  it('swallows a JsonException raised by an unencodable context', function (): void {
    Logger::info('bad ctx', ['value' => NAN]);

    expect(readLog())->toBe('');
  });
});

describe('getLogFolder / getLogFilePath', function (): void {
  it('strips a trailing debug.log from the WP_DEBUG_LOG path', function (): void {
    expect(Logger::getLogFolder())->toBe(loggerTempDir());
  });

  it('resolves the log file path from the active log file name', function (): void {
    expect(Logger::getLogFilePath())->toBe(loggerTempDir() . '/debug.log');
  });
});

describe('useLogger', function (): void {
  it('switches the active log file', function (): void {
    Logger::useLogger('custom.log');

    expect(Logger::getLogFilePath())->toBe(loggerTempDir() . '/custom.log');

    Logger::error('routed');

    expect(readLog('custom.log'))->toContain('[2026-06-02 10:00:00 - ERROR]: routed')
      ->and(readLog('debug.log'))->toBe('');
  });

  it('falls back to the default log file for null', function (): void {
    Logger::useLogger('custom.log');
    Logger::useLogger(null);

    expect(Logger::getLogFilePath())->toBe(loggerTempDir() . '/debug.log');
  });

  it('falls back to the default log file for the "default" keyword', function (): void {
    Logger::useLogger('custom.log');
    Logger::useLogger('default');

    expect(Logger::getLogFilePath())->toBe(loggerTempDir() . '/debug.log');
  });
});
