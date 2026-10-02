<?php

declare(strict_types=1);

final class Dispute extends BaseModel
{
    protected string $table = 'disputes';
    protected string $columns = 'id, booking_id, damage_claim_id, raised_by, admin_id, reason, status, resolution, ruling_at, created_at';

    /**
     * Open a dispute for the Admin. Callers have checked who may raise it.
     */
    public function open(?int $bookingId, ?int $claimId, int $raisedBy, string $reason): int
    {
        return $this->insert(
            "INSERT INTO disputes (booking_id, damage_claim_id, raised_by, reason, status)
             VALUES (:booking, :claim, :raised_by, :reason, 'open')",
            ['booking' => $bookingId, 'claim' => $claimId, 'raised_by' => $raisedBy, 'reason' => $reason]
        );
    }

    public function countOpen(): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM disputes WHERE status = \'open\''
        );
    }

    public function countPastTimer(): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM disputes
              WHERE status = \'open\'
                AND created_at < NOW() - INTERVAL 7 DAY'
        );
    }

    /** @return list<array<string, mixed>> */
    public function openList(): array
    {
        return $this->select(
            'SELECT dp.id, dp.reason, dp.status, dp.created_at,
                    b.id AS booking_id,
                    i.title AS item_title,
                    borrower.full_name AS borrower_name,
                    lender.full_name AS lender_name,
                    d.name AS division_name,
                    TIMESTAMPDIFF(DAY, dp.created_at, NOW()) AS days_open
               FROM disputes dp
               LEFT JOIN bookings b ON b.id = dp.booking_id
               LEFT JOIN items i ON i.id = b.item_id
               LEFT JOIN users borrower ON borrower.id = b.borrower_id
               LEFT JOIN users lender ON lender.id = b.lender_id
               LEFT JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE dp.status = \'open\'
              ORDER BY dp.created_at ASC'
        );
    }

    /**
     * Most recently opened disputes, for the admin notification feed.
     *
     * @return list<array<string, mixed>>
     */
    public function recentOpen(int $limit = 10): array
    {
        return $this->selectPage(
            "SELECT dp.id, dp.reason, dp.status, dp.created_at,
                    borrower.full_name AS borrower_name, lender.full_name AS lender_name
               FROM disputes dp
               LEFT JOIN bookings b ON b.id = dp.booking_id
               LEFT JOIN users borrower ON borrower.id = b.borrower_id
               LEFT JOIN users lender ON lender.id = b.lender_id
              WHERE dp.status = 'open'
              ORDER BY dp.created_at DESC",
            [],
            1,
            $limit
        );
    }

    /** @return array<string, mixed>|null */
    public function findWithHistory(int $id): ?array
    {
        return $this->selectOne(
            'SELECT dp.id, dp.reason, dp.status, dp.resolution, dp.ruling_at, dp.created_at,
                    b.id AS booking_id, b.start_date, b.end_date,
                    i.title AS item_title, i.id AS item_id,
                    borrower.id AS borrower_id, borrower.full_name AS borrower_name,
                    lender.id AS lender_id, lender.full_name AS lender_name,
                    d.name AS division_name,
                    mod_user.full_name AS moderator_name
               FROM disputes dp
               LEFT JOIN bookings b ON b.id = dp.booking_id
               LEFT JOIN items i ON i.id = b.item_id
               LEFT JOIN users borrower ON borrower.id = b.borrower_id
               LEFT JOIN users lender ON lender.id = b.lender_id
               LEFT JOIN gn_divisions d ON d.id = i.gn_division_id
               LEFT JOIN users mod_user ON mod_user.id = d.moderator_id
              WHERE dp.id = :id',
            ['id' => $id]
        );
    }

    /**
     * The latest dispute on a booking, for the booking page.
     *
     * @return array<string, mixed>|null
     */
    public function latestForBooking(int $bookingId): ?array
    {
        return $this->selectOne(
            'SELECT dp.id, dp.booking_id, dp.damage_claim_id, dp.raised_by, dp.admin_id, dp.reason, dp.status,
                    dp.resolution, dp.ruling_at, dp.created_at, u.full_name AS raised_by_name
               FROM disputes dp JOIN users u ON u.id = dp.raised_by
              WHERE dp.booking_id = :booking
              ORDER BY dp.id DESC LIMIT 1',
            ['booking' => $bookingId]
        );
    }

    /** Open disputes on one claim — at most one is allowed (§19). */
    public function countOpenForClaim(int $claimId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM disputes WHERE damage_claim_id = :claim AND status = 'open'",
            ['claim' => $claimId]
        );
    }

    /**
     * One dispute, row-locked, for its raiser's edit or withdrawal.
     *
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, booking_id, damage_claim_id, raised_by, admin_id, reason, status
               FROM disputes WHERE id = :id FOR UPDATE',
            ['id' => $id]
        );
    }

    /** Change the reason while no Admin has picked the dispute up. */
    public function updateReason(int $id, string $reason): bool
    {
        return $this->execute(
            "UPDATE disputes SET reason = :reason WHERE id = :id AND status = 'open' AND admin_id IS NULL",
            ['reason' => $reason, 'id' => $id]
        ) === 1;
    }

    /** The raiser withdraws it. */
    public function withdraw(int $id): bool
    {
        return $this->execute(
            "UPDATE disputes SET status = 'closed', resolution = 'Withdrawn by the member who raised it.'
              WHERE id = :id AND status = 'open'",
            ['id' => $id]
        ) === 1;
    }

    /** Open disputes this member raised or is party to — account closure waits for them (Plan §17). */
    public function countOpenFor(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM disputes dp LEFT JOIN bookings b ON b.id = dp.booking_id
              WHERE dp.status = 'open'
                AND (dp.raised_by = :raiser OR b.borrower_id = :borrower OR b.lender_id = :lender)",
            ['raiser' => $memberId, 'borrower' => $memberId, 'lender' => $memberId]
        );
    }
}
