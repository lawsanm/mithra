<?php

declare(strict_types=1);

final class Dispute extends BaseModel
{
    protected string $table = 'disputes';
    protected string $columns = 'id, booking_id, damage_claim_id, raised_by, admin_id, reason, status, resolution, ruling_points, ruling_at, created_at';

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
    public function openPage(int $page): array
    {
        return $this->selectPage(
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
              ORDER BY dp.created_at ASC, dp.id ASC',
            [],
            $page,
            20
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
     * Full case material for the Admin's final review (Proposal §10.2, §16.5).
     *
     * @return array<string, mixed>|null
     */
    public function reviewDetails(int $id): ?array
    {
        return $this->selectOne(
            'SELECT dp.id, dp.booking_id, dp.damage_claim_id, dp.raised_by, dp.admin_id,
                    dp.reason, dp.status, dp.resolution, dp.ruling_points, dp.ruling_at, dp.created_at,
                    b.status AS booking_status, b.start_date, b.end_date, b.borrower_id, b.lender_id,
                    i.id AS item_id, i.title AS item_title, i.declared_value,
                    borrower.full_name AS borrower_name, lender.full_name AS lender_name,
                    d.name AS division_name, d.moderator_id,
                    c.severity, c.description AS claim_description, c.proposed_penalty,
                    c.evidence_photos, c.track AS claim_track, c.status AS claim_status,
                    c.borrower_response, c.created_at AS claim_created_at,
                    h.lender_photos AS handover_lender_photos,
                    h.borrower_photos AS handover_borrower_photos,
                    h.lender_notes AS handover_lender_notes,
                    h.borrower_notes AS handover_borrower_notes,
                    r.return_at, r.lender_photos AS return_lender_photos,
                    r.borrower_photos AS return_borrower_photos,
                    r.lender_notes AS return_lender_notes,
                    r.borrower_notes AS return_borrower_notes,
                    mr.outcome_category, mr.penalty_points AS moderator_penalty,
                    mr.notes AS moderator_notes, mr.met_at,
                    mr.lender_signoff_at, mr.borrower_signoff_at, mr.closed_at AS resolution_closed_at,
                    mod_user.full_name AS moderator_name
               FROM disputes dp
          LEFT JOIN bookings b ON b.id = dp.booking_id
          LEFT JOIN items i ON i.id = b.item_id
          LEFT JOIN users borrower ON borrower.id = b.borrower_id
          LEFT JOIN users lender ON lender.id = b.lender_id
          LEFT JOIN gn_divisions d ON d.id = i.gn_division_id
          LEFT JOIN damage_claims c ON c.id = dp.damage_claim_id
          LEFT JOIN handover_records h ON h.booking_id = b.id
          LEFT JOIN return_records r ON r.booking_id = b.id
          LEFT JOIN moderator_resolutions mr ON mr.damage_claim_id = c.id
          LEFT JOIN users mod_user ON mod_user.id = mr.moderator_id
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
                    dp.resolution, dp.ruling_points, dp.ruling_at, dp.created_at, u.full_name AS raised_by_name
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

    /** Lock an open dispute before applying its one final Admin ruling. */
    public function lockForRuling(int $id): ?array
    {
        return $this->selectOne(
            'SELECT id, booking_id, damage_claim_id, status
               FROM disputes WHERE id = :id FOR UPDATE',
            ['id' => $id]
        );
    }

    /** Persist the Admin's one-time ruling after its settlement succeeded. */
    public function recordRuling(int $id, int $adminId, int $points, string $rationale): bool
    {
        return $this->execute(
            "UPDATE disputes
                SET status = 'ruled', admin_id = :admin, ruling_points = :points,
                    resolution = :resolution, ruling_at = NOW()
              WHERE id = :id AND status = 'open'",
            ['admin' => $adminId, 'points' => $points, 'resolution' => $rationale, 'id' => $id]
        ) === 1;
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
