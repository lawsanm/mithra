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
}
