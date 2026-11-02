<?php

declare(strict_types=1);

final class DisasterContribution extends BaseModel
{
    protected string $table = 'disaster_contributions';
    protected string $columns = 'id, disaster_event_id, sponsor_id, status, description, estimated_value, recorded_at';

    public function records(?int $divisionId = null): array
    {
        return $this->select(
            'SELECT c.id, c.disaster_event_id, c.sponsor_id, c.contribution_kind, c.description,
                    c.estimated_value, c.handed_over_on, c.receipt_reference, c.notes, c.status,
                    c.received_description, c.received_value, c.received_on, c.ack_reference,
                    c.confirmed_at, c.verified_value, c.recorded_at AS created_at,
                    s.company_name, de.reason AS event_name, d.name AS division_name,
                    m.full_name AS moderator_name, u.full_name AS recorder_name
               FROM disaster_contributions c JOIN sponsors s ON s.id = c.sponsor_id
               JOIN disaster_events de ON de.id = c.disaster_event_id
               JOIN gn_divisions d ON d.id = de.gn_division_id
          LEFT JOIN users m ON m.id = d.moderator_id
               JOIN users u ON u.id = c.recorded_by
              WHERE (:all_divisions = 1 OR de.gn_division_id = :division)
              ORDER BY c.recorded_at DESC, c.id DESC',
            ['all_divisions' => (int) ($divisionId === null), 'division' => $divisionId ?? 0]
        );
    }

    /** Full record and current event ownership/state for service checks. */
    public function findWithEvent(int $id): ?array
    {
        return $this->selectOne(
            'SELECT c.id, c.disaster_event_id, c.sponsor_id, c.contribution_kind, c.description,
                    c.estimated_value, c.handed_over_on, c.receipt_reference, c.sponsor_proof,
                    c.notes, c.status, c.recorded_by, c.verified_by, c.verified_at, c.verified_value,
                    c.received_description, c.received_value, c.received_on, c.ack_reference, c.confirmed_at,
                    c.recorded_at, de.gn_division_id, de.started_at, de.ended_at, d.moderator_id,
                    d.name AS division_name, s.company_name, s.user_id AS sponsor_user_id,
                    recorder.full_name AS recorder_name, verifier.full_name AS verifier_name,
                    m.full_name AS moderator_name
               FROM disaster_contributions c
               JOIN disaster_events de ON de.id = c.disaster_event_id
               JOIN gn_divisions d ON d.id = de.gn_division_id
               JOIN sponsors s ON s.id = c.sponsor_id
               JOIN users recorder ON recorder.id = c.recorded_by
          LEFT JOIN users verifier ON verifier.id = c.verified_by
          LEFT JOIN users m ON m.id = d.moderator_id
              WHERE c.id = :id',
            ['id' => $id]
        );
    }

    /** Lock current workflow state before a transition. */
    public function lock(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, disaster_event_id, status, contribution_kind, estimated_value, handed_over_on,
                    verified_value, verified_by
               FROM disaster_contributions WHERE id = :id FOR UPDATE',
            ['id' => $id]
        );
    }

    /** @param array<string, mixed> $record */
    public function createRecord(array $record): int
    {
        return $this->insert(
            'INSERT INTO disaster_contributions
                (disaster_event_id, sponsor_id, contribution_kind, description, estimated_value,
                 receipt_reference, sponsor_proof, recorded_by, recorded_at, handed_over_on, notes, status)
             VALUES
                (:event, :sponsor, :kind, :description, :value, :receipt, :proof, :recorder,
                 CURRENT_TIMESTAMP, :handed_over, :notes, \'awaiting\')',
            $record
        );
    }

    /** @param array<string, mixed> $record */
    public function updateRecord(int $id, array $record, bool $resetConfirmation): void
    {
        $record['id'] = $id;
        $record['status'] = $resetConfirmation ? 'awaiting' : $record['status'];
        $record['received_description'] = $resetConfirmation ? null : $record['received_description'];
        $record['received_value'] = $resetConfirmation ? null : $record['received_value'];
        $record['received_on'] = $resetConfirmation ? null : $record['received_on'];
        $record['ack_reference'] = $resetConfirmation ? null : $record['ack_reference'];
        $record['confirmed_at'] = $resetConfirmation ? null : $record['confirmed_at'];

        $this->execute(
            'UPDATE disaster_contributions
                SET disaster_event_id = :event, sponsor_id = :sponsor, contribution_kind = :kind,
                    description = :description, estimated_value = :value, receipt_reference = :receipt,
                    sponsor_proof = :proof, handed_over_on = :handed_over, notes = :notes,
                    status = :status,
                    received_description = :received_description, received_value = :received_value,
                    received_on = :received_on, ack_reference = :ack_reference, confirmed_at = :confirmed_at
              WHERE id = :id',
            $record
        );
    }

    /** @param array<string, mixed> $confirmation */
    public function addConfirmation(int $id, array $confirmation): void
    {
        $this->insert(
            'INSERT INTO disaster_contribution_confirmations
                (contribution_id, moderator_id, outcome, received_description, received_value,
                 received_on, ack_reference, proof_files, note)
             VALUES (:contribution, :moderator, :outcome, :description, :value,
                     :received_on, :ack_reference, :proof, :note)',
            $confirmation + ['contribution' => $id]
        );

        $this->execute(
            'UPDATE disaster_contributions
                SET status = \'ready\', received_description = :description,
                    received_value = :value, received_on = :received_on,
                    ack_reference = :ack_reference, confirmed_at = CURRENT_TIMESTAMP
              WHERE id = :id',
            $confirmation + ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function latestConfirmation(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, moderator_id, outcome, received_description, received_value, received_on,
                    ack_reference, proof_files, note, confirmed_at
               FROM disaster_contribution_confirmations
              WHERE contribution_id = :id ORDER BY confirmed_at DESC, id DESC LIMIT 1',
            ['id' => $id]
        );
    }

    /** @param array<string, mixed> $decision */
    public function applyDecision(int $id, array $decision): void
    {
        $this->execute(
            'UPDATE disaster_contributions
                SET status = :status, notes = :notes, verified_value = :verified_value,
                    verified_by = :verified_by, verified_at = :verified_at
              WHERE id = :id',
            $decision + ['id' => $id]
        );
    }

    public function recordEvent(int $id, int $actorId, string $action, ?string $note = null): void
    {
        $this->insert(
            'INSERT INTO disaster_contribution_events (contribution_id, actor_id, action, note)
             VALUES (:contribution, :actor, :action, :note)',
            ['contribution' => $id, 'actor' => $actorId, 'action' => $action, 'note' => $note]
        );
    }

    /** @param list<int> $reliefIds */
    public function replaceReliefLinks(int $id, int $moderatorId, array $reliefIds): void
    {
        $this->execute('DELETE FROM disaster_contribution_relief_links WHERE contribution_id = :id', ['id' => $id]);
        foreach ($reliefIds as $reliefId) {
            $this->insert(
                'INSERT INTO disaster_contribution_relief_links (contribution_id, relief_record_id, linked_by)
                 VALUES (:contribution, :relief, :actor)',
                ['contribution' => $id, 'relief' => $reliefId, 'actor' => $moderatorId]
            );
        }
    }

    public function reliefBelongsToEvent(int $reliefId, int $eventId, int $moderatorId): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM disaster_relief_records
              WHERE id = :relief AND disaster_event_id = :event AND moderator_id = :moderator',
            ['relief' => $reliefId, 'event' => $eventId, 'moderator' => $moderatorId]
        ) === 1;
    }

    /** @return list<array<string, mixed>> */
    public function linkedRelief(int $id): array
    {
        return $this->select(
            'SELECT r.id, r.description, r.relief_type, r.location, r.households_reached,
                    r.estimated_value, r.distributed_on
               FROM disaster_contribution_relief_links l
               JOIN disaster_relief_records r ON r.id = l.relief_record_id
              WHERE l.contribution_id = :id ORDER BY r.distributed_on, r.id',
            ['id' => $id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function timeline(int $id): array
    {
        return $this->select(
            'SELECT e.action, e.note, e.occurred_at, u.full_name AS actor_name
               FROM disaster_contribution_events e JOIN users u ON u.id = e.actor_id
              WHERE e.contribution_id = :id ORDER BY e.occurred_at, e.id',
            ['id' => $id]
        );
    }
}
