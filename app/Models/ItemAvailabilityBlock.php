<?php

declare(strict_types=1);

/**
 * item_availability_blocks — date ranges a lender has blocked off their own
 * item (Plan 2.4). Rows are the lender's own notes, so they are deleted, not
 * archived: nothing else references them.
 */
final class ItemAvailabilityBlock extends BaseModel
{
    protected string $table = 'item_availability_blocks';
    protected string $columns = 'id, item_id, start_date, end_date, note';

    /**
     * Blocks on one item that have not ended yet, soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingForItem(int $itemId): array
    {
        return $this->select(
            'SELECT id, item_id, start_date, end_date, note
               FROM item_availability_blocks
              WHERE item_id = :item AND end_date >= CURDATE()
              ORDER BY start_date
              LIMIT 50',
            ['item' => $itemId]
        );
    }

    /**
     * One block with its item's owner, so the service can check ownership.
     *
     * @return array<string, mixed>|null
     */
    public function findWithOwner(int $id): ?array
    {
        return $this->selectOne(
            'SELECT b.id, b.item_id, b.start_date, b.end_date, b.note, i.owner_id
               FROM item_availability_blocks b
               JOIN items i ON i.id = b.item_id
              WHERE b.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Blocks on this item that share at least one day with the range. Both
     * ends are inclusive, so a block ending the day another starts overlaps.
     */
    public function countOverlapping(int $itemId, string $start, string $end, int $exceptId = 0): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM item_availability_blocks
              WHERE item_id = :item AND id <> :except
                AND start_date <= :end AND end_date >= :start',
            ['item' => $itemId, 'except' => $exceptId, 'end' => $end, 'start' => $start]
        );
    }

    public function create(int $itemId, string $start, string $end, ?string $note): int
    {
        return $this->insert(
            'INSERT INTO item_availability_blocks (item_id, start_date, end_date, note) VALUES (:item, :start, :end, :note)',
            ['item' => $itemId, 'start' => $start, 'end' => $end, 'note' => $note]
        );
    }

    public function update(int $id, string $start, string $end, ?string $note): void
    {
        $this->execute(
            'UPDATE item_availability_blocks SET start_date = :start, end_date = :end, note = :note WHERE id = :id',
            ['start' => $start, 'end' => $end, 'note' => $note, 'id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM item_availability_blocks WHERE id = :id', ['id' => $id]);
    }
}
