<?php

declare(strict_types=1);

/**
 * member_wallets + point_ledger — the Wallet screen and the dashboard balance.
 *
 * Balances are read from the cached wallet row (O(1)); the ledger is the
 * append-only source of truth behind them.
 */
final class Wallet extends BaseModel
{
    protected string $table = 'member_wallets';
    protected string $columns = 'user_id, balance, bond_locked';

    /**
     * Open an empty wallet for a member who has just been verified. Doing
     * nothing when the row already exists keeps approval idempotent — the
     * balance of an existing wallet is never touched here (§8).
     *
     * The 200-point welcome bonus (Plan §6.4) is not credited here: points
     * only move through LedgerService, which writes the append-only ledger
     * entry in the same transaction as the approval.
     */
    public function openFor(int $memberId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO member_wallets (user_id, balance, bond_locked)
             VALUES (:id, 0, 0)
             ON DUPLICATE KEY UPDATE user_id = user_id'
        );

        $statement->execute(['id' => $memberId]);
    }

    public function balance(int $memberId): int
    {
        return (int) $this->selectValue(
            'SELECT balance FROM member_wallets WHERE user_id = :id',
            ['id' => $memberId]
        );
    }

    public function escrow(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COALESCE(SUM(CASE WHEN l.to_pool_code = 'in_flight' THEN l.amount
                        WHEN l.from_pool_code = 'in_flight' THEN -l.amount ELSE 0 END), 0)
               FROM point_ledger l JOIN bookings b ON b.id = l.booking_id
              WHERE b.borrower_id = :member
                AND b.status IN ('accepted','awaiting_handover','in_progress','awaiting_return','pending_moderator')",
            ['member' => $memberId]
        );
    }

    /**
     * Points earned this month, for the dashboard's "Earned N this month".
     */
    public function earnedThisMonth(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COALESCE(SUM(amount), 0) FROM point_ledger
              WHERE to_user_id = :id
                AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            ['id' => $memberId]
        );
    }

    /** Activity rows per page (Rules/CONVENTIONS.md §9). */
    public const PER_PAGE = 20;

    /**
     * Wallet activity: ledger rows touching this member, newest first, one
     * page at a time, optionally limited to one PointLedger::GROUPS filter.
     *
     * @return list<array<string, mixed>>
     */
    public function activity(int $memberId, int $page = 1, string $group = ''): array
    {
        [$filter, $reasons] = self::reasonFilter($group);

        $statement = $this->pdo->prepare(
            "SELECT l.id, l.amount, l.reason, l.created_at, l.booking_id,
                    (l.to_user_id = :to_id) AS incoming,
                    i.title AS item_title,
                    g.reason AS gift_reason,
                    sender.full_name    AS sender_name,
                    recipient.full_name AS recipient_name
               FROM point_ledger l
          LEFT JOIN bookings b       ON b.id = l.booking_id
          LEFT JOIN items i          ON i.id = b.item_id
          LEFT JOIN gifts g          ON g.id = l.gift_id
          LEFT JOIN users sender     ON sender.id = l.from_user_id
          LEFT JOIN users recipient  ON recipient.id = l.to_user_id
              WHERE (l.from_user_id = :from_id OR l.to_user_id = :to_id2)" . $filter . "
              ORDER BY l.created_at DESC, l.id DESC
              LIMIT :take OFFSET :skip"
        );
        $statement->bindValue(':to_id', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':from_id', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':to_id2', $memberId, PDO::PARAM_INT);
        foreach ($reasons as $name => $reason) {
            $statement->bindValue(':' . $name, $reason);
        }
        $statement->bindValue(':take', self::PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':skip', (max(1, $page) - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countActivity(int $memberId, string $group = ''): int
    {
        [$filter, $reasons] = self::reasonFilter($group);

        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM point_ledger l WHERE (l.from_user_id = :a OR l.to_user_id = :b)' . $filter,
            ['a' => $memberId, 'b' => $memberId] + $reasons
        );
    }

    /**
     * A fixed set of placeholders for one filter's ledger reasons.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function reasonFilter(string $group): array
    {
        if (!isset(PointLedger::GROUPS[$group])) {
            return ['', []];
        }

        $names   = [];
        $reasons = [];
        foreach (PointLedger::GROUPS[$group] as $index => $reason) {
            $names[]                    = ':reason' . $index;
            $reasons['reason' . $index] = $reason;
        }

        return [' AND l.reason IN (' . implode(', ', $names) . ')', $reasons];
    }

    /**
     * The spendable balance, row-locked until the surrounding transaction ends
     * (Rules/CONVENTIONS.md §8). Null when the member has no wallet yet.
     */
    public function lockBalance(int $memberId): ?int
    {
        $balance = $this->selectValue(
            'SELECT balance FROM member_wallets WHERE user_id = :id FOR UPDATE',
            ['id' => $memberId]
        );

        return $balance === false ? null : (int) $balance;
    }

    /**
     * Apply a signed change to a cached wallet balance. The UNSIGNED column
     * refuses anything that would go negative (Plan §7.7).
     */
    public function adjust(int $memberId, int $delta): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE member_wallets SET balance = balance + :delta WHERE user_id = :id'
        );

        $statement->execute(['delta' => $delta, 'id' => $memberId]);
    }

    /** The locked moderator conduct bond (Plan §16.7); zero for everyone else. */
    public function bondLocked(int $memberId): int
    {
        return (int) $this->selectValue(
            'SELECT bond_locked FROM member_wallets WHERE user_id = :id',
            ['id' => $memberId]
        );
    }
}
