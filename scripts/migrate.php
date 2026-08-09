<?php

declare(strict_types=1);

/**
 * Dev entry point: creates the database and loads every migrations/*.sql
 * top-to-bottom using the credentials in config/config.php. Run via
 * setup.cmd, or directly:
 *
 *     php scripts/migrate.php
 *
 * Refuses to touch a database that already has tables; drop it first if you
 * want a clean reload. All PDO work lives in app/Core (§6) — this file only
 * wires it up.
 */

require_once __DIR__ . '/../app/autoload.php';

try {
    $migrator = new Migrator(Database::serverConnection());
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to MySQL: {$e->getMessage()}\n");
    fwrite(STDERR, "Check that MySQL is running and config/config.php matches your machine.\n");
    exit(1);
}

$database = (string) Config::get('db.database', 'mithra');

if ($migrator->hasTables($database)) {
    echo "Database {$database} already has tables - nothing to do.\n";
    exit(0);
}

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    echo 'Running ' . basename($file) . "...\n";

    try {
        $migrator->applyFile($file);
    } catch (PDOException $e) {
        fwrite(STDERR, 'Migration failed in ' . basename($file) . ': ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Database ready.\n";
