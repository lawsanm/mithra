<?php

declare(strict_types=1);

/**
 * disaster_events — a Disaster Mode period the Admin started for one division
 * (Plan §14). An event is active until the Admin sets its ended_at.
 */
final class DisasterEvent extends BaseModel
{
    public const ADMIN_PAGE_SIZE = 20;

    protected string $table = 'disaster_events';
    protected string $columns = 'id, gn_division_id, started_by, reason, started_at, planned_end_at, ended_at';

    public function records(): array
    {
        return $this->select(
            'SELECT de.id, de.gn_division_id, de.reason, de.started_at, de.planned_end_at, de.ended_at,
                    d.name AS division_name, m.full_name AS moderator_name, u.full_name AS started_by_name
               FROM disaster_events de JOIN gn_divisions d ON d.id = de.gn_division_id
          LEFT JOIN users m ON m.id = d.moderator_id
               JOIN users u ON u.id = de.started_by
              ORDER BY de.started_at DESC, de.id DESC'
        );
    }

    /** @return list<array<string, mixed>> */
    public function adminPage(int $page): array
    {
        return $this->selectPage(
            'SELECT de.id, de.reason, de.started_at, de.planned_end_at, de.ended_at,
                    d.name AS division_name, u.full_name AS started_by_name
               FROM disaster_events de
               JOIN gn_divisions d ON d.id = de.gn_division_id
               JOIN users u ON u.id = de.started_by
              ORDER BY de.started_at DESC, de.id DESC',
            [],
            $page,
            self::ADMIN_PAGE_SIZE
        );
    }

    public function countAll(): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM disaster_events');
    }

    /**
     * Start one disaster period and return its id for actor links/notifications.
     */
    public function create(int $divisionId, int $startedBy, string $reason, ?string $plannedEndAt): int
    {
        return $this->insert(
            'INSERT INTO disaster_events (gn_division_id, started_by, reason, planned_end_at)
             VALUES (:division, :started_by, :reason, :planned_end)',
            [
                'division' => $divisionId,
                'started_by' => $startedBy,
                'reason' => $reason,
                'planned_end' => $plannedEndAt,
            ]
        );
    }

    /**
     * The division's current disaster, or null when Disaster Mode is off.
     *
     * @return array<string, mixed>|null
     */
    public function activeForDivision(int $divisionId): ?array
    {
        return $this->selectOne(
            'SELECT de.id, de.gn_division_id, de.reason, de.started_at, de.planned_end_at, d.name AS division_name
               FROM disaster_events de
               JOIN gn_divisions d ON d.id = de.gn_division_id
              WHERE de.gn_division_id = :division AND de.ended_at IS NULL
              ORDER BY de.started_at DESC
              LIMIT 1',
            ['division' => $divisionId]
        );
    }

    /** End every open event for the division and return the number closed. */
    public function endActiveForDivision(int $divisionId): int
    {
        return $this->execute(
            'UPDATE disaster_events
                SET ended_at = CURRENT_TIMESTAMP
              WHERE gn_division_id = :division AND ended_at IS NULL',
            ['division' => $divisionId]
        );
    }

    /** @return list<array{id: int, gn_division_id: int}> */
    public function dueForExpiry(): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'gn_division_id' => (int) $row['gn_division_id'],
            ],
            $this->select(
                'SELECT id, gn_division_id
                   FROM disaster_events
                  WHERE ended_at IS NULL AND planned_end_at <= CURRENT_TIMESTAMP
                  ORDER BY planned_end_at, id'
            )
        );
    }

    /** Recheck and lock one due event after its division row has been locked. */
    public function lockDueEvent(int $eventId, int $divisionId): ?array
    {
        return $this->selectOne(
            'SELECT id, reason, planned_end_at
               FROM disaster_events
              WHERE id = :id AND gn_division_id = :division
                AND ended_at IS NULL AND planned_end_at <= CURRENT_TIMESTAMP
              FOR UPDATE',
            ['id' => $eventId, 'division' => $divisionId]
        );
    }

    /** End one event only if it remains due and open. */
    public function endIfDue(int $eventId): bool
    {
        return $this->execute(
            'UPDATE disaster_events
                SET ended_at = CURRENT_TIMESTAMP
              WHERE id = :id AND ended_at IS NULL AND planned_end_at <= CURRENT_TIMESTAMP',
            ['id' => $eventId]
        ) === 1;
    }

    /** Lock an event only while it is still open in its expected division. */
    public function lockOpenForUpdate(int $eventId, int $divisionId): ?array
    {
        return $this->selectOne(
            'SELECT id, gn_division_id, planned_end_at, ended_at
               FROM disaster_events
              WHERE id = :id AND gn_division_id = :division AND ended_at IS NULL
              FOR UPDATE',
            ['id' => $eventId, 'division' => $divisionId]
        );
    }

    /** Update only the current planned end of an open event. */
    public function updatePlannedEnd(int $eventId, string $plannedEndAt): bool
    {
        return $this->execute(
            'UPDATE disaster_events
                SET planned_end_at = :planned_end
              WHERE id = :id AND ended_at IS NULL',
            ['id' => $eventId, 'planned_end' => $plannedEndAt]
        ) === 1;
    }

    /**
     * Every disaster in a division, newest first, for the report list.
     *
     * @return list<array<string, mixed>>
     */
    public function forDivision(int $divisionId): array
    {
        return $this->select(
            'SELECT id, reason, started_at, planned_end_at, ended_at
               FROM disaster_events
              WHERE gn_division_id = :division
              ORDER BY started_at DESC
              LIMIT 50',
            ['division' => $divisionId]
        );
    }

    /**
     * One disaster with its division's name, for a report heading.
     *
     * @return array<string, mixed>|null
     */
    public function findWithDivision(int $id): ?array
    {
        return $this->selectOne(
            'SELECT de.id, de.gn_division_id, de.reason, de.started_at, de.planned_end_at, de.ended_at,
                    d.name AS division_name
               FROM disaster_events de
               JOIN gn_divisions d ON d.id = de.gn_division_id
              WHERE de.id = :id',
            ['id' => $id]
        );
    }
}
