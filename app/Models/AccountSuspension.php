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

    /** Lock the current suspension row before an Admin reinstates its account. */
    public function lockOpenForUser(int $userId): ?array
    {
        return $this->selectOne(
            'SELECT id, reason, started_at
               FROM account_suspensions
              WHERE user_id = :user AND ended_at IS NULL
              ORDER BY started_at DESC, id DESC LIMIT 1 FOR UPDATE',
            ['user' => $userId]
        );
    }

    public function hasOpenForUser(int $userId): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM account_suspensions WHERE user_id = :user AND ended_at IS NULL',
            ['user' => $userId]
        ) > 0;
    }

    public function close(int $id): bool
    {
        return $this->execute(
            'UPDATE account_suspensions SET ended_at = NOW()
              WHERE id = :id AND ended_at IS NULL',
            ['id' => $id]
        ) === 1;
    }

    /** @return list<array<string, mixed>> */
    public function historyFor(int $userId): array
    {
        return $this->select(
            'SELECT s.reason, s.started_at, s.ended_at, admin.full_name AS admin_name
               FROM account_suspensions s
          LEFT JOIN users admin ON admin.id = s.suspended_by
              WHERE s.user_id = :user
              ORDER BY s.started_at DESC, s.id DESC',
            ['user' => $userId]
        );
    }
}
