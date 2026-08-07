<?php

declare(strict_types=1);

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

    /** @return array<string, mixed>|null */
    public function findWithStaff(int $id): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.name, d.district, d.status, d.created_at,
                    d.moderator_id, d.disaster_mode_active, d.disaster_mode_until,
                    COUNT(DISTINCT ud.user_id) AS member_count,
                    m.full_name AS moderator_name,
                    ma.appointed_at AS moderator_since,
                    ma.bond_points, ma.bond_status
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

    /** @return list<array{id: int, name: string}> */
    public function allNames(): array
    {
        return $this->select(
            'SELECT id, name FROM gn_divisions ORDER BY name'
        );
    }

    public function countAll(): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM gn_divisions');
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
}
