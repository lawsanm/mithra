<?php

declare(strict_types=1);

/**
 * sponsors / sponsor_contributions — contributions listed on the Transparency
 * page and the Admin's sponsor fund ledger.
 *
 * Each contribution is cash recorded offline by the Sponsor Liaison and turned
 * into points at 1 rupee = 1 point with no deductions, split General (Sponsor
 * Pool) / Aid (Aid Pool) as the sponsor chose (Plan §15.2–15.3). `points` below
 * is general_points + aid_points, which always equals cash_amount.
 */
final class Sponsor extends BaseModel
{
    protected string $table = 'sponsors';
    protected string $columns = 'id, company_name, total_contributed, active';

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
}
