<?php

declare(strict_types=1);

/**
 * account_suspensions — every suspension an account has had (Plan §6.3.3).
 * Written by the Admin portal; the trust score reads the count.
 */
final class AccountSuspension extends BaseModel
{
    protected string $table = 'account_suspensions';
    protected string $columns = 'id, user_id, suspended_by, reason, started_at, ended_at';

    /** Suspensions that have ended — "past" suspensions, each −10 on the trust score. */
    public function countPastFor(int $userId): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM account_suspensions WHERE user_id = :user AND ended_at IS NOT NULL',
            ['user' => $userId]
        );
    }

    /** Record an Admin suspension in the account's append-only history. */
    public function record(int $userId, int $adminId, string $reason): void
    {
        $this->insert(
            'INSERT INTO account_suspensions (user_id, suspended_by, reason)
             VALUES (:user, :admin, :reason)',
            ['user' => $userId, 'admin' => $adminId, 'reason' => $reason]
        );
    }
}
