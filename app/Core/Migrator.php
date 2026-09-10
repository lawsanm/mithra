<?php

declare(strict_types=1);

/**
 * Dev migration runner: applies migrations/*.sql over a server-level
 * connection (Database::serverConnection()), so a fresh laptop needs no
 * mysql CLI on PATH. Driven by scripts/migrate.php.
 *
 * Files applied are recorded in `schema_migrations`, so a database created
 * before a migration was written picks it up on the next run instead of being
 * skipped for having tables already.
 *
 * Lives in app/Core so every prepare/execute stays where §6 allows SQL.
 */
final class Migrator
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * True when the given database already holds at least one table.
     */
    public function hasTables(string $database): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?'
        );
        $statement->execute([$database]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Point this connection at one database, so the statements below need no
     * qualified names.
     *
     * The name is a database identifier and cannot be bound as a parameter, so
     * it is back-quoted instead. It comes from config/config.php, never from a
     * request.
     */
    public function useDatabase(string $database): void
    {
        $this->pdo->exec('USE `' . str_replace('`', '``', $database) . '`');
    }

    /**
     * The migrator's own bookkeeping: which files this database has already
     * seen. Created here rather than in a migration because a migration could
     * not record itself.
     */
    public function ensureLog(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                 filename   VARCHAR(255) NOT NULL,
                 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                 PRIMARY KEY (filename)
             ) ENGINE=InnoDB'
        );
    }

    /**
     * @return list<string> file names, in the order they were applied
     */
    public function applied(): array
    {
        $statement = $this->pdo->prepare('SELECT filename FROM schema_migrations ORDER BY filename');
        $statement->execute();

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Note a file as applied. Re-recording one is not an error: a migration
     * that ran but failed to be recorded must be recordable on the next run.
     */
    public function record(string $filename): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO schema_migrations (filename) VALUES (?)
             ON DUPLICATE KEY UPDATE filename = filename'
        );
        $statement->execute([$filename]);
    }

    /**
     * Run one .sql file. The file may hold many statements; statements after
     * the first execute lazily, so the rowsets are drained to make an error
     * anywhere in the file throw instead of vanishing.
     */
    public function applyFile(string $path): void
    {
        $statement = $this->pdo->prepare((string) file_get_contents($path));
        $statement->execute();

        while ($statement->nextRowset()) {
            // iterating is the point
        }

        $statement->closeCursor();
    }
}
