<?php

declare(strict_types=1);

/**
 * item_value_reviews — the listing's audit trail (Plan §9.2).
 *
 * Append-only (Rules/CONVENTIONS.md §4): every approve, adjust and reject is a
 * new row, and this model has no update or delete.
 */
final class ItemValueReview extends BaseModel
{
    protected string $table = 'item_value_reviews';
    protected string $columns = 'id, item_id, reviewer_id, decision, previous_value, new_value, reason, created_at';

    /**
     * @param array{item_id:int, reviewer_id:int, decision:string, previous_value:int,
     *              new_value:int, reason:?string} $entry
     */
    public function record(array $entry): int
    {
        return $this->insert(
            'INSERT INTO item_value_reviews (item_id, reviewer_id, decision, previous_value, new_value, reason)
             VALUES (:item_id, :reviewer_id, :decision, :previous_value, :new_value, :reason)',
            $entry
        );
    }

    /**
     * The trail for one listing, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function forItem(int $itemId): array
    {
        return $this->select(
            'SELECT r.decision, r.previous_value, r.new_value, r.reason, r.created_at,
                    u.full_name AS reviewer_name
               FROM item_value_reviews r
               JOIN users u ON u.id = r.reviewer_id
              WHERE r.item_id = :item
              ORDER BY r.created_at DESC, r.id DESC',
            ['item' => $itemId]
        );
    }
}
