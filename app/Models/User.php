<?php

declare(strict_types=1);

/**
 * users — profiles, trust and the headline counts shown on member profiles.
 */
final class User extends BaseModel
{
    protected string $table = 'users';
    protected string $columns = 'id, full_name, email, phone, address, trust_score, status, joined_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDivision(int $id): ?array
    {
        return $this->selectOne(
            'SELECT u.id, u.full_name, u.email, u.phone, u.address, u.trust_score,
                    u.gift_receive_enabled, u.joined_at, d.id AS division_id, d.name AS division_name
               FROM users u
               JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = \'home\'
               JOIN gn_divisions  d  ON d.id = ud.gn_division_id
              WHERE u.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Headline figures for a public profile.
     *
     * @return array<string, mixed>
     */
    public function profileStats(int $id): array
    {
        return [
            'completed'  => (int) $this->selectValue(
                'SELECT COUNT(*) FROM bookings WHERE (borrower_id = :a OR lender_id = :b) AND status = \'completed\'',
                ['a' => $id, 'b' => $id]
            ),
            'items'      => (int) $this->selectValue(
                'SELECT COUNT(*) FROM items WHERE owner_id = :id AND status <> \'archived\'',
                ['id' => $id]
            ),
            'times_lent' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM bookings WHERE lender_id = :id',
                ['id' => $id]
            ),
            'disputes'   => (int) $this->selectValue(
                'SELECT COUNT(*) FROM disputes WHERE raised_by = :id',
                ['id' => $id]
            ),
            'on_time'    => (int) $this->selectValue(
                'SELECT COALESCE(ROUND(100 * AVG(r.return_at <= b.end_date + INTERVAL 1 DAY)), 100)
                   FROM bookings b JOIN return_records r ON r.booking_id = b.id
                  WHERE b.borrower_id = :id AND r.return_at IS NOT NULL',
                ['id' => $id]
            ),
            'donations'  => (int) $this->selectValue(
                'SELECT COUNT(*) FROM donations WHERE donor_id = :id AND status = \'completed\'',
                ['id' => $id]
            ),
        ];
    }

    /**
     * Members this person may send a gift to.
     *
     * Only the member role is giftable — moderators, the sponsor liaison and
     * admins hold staff accounts and are never gift recipients.
     *
     * @return list<array<string, mixed>>
     */
    public function giftableExcept(int $id): array
    {
        return $this->select(
            "SELECT u.id, u.full_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id <> :id
                AND r.code = 'member'
                AND u.status = 'active'
                AND u.gift_receive_enabled = 1
              ORDER BY u.full_name",
            ['id' => $id]
        );
    }

    public function countByRole(string $roleCode): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = :code',
            ['code' => $roleCode]
        );
    }

    public function countNewMembersThisMonth(): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE r.code = 'member' AND u.joined_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
    }

    public function countAll(): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM users');
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users WHERE status = :status',
            ['status' => $status]
        );
    }

    public function countJoinedThisMonth(): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM users WHERE joined_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
    }

    /**
     * Most recently suspended accounts, for the admin notification feed.
     *
     * @return list<array<string, mixed>>
     */
    public function recentlySuspended(int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, full_name, updated_at FROM users
              WHERE status = 'suspended'
              ORDER BY updated_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function roleName(int $id): string
    {
        return (string) ($this->selectValue(
            'SELECT r.name FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = :id',
            ['id' => $id]
        ) ?: 'Member');
    }

    /**
     * Admin member directory, filtered by status and free-text search on name
     * or home division.
     *
     * @return list<array<string, mixed>>
     */
    public function adminList(string $status, string $search): array
    {
        $where = '1=1';
        $params = [];

        if ($status !== '' && $status !== 'all') {
            $where .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        if ($search !== '') {
            $where .= ' AND (u.full_name LIKE :q OR d.name LIKE :q2)';
            $params['q'] = "%{$search}%";
            $params['q2'] = "%{$search}%";
        }

        return $this->select(
            "SELECT u.id, u.full_name, u.status, u.trust_score,
                    r.name AS role_name,
                    d.name AS division_name,
                    COALESCE(mw.balance, 0) AS balance
               FROM users u
               JOIN roles r ON r.id = u.role_id
               LEFT JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = 'home'
               LEFT JOIN gn_divisions d ON d.id = ud.gn_division_id
               LEFT JOIN member_wallets mw ON mw.user_id = u.id
              WHERE {$where}
              ORDER BY u.id
              LIMIT 50",
            $params
        );
    }

    /**
     * Initials for the avatar, e.g. "T.H.K. Madushan" -> "TM".
     */
    public static function initials(string $fullName): string
    {
        preg_match_all('/\b([A-Za-z])/', $fullName, $matches);
        $letters = $matches[1] ?? [];

        if ($letters === []) {
            return '?';
        }

        return strtoupper($letters[0] . ($letters[count($letters) - 1] ?? ''));
    }
}
