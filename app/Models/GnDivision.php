<?php

declare(strict_types=1);

/**
 * gn_divisions — the Grama Niladhari divisions members belong to, with their
 * moderator and membership counts.
 */
final class GnDivision extends BaseModel
{
    protected string $table = 'gn_divisions';
    protected string $columns = 'id, name, district, moderator_id, disaster_mode_active, disaster_mode_until, status, created_at';

    /** @return list<array<string, mixed>> */
    public function allWithStaff(): array
    {
        return $this->select(
            'SELECT d.id, d.name, d.district, d.status, d.created_at,
                    d.moderator_id, d.disaster_mode_active,
                    COUNT(DISTINCT ud.user_id) AS member_count,
                    m.full_name AS moderator_name
               FROM gn_divisions d
               LEFT JOIN user_divisions ud ON ud.gn_division_id = d.id AND ud.status = :udactive
               LEFT JOIN users m ON m.id = d.moderator_id
              GROUP BY d.id
              ORDER BY d.name',
            ['udactive' => 'active']
        );
    }

    /**
     * The moderator_assignments columns are wrapped in MAX() because MySQL 8's
     * ONLY_FULL_GROUP_BY refuses bare columns it cannot prove are single-valued.
     * The join matches only the division's current moderator's active
     * appointment (one moderator per division), so MAX() reads that
     * single row's values.
     *
     * @return array<string, mixed>|null
     */
    public function findWithStaff(int $id): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.name, d.district, d.status, d.created_at,
                    d.moderator_id, d.disaster_mode_active, d.disaster_mode_until,
                    COUNT(DISTINCT ud.user_id) AS member_count,
                    m.full_name AS moderator_name,
                    MAX(ma.appointed_at) AS moderator_since,
                    MAX(ma.bond_points) AS bond_points,
                    MAX(ma.bond_status) AS bond_status
               FROM gn_divisions d
               LEFT JOIN user_divisions ud ON ud.gn_division_id = d.id AND ud.status = :udactive
               LEFT JOIN users m ON m.id = d.moderator_id
               LEFT JOIN moderator_assignments ma ON ma.user_id = d.moderator_id
                     AND ma.gn_division_id = d.id AND ma.status = :maactive
              WHERE d.id = :id
              GROUP BY d.id',
            ['udactive' => 'active', 'maactive' => 'active', 'id' => $id]
        );
    }

    /** @return array<string, mixed> */
    public function divisionStats(int $id): array
    {
        return [
            'members' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM user_divisions WHERE gn_division_id = :id AND status = \'active\'',
                ['id' => $id]
            ),
            'active_bookings' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM bookings b
                   JOIN items i ON i.id = b.item_id AND i.gn_division_id = :id
                  WHERE b.status IN (\'in_progress\', \'awaiting_return\')',
                ['id' => $id]
            ),
            'items_listed' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM items WHERE gn_division_id = :id AND status NOT IN (\'archived\', \'rejected\')',
                ['id' => $id]
            ),
            'disputes' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM disputes dp
                   JOIN bookings b ON b.id = dp.booking_id
                   JOIN items i ON i.id = b.item_id AND i.gn_division_id = :id
                  WHERE dp.status = \'open\'',
                ['id' => $id]
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function approvalStats(int $id): array
    {
        return [
            'pending' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM user_divisions
                  WHERE gn_division_id = :id AND status = \'pending\'',
                ['id' => $id]
            ),
            'approved' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM user_divisions
                  WHERE gn_division_id = :id AND status = \'active\'',
                ['id' => $id]
            ),
        ];
    }

    /**
     * Divisions a newcomer may apply to join — an archived one accepts nobody.
     *
     * @return list<array<string, mixed>>
     */
    public function activeNames(): array
    {
        return $this->select(
            "SELECT id, name, district FROM gn_divisions
              WHERE status = 'active'
              ORDER BY district, name"
        );
    }

    /** Is this id a division that is currently accepting members? */
    public function isActive(int $id): bool
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM gn_divisions WHERE id = :id AND status = 'active'",
            ['id' => $id]
        ) === 1;
    }

    /**
     * The division this moderator is responsible for, or null when they hold
     * no current appointment.
     */
    public function moderatedBy(int $moderatorId): ?int
    {
        $id = $this->selectValue(
            'SELECT id FROM gn_divisions WHERE moderator_id = :id LIMIT 1',
            ['id' => $moderatorId]
        );

        return $id === false || $id === null ? null : (int) $id;
    }

    public function countAll(): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM gn_divisions');
    }

    /**
     * Most recent pending member approvals across every division, for the
     * admin notification feed.
     *
     * @return list<array<string, mixed>>
     */
    public function recentPendingApprovals(int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            "SELECT ud.created_at, u.full_name, d.name AS division_name
               FROM user_divisions ud
               JOIN users u ON u.id = ud.user_id
               JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.status = 'pending'
              ORDER BY ud.created_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Divisions with nobody currently appointed as moderator.
     *
     * @return list<array<string, mixed>>
     */
    public function vacant(): array
    {
        return $this->select(
            'SELECT d.name, d.created_at FROM gn_divisions d
              WHERE d.moderator_id IS NULL
              ORDER BY d.name'
        );
    }

    /** @return list<array<string, mixed>> */
    public function pendingApprovals(int $divisionId): array
    {
        return $this->select(
            'SELECT u.id, u.full_name, u.nic, u.address, u.created_at AS applied_at
               FROM users u
               JOIN user_divisions ud ON ud.user_id = u.id AND ud.gn_division_id = :div
              WHERE ud.status = \'pending\'
              ORDER BY u.created_at ASC',
            ['div' => $divisionId]
        );
    }

    /**
     * Is this name already used in the district? Names are unique per district
     * (uk_gnd_name_district), compared case-insensitively by the collation.
     */
    public function nameTaken(string $name, string $district, int $exceptId = 0): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM gn_divisions WHERE name = :name AND district = :district AND id <> :id',
            ['name' => $name, 'district' => $district, 'id' => $exceptId]
        ) > 0;
    }

    public function create(string $name, string $district): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO gn_divisions (name, district, status) VALUES (:name, :district, 'active')"
        );
        $statement->execute(['name' => $name, 'district' => $district]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDetails(int $id, string $name, string $district): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE gn_divisions SET name = :name, district = :district WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'name' => $name, 'district' => $district]);
    }

    public function archive(int $id): void
    {
        $statement = $this->pdo->prepare("UPDATE gn_divisions SET status = 'archived' WHERE id = :id");
        $statement->execute(['id' => $id]);
    }
}
