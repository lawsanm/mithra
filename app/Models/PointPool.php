<?php

declare(strict_types=1);

/**
 * point_pools — the six balances on the Transparency Dashboard.
 */
final class PointPool extends BaseModel
{
    protected string $table = 'point_pools';
    protected string $columns = 'pool_code, name, balance';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->select(
            "SELECT pool_code, name, balance FROM point_pools
              ORDER BY FIELD(pool_code,'sponsor','aid','reserve','in_flight','retired','member_wallets')"
        );
    }

    public function balance(string $poolCode): int
    {
        return (int) $this->selectValue(
            'SELECT balance FROM point_pools WHERE pool_code = :code',
            ['code' => $poolCode]
        );
    }

    public function totalBalance(): int
    {
        return (int) $this->selectValue('SELECT COALESCE(SUM(balance), 0) FROM point_pools');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastInvariantRun(): ?array
    {
        return $this->selectOne(
            "SELECT status, finished_at, notes FROM cron_runs
              WHERE job_name = 'check_invariant' ORDER BY started_at DESC LIMIT 1"
        );
    }
}
