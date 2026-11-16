<?php

/**
 * Create the MySQL database used by the test suite when it does not exist.
 *
 * `php artisan test` refuses to start on a clean machine when `hydro_test` is
 * missing ("Unknown database"). This script reads the connection details from
 * .env without booting Laravel (booting would try to connect to a database that
 * may not exist yet) and creates the schema if needed.
 *
 * Usage: php scripts/ensure-test-database.php
 */
$isDirectRun = PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;

$root = dirname(__DIR__);
$env = [];

$envPath = $root.'/.env';

if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

$host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306');
$username = getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? 'root');
$password = getenv('DB_PASSWORD') ?: ($env['DB_PASSWORD'] ?? '');
$database = getenv('TEST_DB_DATABASE') ?: 'hydro_test';

$identifier = '`'.str_replace('`', '', $database).'`';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s', $host, $port),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $identifier,
    ));

    if ($isDirectRun) {
        fwrite(STDOUT, "Test database {$database} is ready.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Could not prepare the test database: {$exception->getMessage()}\n");
    exit(1);
}
