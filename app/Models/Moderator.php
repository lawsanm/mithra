<?php

declare(strict_types=1);

final class Moderator extends BaseModel
{
    protected string $table = 'moderator_assignments';
    protected string $columns = 'id, user_id, gn_division_id, appointed_by, appointed_at, bond_points, bond_status, status';

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
        $statement = $this->pdo->prepare(
            "SELECT ma.appointed_at, u.full_name, d.name AS division_name
               FROM moderator_assignments ma
               JOIN users u ON u.id = ma.user_id
               JOIN gn_divisions d ON d.id = ma.gn_division_id
              WHERE ma.status IN ('active', 'trial')
              ORDER BY ma.appointed_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
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

    /** @return list<array<string, mixed>> */
    public function eligibleCandidates(int $divisionId): array
    {
        return $this->select(
            'SELECT u.id, u.full_name, u.trust_score, u.address, u.joined_at,
                    ud.verified_at,
                    (SELECT COUNT(*) FROM bookings WHERE (borrower_id = u.id OR lender_id = u.id)
                       AND status = \'completed\') AS completed_bookings
               FROM users u
               JOIN user_divisions ud ON ud.user_id = u.id AND ud.gn_division_id = :div
                    AND ud.membership_type = \'home\' AND ud.status = \'active\'
               JOIN roles r ON r.id = u.role_id
              WHERE r.code = \'member\'
                AND u.status = \'active\'
                AND u.trust_score >= 70
              ORDER BY u.trust_score DESC',
            ['div' => $divisionId]
        );
    }
}
