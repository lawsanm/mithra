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
     * The pool's balance, row-locked until the surrounding transaction ends
     * (Rules/CONVENTIONS.md §8). Call only inside a transaction.
     */
    public function lockBalance(string $poolCode): int
    {
        return (int) $this->selectValue(
            'SELECT balance FROM point_pools WHERE pool_code = :code FOR UPDATE',
            ['code' => $poolCode]
        );
    }

    /**
     * Apply a signed change to a pool's cached balance. The ledger row written
     * in the same transaction is the record of truth.
     */
    public function adjust(string $poolCode, int $delta): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE point_pools SET balance = balance + :delta WHERE pool_code = :code'
        );

        $statement->execute(['delta' => $delta, 'code' => $poolCode]);
    }
}
