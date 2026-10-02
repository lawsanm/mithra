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
    /** Table this model owns. */
    protected string $table = '';

    /** Columns returned by the generic finders — never SELECT *. */
    protected string $columns = '*';

    public function __construct(protected PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->selectOne("SELECT {$this->columns} FROM {$this->table} WHERE id = :id", ['id' => $id]);
    }

    /**
     * @param  array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param  array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function selectOne(string $sql, array $params = []): ?array
    {
        return $this->select($sql, $params)[0] ?? null;
    }

    /**
     * The first column of the first row, or false when there is no row.
     *
     * @param array<string, mixed> $params
     */
    protected function selectValue(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /**
     * The first column of every row as ids — reviewers, members, divisions.
     *
     * @param  array<string, mixed> $params
     * @return list<int>
     */
    protected function selectIds(string $sql, array $params = []): array
    {
        return array_map('intval', $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * One page of rows (§9: no unbounded lists). LIMIT and OFFSET must bind as
     * integers, which execute($params) cannot do, so they are bound here.
     *
     * @param  array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function selectPage(string $sql, array $params, int $page, int $perPage): array
    {
        $statement = $this->pdo->prepare($sql . ' LIMIT :take OFFSET :skip');

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':take', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':skip', (max(1, $page) - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Run an UPDATE or DELETE. Returns the rows it changed, so a write
     * guarded by the state it expects can tell when it lost a race.
     *
     * @param array<string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Run an INSERT and return the new row's id.
     *
     * @param array<string, mixed> $params
     */
    protected function insert(string $sql, array $params): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * "(:name0, :name1, …)" for an IN list, with each value added to $params
     * — fixed placeholder names, so the values stay bound (§7.1).
     *
     * @param list<scalar>          $values
     * @param array<string, mixed> $params
     */
    protected static function inList(string $name, array $values, array &$params): string
    {
        $names = [];

        foreach (array_values($values) as $index => $value) {
            $names[]               = ':' . $name . $index;
            $params[$name . $index] = $value;
        }

        return '(' . implode(', ', $names) . ')';
    }

    /**
     * The account ids in a row, skipping empty ones — who may see a file.
     *
     * @param  array<string, mixed> $row
     * @return list<int>
     */
    protected static function ids(array $row): array
    {
        return array_values(array_filter(array_map('intval', array_values($row)), static fn (int $id): bool => $id > 0));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
