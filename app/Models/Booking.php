<?php

declare(strict_types=1);

/**
 * bookings — My Bookings, Booking Detail and the dashboard's borrowing rows.
 */
final class Booking extends BaseModel
{
    protected string $table = 'bookings';
    protected string $columns = 'id, item_id, borrower_id, lender_id, start_date, end_date, rental_charge, status';

    /** Statuses that count as a live booking, for both roles. */
    private const OPEN_STATES = "('requested','accepted','awaiting_handover','in_progress','awaiting_return','pending_moderator')";

    /** Statuses where the borrower physically holds the item. */
    private const HOLDING_STATES = "('awaiting_handover','in_progress','awaiting_return')";

    /**
     * @return list<array<string, mixed>>
     */
    public function forMember(int $memberId, string $role): array
    {
        // Whitelisted, never interpolated from request input.
        $column = $role === 'lender' ? 'b.lender_id' : 'b.borrower_id';
        $other  = $role === 'lender' ? 'b.borrower_id' : 'b.lender_id';

        return $this->select(
            "SELECT b.id, b.start_date, b.end_date, b.rental_charge, b.status,
                    i.title AS item_title, o.full_name AS counterparty
               FROM bookings b
               JOIN items i ON i.id = b.item_id
               JOIN users o ON o.id = {$other}
              WHERE {$column} = :member AND b.status IN " . self::OPEN_STATES . '
              ORDER BY b.end_date',
            ['member' => $memberId]
        );
    }

    public function countForMember(int $memberId, string $role): int
    {
        $column = $role === 'lender' ? 'lender_id' : 'borrower_id';

        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings WHERE {$column} = :member AND status IN " . self::OPEN_STATES,
            ['member' => $memberId]
        );
    }

    /**
     * Dashboard: what the member currently has out.
     *
     * @return list<array<string, mixed>>
     */
    public function activeBorrowings(int $memberId): array
    {
        return $this->select(
            "SELECT b.id, b.start_date, b.end_date, b.status,
                    i.title AS item_title, l.full_name AS lender_name
               FROM bookings b
               JOIN items i ON i.id = b.item_id
               JOIN users l ON l.id = b.lender_id
              WHERE b.borrower_id = :member AND b.status IN " . self::HOLDING_STATES . '
              ORDER BY b.end_date',
            ['member' => $memberId]
        );
    }

    public function countActiveBorrowings(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings
              WHERE borrower_id = :member AND status IN " . self::HOLDING_STATES,
            ['member' => $memberId]
        );
    }

    public function countDueTomorrow(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings
              WHERE borrower_id = :member
                AND status IN " . self::HOLDING_STATES . '
                AND end_date = CURDATE() + INTERVAL 1 DAY',
            ['member' => $memberId]
        );
    }

    /**
     * Booking Detail, with the counterparty and the handover baseline.
     *
     * @return array<string, mixed>|null
     */
    public function findForDetail(int $id): ?array
    {
        return $this->selectOne(
            "SELECT b.id, b.start_date, b.end_date, b.rate_basis, b.agreed_rate, b.rental_charge,
                    b.late_buffer, b.status, b.requested_at, b.accepted_at,
                    DATEDIFF(b.end_date, b.start_date) + 1 AS days,
                    GREATEST(DATEDIFF(CURDATE(), b.end_date), 0) AS days_overdue,
                    i.title AS item_title, i.declared_value,
                    l.id AS lender_id, l.full_name AS lender_name, l.trust_score AS lender_trust,
                    l.status AS lender_status,
                    (SELECT COUNT(*) FROM bookings x WHERE x.lender_id = l.id AND x.status = 'completed')
                      AS lender_lends,
                    h.lender_notes, h.borrower_notes,
                    COALESCE(JSON_LENGTH(h.lender_photos), 0)   AS lender_photo_count,
                    COALESCE(JSON_LENGTH(h.borrower_photos), 0) AS borrower_photo_count
               FROM bookings b
               JOIN items i ON i.id = b.item_id
               JOIN users l ON l.id = b.lender_id
          LEFT JOIN handover_records h ON h.booking_id = b.id
              WHERE b.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Claim summary for the pending-moderator state.
     *
     * @return array<string, mixed>|null
     */
    public function damageClaim(int $bookingId): ?array
    {
        return $this->selectOne(
            "SELECT dc.severity, dc.description, dc.created_at,
                    u.full_name AS raised_by_name, i.declared_value
               FROM damage_claims dc
               JOIN bookings b ON b.id = dc.booking_id
               JOIN items i    ON i.id = b.item_id
               JOIN users u    ON u.id = dc.raised_by
              WHERE dc.booking_id = :id
              ORDER BY dc.created_at DESC
              LIMIT 1",
            ['id' => $bookingId]
        );
    }
}
