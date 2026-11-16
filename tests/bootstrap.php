<?php

/**
 * PHPUnit bootstrap.
 *
 * Laravel connects to `hydro_test` as soon as RefreshDatabase runs, so a
 * missing schema aborts the whole suite with "Unknown database". Creating it
 * here means `php artisan test`, `vendor/bin/pest` and IDE runners all work on
 * a clean machine without a manual setup step.
 */
require __DIR__.'/../vendor/autoload.php';

$ensureDatabase = __DIR__.'/../scripts/ensure-test-database.php';

if (is_file($ensureDatabase)) {
    require $ensureDatabase;
}
