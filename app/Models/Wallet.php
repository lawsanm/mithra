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

    public function balance(int $memberId): int
    {
        return (int) $this->selectValue(
            'SELECT balance FROM member_wallets WHERE user_id = :id',
            ['id' => $memberId]
        );
    }

    /**
     * Points currently held in escrow against this member's live bookings.
     */
    public function escrowHeld(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COALESCE(SUM(rental_charge), 0) FROM bookings
              WHERE borrower_id = :id
                AND status IN ('awaiting_handover','in_progress','awaiting_return')",
            ['id' => $memberId]
        );
    }

    public function countEscrowBookings(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings
              WHERE borrower_id = :id
                AND status IN ('awaiting_handover','in_progress','awaiting_return')",
            ['id' => $memberId]
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

    /**
     * Wallet activity: every ledger row touching this member, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function activity(int $memberId, int $limit = 20): array
    {
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
              WHERE l.from_user_id = :from_id OR l.to_user_id = :to_id2
              ORDER BY l.created_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':to_id', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':from_id', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':to_id2', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
