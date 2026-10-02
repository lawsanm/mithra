<?php

declare(strict_types=1);

/**
 * disaster_relief_records — the relief a moderator handed out during a
 * disaster in their division (Plan §14.1 step 5). Record-keeping only: no
 * points move.
 */
final class DisasterReliefRecord extends BaseModel
{
    public const PER_PAGE = 20;

    protected string $table = 'disaster_relief_records';
    protected string $columns = 'id, disaster_event_id, moderator_id, sponsor_id, relief_type, description, location,
                                 households_reached, estimated_value, distributed_on, notes, created_at, updated_at';

    /**
     * One page of every relief record in a division, newest distribution first,
     * with whether its disaster is still active.
     *
     * @return list<array<string, mixed>>
     */
    public function forDivision(int $divisionId, int $page = 1): array
    {
        return $this->selectPage(
            'SELECT r.id, r.disaster_event_id, r.relief_type, r.description, r.location,
                    r.households_reached, r.estimated_value, r.distributed_on,
                    s.company_name AS sponsor_name, de.reason AS disaster_reason,
                    (de.ended_at IS NULL) AS disaster_active
               FROM disaster_relief_records r
               JOIN disaster_events de ON de.id = r.disaster_event_id
               LEFT JOIN sponsors s    ON s.id = r.sponsor_id
              WHERE de.gn_division_id = :division
              ORDER BY r.distributed_on DESC, r.id DESC',
            ['division' => $divisionId],
            $page,
            self::PER_PAGE
        );
    }

    /**
     * Every relief record for one disaster, in the order it was handed out,
     * for the relief report.
     *
     * @return list<array<string, mixed>>
     */
    public function allForEvent(int $eventId): array
    {
        return $this->select(
            'SELECT r.id, r.relief_type, r.description, r.location, r.households_reached,
                    r.estimated_value, r.distributed_on, r.notes, r.sponsor_id,
                    s.company_name AS sponsor_name, u.full_name AS moderator_name
               FROM disaster_relief_records r
               JOIN users u         ON u.id = r.moderator_id
               LEFT JOIN sponsors s ON s.id = r.sponsor_id
              WHERE r.disaster_event_id = :event
              ORDER BY r.distributed_on ASC, r.id ASC
              LIMIT 2000',
            ['event' => $eventId]
        );
    }

    public function countForDivision(int $divisionId): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*)
               FROM disaster_relief_records r
               JOIN disaster_events de ON de.id = r.disaster_event_id
              WHERE de.gn_division_id = :division',
            ['division' => $divisionId]
        );
    }

    /**
     * What has been handed out so far for one disaster.
     *
     * @return array{records: int, households: int, value: int}
     */
    public function totalsForEvent(int $eventId): array
    {
        $row = $this->selectOne(
            'SELECT COUNT(*) AS records,
                    COALESCE(SUM(households_reached), 0) AS households,
                    COALESCE(SUM(estimated_value), 0) AS value
               FROM disaster_relief_records
              WHERE disaster_event_id = :event',
            ['event' => $eventId]
        ) ?? [];

        return [
            'records'    => (int) ($row['records'] ?? 0),
            'households' => (int) ($row['households'] ?? 0),
            'value'      => (int) ($row['value'] ?? 0),
        ];
    }

    /**
     * One record with the division and state of its disaster, for the
     * ownership and lock checks.
     *
     * @return array<string, mixed>|null
     */
    public function findWithEvent(int $id): ?array
    {
        return $this->selectOne(
            'SELECT r.id, r.disaster_event_id, r.sponsor_id, r.relief_type, r.description, r.location,
                    r.households_reached, r.estimated_value, r.distributed_on, r.notes,
                    de.gn_division_id, de.started_at AS disaster_started_at,
                    (de.ended_at IS NULL) AS disaster_active
               FROM disaster_relief_records r
               JOIN disaster_events de ON de.id = r.disaster_event_id
              WHERE r.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @param array{sponsor_id: ?int, relief_type: string, description: string, location: string,
     *              households_reached: int, estimated_value: ?int, distributed_on: string,
     *              notes: ?string} $record
     */
    public function create(int $eventId, int $moderatorId, array $record): int
    {
        return $this->insert(
            'INSERT INTO disaster_relief_records
                    (disaster_event_id, moderator_id, sponsor_id, relief_type, description, location,
                     households_reached, estimated_value, distributed_on, notes)
             VALUES (:event, :moderator, :sponsor_id, :relief_type, :description, :location,
                     :households_reached, :estimated_value, :distributed_on, :notes)',
            $record + ['event' => $eventId, 'moderator' => $moderatorId]
        );
    }

    /**
     * @param array{sponsor_id: ?int, relief_type: string, description: string, location: string,
     *              households_reached: int, estimated_value: ?int, distributed_on: string,
     *              notes: ?string} $record
     */
    public function updateRecord(int $id, array $record): void
    {
        $this->execute(
            'UPDATE disaster_relief_records
                SET sponsor_id = :sponsor_id, relief_type = :relief_type, description = :description,
                    location = :location, households_reached = :households_reached,
                    estimated_value = :estimated_value, distributed_on = :distributed_on, notes = :notes
              WHERE id = :id',
            $record + ['id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM disaster_relief_records WHERE id = :id', ['id' => $id]);
    }
}
