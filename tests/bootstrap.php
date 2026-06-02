<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
|
| Loaded by PHPUnit/Pest only. Pulls the Composer autoloader and the
| WordPress class stubs the tests rely on. Kept out of the Composer "files"
| autoload so the stubs never leak into PHPStan's WordPress bootstrap.
|
*/

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
