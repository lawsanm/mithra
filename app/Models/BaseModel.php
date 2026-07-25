<?php

declare(strict_types=1);

/**
 * Thin PDO wrapper every model extends (Rules/CONVENTIONS.md §6).
 *
 * SQL lives in models and nowhere else. Every query here is a prepared
 * statement with bound parameters — no value is ever concatenated into SQL.
 * Subclasses declare their table and the columns they are allowed to read,
 * so nothing does SELECT * (§9).
 */
abstract class BaseModel
{
    protected PDO $pdo;

    /** Table this model owns. */
    protected string $table = '';

    /** Columns returned by the generic finders — never SELECT *. */
    protected string $columns = '*';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->selectOne(
            "SELECT {$this->columns} FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );

        return $row;
    }

    /**
     * @param  array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function select(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param  array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->select($sql, $params);

        return $rows[0] ?? null;
    }

    /**
     * @param  array<string, mixed> $params
     */
    protected function selectValue(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }
}
