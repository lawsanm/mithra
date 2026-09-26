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
               JOIN users u ON u.id = c.verified_by
              WHERE (:all_divisions = 1 OR de.gn_division_id = :division)
              ORDER BY c.recorded_at DESC, c.id DESC',
            ['all_divisions' => (int) ($divisionId === null), 'division' => $divisionId ?? 0]
        );
    }
}
