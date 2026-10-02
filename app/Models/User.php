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
    private const LOGIN_SELECT = 'SELECT u.id, u.full_name, u.password_hash, u.password_changed_at, u.status,
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
                    u.gift_receive_enabled, u.status, u.joined_at,
                    d.id AS division_id, d.name AS division_name,
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
     * on (Plan §18.1). A sponsor login the Sponsor Liaison creates is opened
     * straight after, in the same transaction (§15.10).
     *
     * @param array{role_id:int, full_name:string, nic:string, nic_photo_path:?string,
     *              phone:string, email:?string, address:string, password_hash:string} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO users
                 (role_id, full_name, nic, nic_photo_path, phone, email, address, password_hash, status)
             VALUES
                 (:role_id, :full_name, :nic, :nic_photo_path, :phone, :email, :address, :password_hash, 'pending')"
        );

        $statement->execute([
            'role_id'        => $data['role_id'],
            'full_name'      => $data['full_name'],
            'nic'            => $data['nic'],
            'nic_photo_path' => $data['nic_photo_path'],
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
     * Members this person may send a gift to: active members who accept
     * gifts and share an active division with them (§11.1). A moderator is a
     * verified member (§16.3) and can receive gifts too; the Sponsor Liaison,
     * admins and sponsors cannot.
     *
     * @return list<array<string, mixed>>
     */
    public function giftableExcept(int $id): array
    {
        return $this->select(
            "SELECT DISTINCT u.id, u.full_name
               FROM users u
               JOIN roles r            ON r.id = u.role_id
               JOIN user_divisions them ON them.user_id = u.id AND them.status = 'active'
               JOIN user_divisions mine ON mine.user_id = :me AND mine.status = 'active'
                                       AND mine.gn_division_id = them.gn_division_id
              WHERE u.id <> :id
                AND r.code IN ('member', 'moderator')
                AND u.status = 'active'
                AND u.gift_receive_enabled = 1
              ORDER BY u.full_name",
            ['me' => $id, 'id' => $id]
        );
    }

    /**
     * The moderator of a member's home division, or null when the account has
     * no home division or the division has no moderator.
     */
    public function homeModeratorName(int $userId): ?string
    {
        $name = $this->selectValue(
            "SELECT m.full_name
               FROM user_divisions ud
               JOIN gn_divisions d ON d.id = ud.gn_division_id
               JOIN users m        ON m.id = d.moderator_id
              WHERE ud.user_id = :id AND ud.membership_type = 'home'",
            ['id' => $userId]
        );

        return $name === false ? null : (string) $name;
    }

    /**
     * The longest-standing active account in a role, e.g. the Sponsor Liaison.
     */
    public function firstNameInRole(string $roleCode): ?string
    {
        $name = $this->selectValue(
            "SELECT u.full_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE r.code = :code AND u.status = 'active'
              ORDER BY u.id
              LIMIT 1",
            ['code' => $roleCode]
        );

        return $name === false ? null : (string) $name;
    }

    /**
     * The name a greeting uses, e.g. "T.H.K. Madushan" -> "Madushan".
     */
    public static function shortName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return end($parts) ?: $fullName;
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

    /** Members a moderator has verified and who can still take part — moderators are members too. */
    public function countVerifiedMembers(): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE r.code IN ('member', 'moderator') AND u.status = 'active'"
        );
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

    public function roleCode(int $id): string
    {
        return (string) ($this->selectValue(
            'SELECT r.code FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = :id',
            ['id' => $id]
        ) ?: 'member');
    }

    public function roleName(int $id): string
    {
        return (string) ($this->selectValue(
            'SELECT r.name FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = :id',
            ['id' => $id]
        ) ?: 'Member');
    }

    /**
     * Admin member directory, filtered by status, role code and free-text
     * search on name or home division.
     *
     * @return list<array<string, mixed>>
     */
    public function adminList(string $status, string $role, string $search): array
    {
        $where = '1=1';
        $params = [];

        if ($status !== '' && $status !== 'all') {
            $where .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        if ($role !== '') {
            $where .= ' AND r.code = :role';
            $params['role'] = $role;
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

    // ── Account security (module 1.1) ───────────────────────────────────────

    /**
     * The signed-in account with its password hash, for re-authentication
     * (password change, account closure). Never used to render a page.
     *
     * @return array<string, mixed>|null
     */
    public function findAccount(int $id): ?array
    {
        return $this->selectOne(
            'SELECT u.id, u.full_name, u.email, u.phone, u.address, u.password_hash,
                    u.password_changed_at, u.status, u.gift_receive_enabled, r.code AS role_code
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = :id',
            ['id' => $id]
        );
    }

    /**
     * What the per-request session check needs: is the account still usable,
     * and when did its password last change?
     *
     * @return array{status: string, password_changed_at: ?string, role_code: string}|null
     */
    public function sessionState(int $id): ?array
    {
        return $this->selectOne(
            'SELECT u.status, u.password_changed_at, r.code AS role_code
               FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.id = :id',
            ['id' => $id]
        );
    }

    /**
     * An active account a reset email may be sent to.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->selectOne(
            "SELECT id, full_name, email FROM users WHERE email = :email AND status = 'active' LIMIT 1",
            ['email' => $email]
        );
    }

    /** Store a new password hash and stamp the change, which ends older sessions. */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :hash, password_changed_at = NOW() WHERE id = :id'
        );
        $statement->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /** Upgrade a hash to the current cost without counting as a password change. */
    public function rehashPassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $statement->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    public function updateContact(int $id, string $fullName, string $phone, ?string $email): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET full_name = :name, phone = :phone, email = :email WHERE id = :id'
        );
        $statement->execute(['name' => $fullName, 'phone' => $phone, 'email' => $email, 'id' => $id]);
    }

    public function updateAddress(int $id, string $address): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET address = :address WHERE id = :id');
        $statement->execute(['address' => $address, 'id' => $id]);
    }

    public function setGiftReceive(int $id, bool $enabled): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET gift_receive_enabled = :on WHERE id = :id');
        $statement->execute(['on' => $enabled ? 1 : 0, 'id' => $id]);
    }

    public function emailTakenByOther(string $email, int $id): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id',
            ['email' => $email, 'id' => $id]
        ) > 0;
    }

    public function phoneTakenByOther(string $digits, int $id): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM users WHERE phone_digits = :digits AND id <> :id',
            ['digits' => $digits, 'id' => $id]
        ) > 0;
    }

    /**
     * Close an active account (Plan §17).
     *
     * @param string $status 'closed_standard' or 'closed_donation'
     *
     * @return bool false when it was not active
     */
    public function close(int $id, string $status): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE users SET status = :status, closed_at = NOW(), gift_receive_enabled = 0
              WHERE id = :id AND status = 'active'"
        );
        $statement->execute(['status' => $status, 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    /** Store a freshly computed trust score (Plan §6.3) — the cached column every page reads. */
    public function setTrustScore(int $id, int $score): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET trust_score = :score WHERE id = :id');
        $statement->execute(['score' => max(0, min(100, $score)), 'id' => $id]);
    }

    /** When membership began — the trust score's tenure factor. */
    public function joinedAt(int $id): ?string
    {
        $value = $this->selectValue('SELECT joined_at FROM users WHERE id = :id', ['id' => $id]);

        return $value === false || $value === null ? null : (string) $value;
    }

    /**
     * Every verified member and moderator still taking part — the nightly
     * trust-score refresh walks these.
     *
     * @return list<int>
     */
    public function activeMemberIds(): array
    {
        $rows = $this->select(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
              WHERE r.code IN ('member','moderator') AND u.status = 'active'
              ORDER BY u.id"
        );

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Active accounts in one role, e.g. every Admin to tell about a new dispute.
     *
     * @return list<int>
     */
    public function idsInRole(string $roleCode): array
    {
        $rows = $this->select(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
              WHERE r.code = :code AND u.status = 'active' ORDER BY u.id",
            ['code' => $roleCode]
        );

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * The moderator of a member's home division with a phone number to reach
     * them on, or null when there is none.
     *
     * @return array{name: string, phone: string}|null
     */
    public function homeModerator(int $userId): ?array
    {
        $row = $this->selectOne(
            "SELECT m.full_name, m.phone
               FROM user_divisions ud
               JOIN gn_divisions d ON d.id = ud.gn_division_id
               JOIN users m        ON m.id = d.moderator_id
              WHERE ud.user_id = :id AND ud.membership_type = 'home'",
            ['id' => $userId]
        );

        return $row === null ? null : ['name' => (string) $row['full_name'], 'phone' => (string) $row['phone']];
    }

    /**
     * Completed bookings this member took part in on items listed in one
     * division — the trust score's context line on profiles (§6.3.5).
     */
    public function completedInDivision(int $userId, int $divisionId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings b JOIN items i ON i.id = b.item_id
              WHERE (b.borrower_id = :a OR b.lender_id = :b) AND b.status = 'completed'
                AND i.gn_division_id = :division",
            ['a' => $userId, 'b' => $userId, 'division' => $divisionId]
        );
    }
}
