<?php

declare(strict_types=1);

/**
 * Dev migration runner: applies migrations/*.sql over a server-level
 * connection (Database::serverConnection()), so a fresh laptop needs no
 * mysql CLI on PATH. Driven by scripts/migrate.php.
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
