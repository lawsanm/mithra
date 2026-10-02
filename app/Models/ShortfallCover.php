<?php

declare(strict_types=1);

/**
 * Reserve Pool shortfall covers, read from the append-only point_ledger.
 *
 * When a borrower cannot cover an agreed late fee or penalty, they pay what
 * they have and the Reserve Pool pays the lender the rest at that moment
 * (Plan §7.7). Members never carry debt, so there is nothing to write off —
 * each cover is simply a ledger row with reason 'shortfall_cover'.
 */
final class ShortfallCover extends BaseModel
{
    protected string $table = 'point_ledger';
    protected string $columns = 'id, amount, reason, created_at';

    /**
     * The latest covers, with the booking's borrower (whose trust score it
     * counts against for 12 months, §6.3.3) and the lender it paid.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            "SELECT l.id, l.amount, l.created_at, l.booking_id,
                    lender.full_name   AS lender_name,
                    borrower.full_name AS borrower_name,
                    d.name             AS division_name
               FROM point_ledger l
          LEFT JOIN users lender   ON lender.id = l.to_user_id
          LEFT JOIN bookings b     ON b.id = l.booking_id
          LEFT JOIN users borrower ON borrower.id = b.borrower_id
          LEFT JOIN items i        ON i.id = b.item_id
          LEFT JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE l.reason = 'shortfall_cover'
              ORDER BY l.created_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array{total_pts: int, covers: int} */
    public function yearStats(): array
    {
        $row = $this->selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_pts, COUNT(*) AS covers
               FROM point_ledger
              WHERE reason = 'shortfall_cover' AND YEAR(created_at) = YEAR(CURDATE())"
        ) ?? [];

        return ['total_pts' => (int) ($row['total_pts'] ?? 0), 'covers' => (int) ($row['covers'] ?? 0)];
    }

    /** Points moved from the Sponsor Pool into the Reserve this year (§7.7). */
    public function topUpsThisYear(): int
    {
        return (int) $this->selectValue(
            "SELECT COALESCE(SUM(amount), 0) FROM point_ledger
              WHERE reason = 'reserve_topup' AND YEAR(created_at) = YEAR(CURDATE())"
        );
    }

    /**
     * Covers the Reserve paid on this member's behalf as a borrower in the
     * last 12 months — each one −5 on their trust score (§6.3.3).
     */
    public function countAgainstWithinYear(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM point_ledger l JOIN bookings b ON b.id = l.booking_id
              WHERE l.reason = 'shortfall_cover' AND b.borrower_id = :member
                AND l.created_at >= NOW() - INTERVAL 12 MONTH",
            ['member' => $memberId]
        );
    }
}
