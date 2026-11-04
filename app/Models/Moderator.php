<?php

declare(strict_types=1);

final class Moderator extends BaseModel
{
    protected string $table = 'moderator_assignments';
    protected string $columns = 'id, user_id, gn_division_id, appointed_by, appointed_at, bond_points, bond_status, status';

    public function appointment(int $id): ?array
    {
        return $this->selectOne(
            'SELECT ma.id, ma.appointed_at, ma.objection_window_ends, ma.status,
                    u.full_name, d.name AS division_name
               FROM moderator_assignments ma JOIN users u ON u.id = ma.user_id
               JOIN gn_divisions d ON d.id = ma.gn_division_id WHERE ma.id = :id', ['id' => $id]
        );
    }

    public function objections(int $id): array
    {
        return $this->select(
            'SELECT o.id, o.reason, o.status, o.created_at, u.full_name
               FROM moderator_objections o JOIN users u ON u.id = o.member_id
              WHERE o.moderator_assignment_id = :id ORDER BY o.created_at', ['id' => $id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function allActive(): array
    {
        return $this->select(
            'SELECT ma.id, ma.status, ma.appointed_at, ma.bond_points, ma.bond_status,
                    u.id AS user_id, u.full_name, u.trust_score,
                    d.id AS division_id, d.name AS division_name
               FROM moderator_assignments ma
               JOIN users u ON u.id = ma.user_id
               JOIN gn_divisions d ON d.id = ma.gn_division_id
              WHERE ma.status IN (\'active\', \'trial\')
              ORDER BY d.name'
        );
    }

    /** @return list<array<string, mixed>> */
    public function allWithPerformance(): array
    {
        return $this->select(
            'SELECT ma.id, ma.status, ma.appointed_at, ma.bond_points, ma.bond_status,
                    u.id AS user_id, u.full_name, u.trust_score,
                    d.id AS division_id, d.name AS division_name,
                    (SELECT COUNT(*) FROM items i WHERE i.gn_division_id = ma.gn_division_id
                       AND i.approved_by = ma.user_id) AS items_reviewed,
                    (SELECT COUNT(*) FROM moderator_resolutions mr
                       WHERE mr.moderator_id = ma.user_id AND mr.closed_at IS NOT NULL) AS disputes_resolved
               FROM moderator_assignments ma
               JOIN users u ON u.id = ma.user_id
               JOIN gn_divisions d ON d.id = ma.gn_division_id
              WHERE ma.status IN (\'active\', \'trial\')
              ORDER BY d.name'
        );
    }

    /**
     * Most recently appointed active/trial moderators, for the admin
     * notification feed.
     *
     * @return list<array<string, mixed>>
     */
    public function recentAppointments(int $limit = 5): array
    {
        return $this->selectPage(
            "SELECT ma.appointed_at, u.full_name, d.name AS division_name
               FROM moderator_assignments ma
               JOIN users u ON u.id = ma.user_id
               JOIN gn_divisions d ON d.id = ma.gn_division_id
              WHERE ma.status IN ('active', 'trial')
              ORDER BY ma.appointed_at DESC",
            [],
            1,
            $limit
        );
    }

    /**
     * The active/trial moderator assignment for one user, for the admin
     * moderator profile screen.
     *
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->selectOne(
            "SELECT ma.id, ma.appointed_at, ma.bond_points, ma.bond_status, ma.status,
                    u.id AS user_id, u.full_name, u.trust_score, u.phone, u.email, u.address,
                    d.id AS division_id, d.name AS division_name
               FROM moderator_assignments ma
               JOIN users u ON u.id = ma.user_id
               JOIN gn_divisions d ON d.id = ma.gn_division_id
              WHERE ma.user_id = :id AND ma.status IN ('active', 'trial')
              LIMIT 1",
            ['id' => $userId]
        );
    }

    public function hasAppointmentHistory(int $divisionId): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM moderator_assignments WHERE gn_division_id = :division',
            ['division' => $divisionId]
        ) > 0;
    }

    public function createProvisional(int $userId, int $divisionId, int $adminId): int
    {
        return $this->insert(
            'INSERT INTO moderator_assignments
                (user_id, gn_division_id, appointed_by, objection_window_ends, trial_ends_at,
                 bond_points, bond_status, status)
             VALUES
                (:user, :division, :admin, NOW() + INTERVAL 7 DAY, NOW() + INTERVAL 6 MONTH,
                 500, \'held\', \'trial\')',
            ['user' => $userId, 'division' => $divisionId, 'admin' => $adminId]
        );
    }

    public function lifecycle(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT ma.id, ma.user_id, ma.gn_division_id, ma.status, ma.bond_points, ma.bond_status,
                       ma.objection_window_ends, ma.trial_ends_at, ma.confirmed_at, ma.ended_at, ma.end_reason,
                       u.full_name, d.name AS division_name
                  FROM moderator_assignments ma JOIN users u ON u.id = ma.user_id
                  JOIN gn_divisions d ON d.id = ma.gn_division_id WHERE ma.id = :id';
        return $this->selectOne($lock ? $sql . ' FOR UPDATE' : $sql, ['id' => $id]);
    }

    public function confirmTrial(int $id): void
    {
        $this->execute("UPDATE moderator_assignments SET status = 'active', confirmed_at = NOW() WHERE id = :id AND status = 'trial'", ['id' => $id]);
    }

    public function endAppointment(int $id, string $status, string $bondStatus, string $reason): void
    {
        $this->execute('UPDATE moderator_assignments SET status = :status, bond_status = :bond, ended_at = NOW(), end_reason = :reason WHERE id = :id', ['id' => $id, 'status' => $status, 'bond' => $bondStatus, 'reason' => $reason]);
    }

    public function historyPage(int $page): array
    {
        return $this->selectPage('SELECT ma.id, ma.status, ma.appointed_at, ma.ended_at, ma.end_reason, ma.bond_status, u.full_name, d.name AS division_name FROM moderator_assignments ma JOIN users u ON u.id = ma.user_id JOIN gn_divisions d ON d.id = ma.gn_division_id ORDER BY ma.id DESC', [], $page, 20);
    }
}
