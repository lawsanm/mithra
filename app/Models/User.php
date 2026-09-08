<?php

declare(strict_types=1);

/**
 * users — profiles, trust and the headline counts shown on member profiles.
 */
final class User extends BaseModel
{
    /**
     * The columns one login attempt is allowed to see, shared by the two
     * finders below so email and mobile can never drift apart.
     */
    private const LOGIN_SELECT = 'SELECT u.id, u.full_name, u.password_hash, u.status,
                    r.code AS role_code, ud.status AS membership_status
               FROM users u
               JOIN roles r ON r.id = u.role_id
          LEFT JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = \'home\'';

    protected string $table = 'users';
    protected string $columns = 'id, full_name, email, phone, address, trust_score, status, joined_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDivision(int $id): ?array
    {
        return $this->selectOne(
            'SELECT u.id, u.full_name, u.email, u.phone, u.address, u.trust_score,
                    u.gift_receive_enabled, u.joined_at, d.id AS division_id, d.name AS division_name,
                    ud.verified_at, ud.status AS membership_status
               FROM users u
               JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = \'home\'
               JOIN gn_divisions  d  ON d.id = ud.gn_division_id
              WHERE u.id = :id',
            ['id' => $id]
        );
    }

    /**
     * The account a login attempt may match, found by email address.
     *
     * Reads only what authentication needs — the hash is never fetched by the
     * profile queries above (§9: name the columns). The home membership comes
     * along because a moderator's rejection lives on that row, not on `users`:
     * without it a rejected applicant would be told to keep waiting.
     *
     * Staff accounts (liaison, admin) hold no division membership, so the join
     * is a LEFT JOIN and membership_status is NULL for them.
     *
     * @return array<string, mixed>|null
     */
    public function findForLoginByEmail(string $email): ?array
    {
        return $this->selectOne(
            self::LOGIN_SELECT . '
              WHERE u.email = :email
              ORDER BY u.id
              LIMIT 1',
            ['email' => $email]
        );
    }

    /**
     * The same row found by mobile number, compared on the last nine digits.
     *
     * Numbers are stored as people write them ("+94 77 123 4567"), so the
     * comparison runs against the generated `phone_digits` column that
     * migration 003 keeps in step with `phone` — indexed, so this is a lookup
     * rather than the scan the inline REPLACE() chain used to cost.
     *
     * @param string $digits the last nine digits of the typed number
     *
     * @return array<string, mixed>|null
     */
    public function findForLoginByPhone(string $digits): ?array
    {
        return $this->selectOne(
            self::LOGIN_SELECT . '
              WHERE u.phone_digits = :digits
              ORDER BY u.id
              LIMIT 1',
            ['digits' => $digits]
        );
    }

    /**
     * Is this email address already spoken for? Registration asks before
     * inserting so the member gets a field message instead of a duplicate-key
     * error; the unique index behind it is what actually guarantees it (§8).
     */
    public function emailTaken(string $email): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users WHERE email = :email',
            ['email' => $email]
        ) > 0;
    }

    public function nicTaken(string $nic): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users WHERE nic = :nic',
            ['nic' => $nic]
        ) > 0;
    }

    /**
     * @param string $digits the last nine digits of a mobile number
     */
    public function phoneTaken(string $digits): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users WHERE phone_digits = :digits',
            ['digits' => $digits]
        ) > 0;
    }

    /**
     * Insert one registration and return its id.
     *
     * The caller passes an already-validated set and an already-hashed
     * password — this method makes no business decisions and never hashes (§6).
     * Every new account starts 'pending': only a moderator's approval moves it
     * on (Proposal §19.1).
     *
     * @param array{role_id:int, full_name:string, nic:string, phone:string,
     *              email:?string, address:string, password_hash:string} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO users
                 (role_id, full_name, nic, phone, email, address, password_hash, status)
             VALUES
                 (:role_id, :full_name, :nic, :phone, :email, :address, :password_hash, 'pending')"
        );

        $statement->execute([
            'role_id'       => $data['role_id'],
            'full_name'     => $data['full_name'],
            'nic'           => $data['nic'],
            'phone'         => $data['phone'],
            'email'         => $data['email'],
            'address'       => $data['address'],
            'password_hash' => $data['password_hash'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Let a verified applicant in. `joined_at` is the start of membership and
     * is only ever written once — COALESCE keeps the original date if an
     * account is ever re-approved.
     */
    public function markActive(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE users
                SET status = 'active', joined_at = COALESCE(joined_at, NOW())
              WHERE id = :id AND status = 'pending'"
        );

        $statement->execute(['id' => $id]);
    }

    public function roleIdFor(string $code): ?int
    {
        $id = $this->selectValue('SELECT id FROM roles WHERE code = :code', ['code' => $code]);

        return $id === false || $id === null ? null : (int) $id;
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
