<?php

declare(strict_types=1);

/** Append-only history of Admin changes to a disaster event's planned end. */
final class DisasterEventEndDateChange extends BaseModel
{
    protected string $table = 'disaster_event_end_date_changes';
    protected string $columns = 'id, disaster_event_id, changed_by, previous_planned_end_at, new_planned_end_at, changed_at';

    public function record(int $eventId, int $adminId, ?string $previousEndAt, string $newEndAt): void
    {
        $this->insert(
            'INSERT INTO disaster_event_end_date_changes
                (disaster_event_id, changed_by, previous_planned_end_at, new_planned_end_at)
             VALUES (:event, :admin, :previous, :new)',
            ['event' => $eventId, 'admin' => $adminId, 'previous' => $previousEndAt, 'new' => $newEndAt]
        );
    }

    /** @param list<int> $eventIds
     *  @return list<array<string, mixed>>
     */
    public function forEvents(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));

        if ($eventIds === []) {
            return [];
        }

        $params = [];
        $inList = self::inList('event', $eventIds, $params);

        return $this->select(
            'SELECT h.id, h.disaster_event_id, h.previous_planned_end_at, h.new_planned_end_at,
                    h.changed_at, u.full_name AS changed_by_name
               FROM disaster_event_end_date_changes h
               JOIN users u ON u.id = h.changed_by
              WHERE h.disaster_event_id IN ' . $inList . '
              ORDER BY h.changed_at DESC, h.id DESC',
            $params
        );
    }
}
