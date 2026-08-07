<?php

declare(strict_types=1);

/**
 * sponsors / sponsor_purchases — contributions listed on the Transparency page.
 */
final class Sponsor extends BaseModel
{
    protected string $table = 'sponsors';
    protected string $columns = 'id, company_name, total_injected, active';

    /**
     * @return list<array<string, mixed>>
     */
    public function recentContributions(int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.company_name, p.points_credited, p.sponsor_pool_pct, p.aid_pool_pct, p.recorded_at
               FROM sponsor_purchases p JOIN sponsors s ON s.id = p.sponsor_id
              ORDER BY p.recorded_at DESC
              LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Every sponsor cash-to-points inflow, newest first, for the admin
     * Sponsor Fund Ledger.
     *
     * @return list<array<string, mixed>>
     */
    public function allInflows(): array
    {
        return $this->select(
            'SELECT sp.recorded_at, s.company_name, sp.receipt_number, sp.cash_amount,
                    sp.points_credited, sp.sponsor_pool_pct, sp.aid_pool_pct
               FROM sponsor_purchases sp
               JOIN sponsors s ON s.id = sp.sponsor_id
              ORDER BY sp.recorded_at DESC'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentInjections(int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            'SELECT sp.receipt_number, sp.points_credited, sp.recorded_at, s.company_name
               FROM sponsor_purchases sp
               JOIN sponsors s ON s.id = sp.sponsor_id
              ORDER BY sp.recorded_at DESC
              LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
