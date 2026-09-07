<?php

declare(strict_types=1);

/**
 * Dev entry point: creates the database and loads every migrations/*.sql
 * top-to-bottom using the credentials in config/config.php. Run via
 * setup.cmd, or directly:
 *
 *     php scripts/migrate.php
 *
 * Safe to re-run. Each file is recorded in `schema_migrations` once it has
 * been applied, so a second run applies only what is new and never replays a
 * file that already shaped this database. All PDO work lives in app/Core (§6)
 * — this file only wires it up.
 */

require_once __DIR__ . '/../app/autoload.php';

/**
 * The two files that built every database created before migrations were
 * tracked. On the first tracked run against such a database they are recorded,
 * not replayed: their tables are already there.
 */
const BASELINE = ['001_create_schema.sql', '002_seed_demo_data.sql'];

try {
    $migrator = new Migrator(Database::serverConnection());
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to MySQL: {$e->getMessage()}\n");
    fwrite(STDERR, "Check that MySQL is running and config/config.php matches your machine.\n");
    exit(1);
}

$database = (string) Config::get('db.database', 'mithra');
$files    = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDERR, "No migrations found.\n");
    exit(1);
}

$fresh   = !$migrator->hasTables($database);
$applied = [];

if (!$fresh) {
    $migrator->useDatabase($database);
    $migrator->ensureLog();
    $applied = $migrator->applied();

    if ($applied === []) {
        foreach (BASELINE as $name) {
            $migrator->record($name);
            $applied[] = $name;
        }

        echo "Existing database adopted; the schema and seed files are recorded as applied.\n";
    }
}

$pending = array_values(array_filter(
    $files,
    static fn (string $file): bool => !in_array(basename($file), $applied, true)
));

if ($pending === []) {
    echo "Database {$database} is up to date - nothing to do.\n";
    exit(0);
}

foreach ($pending as $file) {
    echo 'Running ' . basename($file) . "...\n";

    try {
        $migrator->applyFile($file);
    } catch (PDOException $e) {
        fwrite(STDERR, 'Migration failed in ' . basename($file) . ': ' . $e->getMessage() . "\n");
        exit(1);
    }

    // The very first file is what creates the database, so the log cannot
    // exist before it has run.
    if ($fresh) {
        $migrator->useDatabase($database);
        $migrator->ensureLog();
        $fresh = false;
    }

    $migrator->record(basename($file));
}

echo "Database ready.\n";
