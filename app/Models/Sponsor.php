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
}
