<?php declare(strict_types=1);

use Tests\TestCase;
use Tests\WPTestCase;

/*
|--------------------------------------------------------------------------
| Test Case bindings
|--------------------------------------------------------------------------
|
| Pure-logic suites use the plain TestCase. Everything that touches WordPress
| globals uses WPTestCase, which wires Brain Monkey for function mocking.
|
*/

pest()->extend(TestCase::class)->in(
  'Unit/Utils',
  'Unit/Factory',
  'Unit/Config',
  'Unit/Context',
  'Unit/Errors',
  'Arch',
);

pest()->extend(WPTestCase::class)->in(
  'Unit/Http',
  'Unit/Services',
  'Unit/Wordpress',
  'Unit/Actions',
  'Unit/Models',
  'Unit/Cli',
  'Feature',
);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeNullOr', function (mixed $expected) {
  return $this->value === null ? $this->toBeNull() : $this->toBe($expected);
});
