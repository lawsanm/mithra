<?php

declare(strict_types=1);

/**
 * saved_searches — a member's named Browse filters (Plan 2.3). Every query is
 * limited to the owner's user_id, so a forged id finds nothing.
 */
final class SavedSearch extends BaseModel
{
    protected string $table = 'saved_searches';
    protected string $columns = 'id, user_id, name, filters, created_at';

    /**
     * @return list<array<string, mixed>>
     */
    public function forMember(int $userId): array
    {
        return $this->select(
            'SELECT id, name, filters, created_at FROM saved_searches
              WHERE user_id = :user ORDER BY name LIMIT 50',
            ['user' => $userId]
        );
    }

    public function countForMember(int $userId): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM saved_searches WHERE user_id = :user', ['user' => $userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOwned(int $id, int $userId): ?array
    {
        return $this->selectOne(
            'SELECT id, user_id, name, filters FROM saved_searches WHERE id = :id AND user_id = :user',
            ['id' => $id, 'user' => $userId]
        );
    }

    /**
     * @param array<string, string> $filters
     */
    public function create(int $userId, string $name, array $filters): int
    {
        return $this->insert(
            'INSERT INTO saved_searches (user_id, name, filters) VALUES (:user, :name, :filters)',
            ['user' => $userId, 'name' => $name, 'filters' => json_encode($filters, JSON_THROW_ON_ERROR)]
        );
    }

    public function rename(int $id, int $userId, string $name): void
    {
        $this->execute('UPDATE saved_searches SET name = :name WHERE id = :id AND user_id = :user', ['name' => $name, 'id' => $id, 'user' => $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $this->execute('DELETE FROM saved_searches WHERE id = :id AND user_id = :user', ['id' => $id, 'user' => $userId]);
    }
}
