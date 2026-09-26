<?php

declare(strict_types=1);

/**
 * sponsors / sponsor_contributions — the sponsor profiles the Liaison manages
 * (Plan §20.4 module 4.2), and the contributions listed on the Transparency
 * page and the Admin's sponsor fund ledger.
 *
 * Each contribution is cash recorded offline by the Sponsor Liaison and turned
 * into points at 1 rupee = 1 point with no deductions, split General (Sponsor
 * Pool) / Aid (Aid Pool) as the sponsor chose (Plan §15.2–15.3). `points` below
 * is general_points + aid_points, which always equals cash_amount.
 */
final class Sponsor extends BaseModel
{
    public const PER_PAGE = 20;

    public function forUser(int $userId): ?array
    {
        return $this->selectOne('SELECT ' . $this->columns . ' FROM sponsors WHERE user_id = :id', ['id' => $userId]);
    }

    public function summaries(): array
    {
        return $this->select(
            'SELECT s.id, s.company_name, s.contact_email, s.agreement_status, s.active,
                    COALESCE(c.cash, 0) AS cash, COALESCE(c.general, 0) AS general,
                    COALESCE(c.aid, 0) AS aid, COALESCE(c.purchases, 0) AS purchases
               FROM sponsors s LEFT JOIN
                    (SELECT sponsor_id, SUM(cash_amount) AS cash, SUM(general_points) AS general,
                            SUM(aid_points) AS aid, COUNT(*) AS purchases
                       FROM sponsor_contributions GROUP BY sponsor_id) c ON c.sponsor_id = s.id
              ORDER BY s.company_name'
        );
    }

    protected string $table = 'sponsors';
    protected string $columns = 'id, user_id, company_name, contact_name, contact_phone, contact_email,
                                 agreement_status, agreement_details, internal_notes, total_contributed,
                                 active, created_at, updated_at';

    /**
     * One page of the Liaison's sponsor list.
     *
     * @param string $agreement '' for every agreement status
     * @param string $sort      contribution (default), name or recently_added
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, string $agreement, string $sort, int $page = 1): array
    {
        [$sql, $params] = $this->searchWhere(
            'SELECT s.id, s.company_name, s.contact_name, s.contact_email, s.agreement_status,
                    s.total_contributed, s.active
               FROM sponsors s',
            $query,
            $agreement
        );

        // Sort keys map to fixed SQL, never to the request's text (§7.1).
        $sql .= match ($sort) {
            'name'           => ' ORDER BY s.company_name, s.id',
            'recently_added' => ' ORDER BY s.created_at DESC, s.id DESC',
            default          => ' ORDER BY s.total_contributed DESC, s.company_name',
        };

        // LIMIT/OFFSET must bind as integers, which execute($params) cannot do.
        $statement = $this->pdo->prepare($sql . ' LIMIT :take OFFSET :skip');

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':take', self::PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':skip', (max(1, $page) - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Total rows behind the current list filters, for paging.
     */
    public function countSearch(string $query, string $agreement): int
    {
        [$sql, $params] = $this->searchWhere('SELECT COUNT(*) FROM sponsors s', $query, $agreement);

        return (int) $this->selectValue($sql, $params);
    }

    /**
     * One sponsor's profile with a summary of what they have contributed, and
     * the status of its login account (NULL when the company has none).
     *
     * @return array<string, mixed>|null
     */
    public function findProfile(int $id): ?array
    {
        return $this->selectOne(
            'SELECT s.id, s.company_name, s.contact_name, s.contact_phone, s.contact_email,
                    s.agreement_status, s.agreement_details, s.internal_notes,
                    s.total_contributed, s.active, s.created_at, s.user_id,
                    u.status AS account_status, u.email AS login_email,
                    COUNT(c.id) AS contribution_count,
                    MAX(c.recorded_at) AS last_contribution_at
               FROM sponsors s
               LEFT JOIN users u ON u.id = s.user_id
               LEFT JOIN sponsor_contributions c ON c.sponsor_id = s.id
              WHERE s.id = :id
              GROUP BY s.id',
            ['id' => $id]
        );
    }

    /**
     * Is another sponsor already on file under this company name? Compared
     * case-insensitively by the collation.
     */
    public function nameTaken(string $companyName, int $exceptId = 0): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM sponsors WHERE company_name = :name AND id <> :id',
            ['name' => $companyName, 'id' => $exceptId]
        ) > 0;
    }

    /**
     * @param array{company_name: string, contact_name: ?string, contact_phone: ?string,
     *              contact_email: ?string, agreement_status: string,
     *              agreement_details: ?string, internal_notes: ?string} $profile
     */
    public function create(array $profile): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sponsors
                    (company_name, contact_name, contact_phone, contact_email,
                     agreement_status, agreement_details, internal_notes, active)
             VALUES (:company_name, :contact_name, :contact_phone, :contact_email,
                     :agreement_status, :agreement_details, :internal_notes, 1)'
        );
        $statement->execute($profile);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * A sponsor the Liaison onboards together with its login account.
     *
     * @param array{company_name: string, contact_name: ?string, contact_phone: ?string,
     *              contact_email: ?string, agreement_status: string,
     *              agreement_details: ?string, internal_notes: ?string} $profile
     */
    public function createWithLogin(array $profile, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sponsors
                    (user_id, company_name, contact_name, contact_phone, contact_email,
                     agreement_status, agreement_details, internal_notes, active)
             VALUES (:user_id, :company_name, :contact_name, :contact_phone, :contact_email,
                     :agreement_status, :agreement_details, :internal_notes, 1)'
        );
        $statement->execute($profile + ['user_id' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{company_name: string, contact_name: ?string, contact_phone: ?string,
     *              contact_email: ?string, agreement_status: string,
     *              agreement_details: ?string, internal_notes: ?string} $profile
     */
    public function updateProfile(int $id, array $profile): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sponsors
                SET company_name = :company_name, contact_name = :contact_name,
                    contact_phone = :contact_phone, contact_email = :contact_email,
                    agreement_status = :agreement_status, agreement_details = :agreement_details,
                    internal_notes = :internal_notes
              WHERE id = :id'
        );
        $statement->execute($profile + ['id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $statement = $this->pdo->prepare('UPDATE sponsors SET active = :active WHERE id = :id');
        $statement->execute(['id' => $id, 'active' => $active ? 1 : 0]);
    }

    /**
     * Active sponsors by name, for a "which sponsor helped" picker.
     *
     * @return list<array{id: int, company_name: string}>
     */
    public function activeNames(): array
    {
        return $this->select('SELECT id, company_name FROM sponsors WHERE active = 1 ORDER BY company_name');
    }

    public function isActive(int $id): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM sponsors WHERE id = :id AND active = 1',
            ['id' => $id]
        ) === 1;
    }

    /** The company a sponsor login account represents, or null when none is linked. */
    public function companyForUser(int $userId): ?string
    {
        $name = $this->selectValue('SELECT company_name FROM sponsors WHERE user_id = :id', ['id' => $userId]);

        return $name === false ? null : (string) $name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentContributions(int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.company_name, c.receipt_number, c.general_points, c.aid_points,
                    (c.general_points + c.aid_points) AS points, c.recorded_at
               FROM sponsor_contributions c JOIN sponsors s ON s.id = c.sponsor_id
              ORDER BY c.recorded_at DESC
              LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Every sponsor contribution, newest first, for the admin Sponsor Fund
     * Ledger.
     *
     * @return list<array<string, mixed>>
     */
    public function allContributions(): array
    {
        return $this->select(
            'SELECT c.recorded_at, s.company_name, c.receipt_number, c.cash_amount,
                    c.general_points, c.aid_points, (c.general_points + c.aid_points) AS points
               FROM sponsor_contributions c
               JOIN sponsors s ON s.id = c.sponsor_id
              ORDER BY c.recorded_at DESC'
        );
    }

    /**
     * The list page's WHERE clause, shared by the page query and its count.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function searchWhere(string $select, string $query, string $agreement): array
    {
        $conditions = [];
        $params     = [];

        if ($query !== '') {
            // Native prepares forbid reusing one placeholder, so bind once per column.
            $conditions[] = '(s.company_name LIKE :q_name OR s.contact_name LIKE :q_contact OR s.contact_email LIKE :q_email)';
            $params['q_name']    = '%' . $query . '%';
            $params['q_contact'] = '%' . $query . '%';
            $params['q_email']   = '%' . $query . '%';
        }

        if ($agreement !== '') {
            $conditions[] = 's.agreement_status = :agreement';
            $params['agreement'] = $agreement;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$select . $where, $params];
    }
}
