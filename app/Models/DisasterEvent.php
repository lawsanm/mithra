<?php

declare(strict_types=1);

/**
 * disaster_events — a Disaster Mode period the Admin started for one division
 * (Plan §14). An event is active until the Admin sets its ended_at.
 */
final class DisasterEvent extends BaseModel
{
    protected string $table = 'disaster_events';
    protected string $columns = 'id, gn_division_id, started_by, reason, started_at, planned_end_at, ended_at';

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
