<?php

declare(strict_types=1);

/**
 * item_categories — the Browse filter pills.
 */
final class ItemCategory extends BaseModel
{
    protected string $table = 'item_categories';
    protected string $columns = 'id, name, display_order';

    /**
     * @return list<array<string, mixed>>
     */
    public function allActive(): array
    {
        return $this->select(
            'SELECT id, name FROM item_categories WHERE active = 1 ORDER BY display_order'
        );
    }

    /** @return array<string, mixed>|null */
    public function findCategory(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, name, active FROM item_categories WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Admin categories screen: every category with how many live listings use it.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithListingCount(): array
    {
        return $this->select(
            "SELECT c.id, c.name, c.active, c.display_order,
                    COUNT(i.id) AS listing_count
               FROM item_categories c
               LEFT JOIN items i ON i.category_id = c.id AND i.status NOT IN ('archived', 'rejected')
              GROUP BY c.id
              ORDER BY c.display_order"
        );
    }

    public function nameTaken(string $name, int $exceptId = 0): bool
    {
        $sql = 'SELECT 1 FROM item_categories WHERE name = :name';
        $params = ['name' => $name];

        if ($exceptId > 0) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        return $this->selectValue($sql . ' LIMIT 1', $params) !== false;
    }

    public function createCategory(string $name, bool $active): int
    {
        $displayOrder = (int) $this->selectValue(
            'SELECT COALESCE(MAX(display_order), 0) + 1 FROM item_categories'
        );

        return $this->insert(
            'INSERT INTO item_categories (name, display_order, active) VALUES (:name, :display_order, :active)',
            ['name' => $name, 'display_order' => $displayOrder, 'active' => $active ? 1 : 0]
        );
    }

    public function updateCategory(int $id, string $name, bool $active): void
    {
        $this->execute(
            'UPDATE item_categories SET name = :name, active = :active WHERE id = :id',
            ['name' => $name, 'active' => $active ? 1 : 0, 'id' => $id]
        );
    }
}
