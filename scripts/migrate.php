<?php

declare(strict_types=1);

/**
 * Dev migration runner: creates the database and loads every migrations/*.sql
 * top-to-bottom using the credentials in config/config.php.
 *
 * Exists so a fresh laptop needs no mysql CLI on PATH — PHP's PDO driver is
 * enough. Run via setup.cmd, or directly:
 *
 *     php scripts/migrate.php
 *
 * Refuses to touch a database that already has tables; drop it first if you
 * want a clean reload.
 */

require_once __DIR__ . '/../app/autoload.php';

$db  = Config::get('db');
$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);

try {
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to MySQL: {$e->getMessage()}\n");
    fwrite(STDERR, "Check that MySQL is running and config/config.php matches your machine.\n");
    exit(1);
}

$tables = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($db['database'])
)->fetchColumn();

if ($tables > 0) {
    echo "Database {$db['database']} already has tables - nothing to do.\n";
    exit(0);
}

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    echo 'Running ' . basename($file) . "...\n";

    try {
        $stmt = $pdo->prepare((string) file_get_contents($file));
        $stmt->execute();

        // Statements after the first execute lazily; drain the rowsets so an
        // error anywhere in the file throws instead of vanishing.
        while ($stmt->nextRowset()) {
            // iterating is the point
        }

        $stmt->closeCursor();
    } catch (PDOException $e) {
        fwrite(STDERR, 'Migration failed in ' . basename($file) . ': ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Database ready.\n";
