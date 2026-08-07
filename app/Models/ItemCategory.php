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
}
