<?php declare(strict_types=1);

arch('source declares strict types')
  ->expect('Fern\Core')
  ->toUseStrictTypes();

arch('no debug statements leak into source')
  ->expect(['dd', 'dump', 'var_dump', 'var_export', 'print_r', 'ray'])
  ->not->toBeUsed();

arch('no die or eval in source')
  ->expect(['die', 'eval'])
  ->not->toBeUsed();

// error_log belongs in the Logger; the spots below are known legacy usages
// kept characterized here so any NEW raw error_log call fails the suite.
arch('error_log is confined to the Logger and known legacy spots')
  ->expect('error_log')
  ->not->toBeUsedIn('Fern\Core')
  ->ignoring([
    'Fern\Core\Logger',
    'Fern\Core\Services\Router\Router',
    'Fern\Core\Services\Woo\WooCartActions',
    'Fern\Core\Services\Mailer\Mailer',
  ]);

arch('errors are throwable')
  ->expect('Fern\Core\Errors')
  ->toExtend(\Exception::class);

arch('php preset')
  ->preset()
  ->php();
