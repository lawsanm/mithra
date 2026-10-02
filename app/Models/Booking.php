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
                    i.title AS item_title, o.full_name AS counterparty,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo
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
                    i.title AS item_title, l.full_name AS lender_name,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo
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

    /** Bookings currently live anywhere in the platform (admin dashboard stat). */
    public function countActive(): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings WHERE status IN ('in_progress', 'awaiting_return', 'awaiting_handover')"
        );
    }

    /** Rental charge held in escrow across all currently active bookings. */
    public function activeEscrowPoints(): int
    {
        return (int) $this->selectValue(
            "SELECT COALESCE(SUM(rental_charge), 0) FROM bookings
              WHERE status IN ('in_progress', 'awaiting_return', 'awaiting_handover')"
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
     * Bookings still running against one item. A listing with any of these
     * cannot be archived — the rental history has to stay reachable.
     */
    public function countOpenForItem(int $itemId): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM bookings WHERE item_id = :item AND status IN ' . self::OPEN_STATES,
            ['item' => $itemId]
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
                    b.borrower_id, borrower.full_name AS borrower_name,
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
               JOIN users borrower ON borrower.id = b.borrower_id
          LEFT JOIN handover_records h ON h.booking_id = b.id
              WHERE b.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Bookings still running on either side — a member cannot close their
     * account while any exist (Plan §17).
     */
    public function countOpenForMember(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings
              WHERE (borrower_id = :borrower OR lender_id = :lender)
                AND status IN ('requested','accepted','awaiting_handover','in_progress',
                               'awaiting_return','pending_moderator','escalated')",
            ['borrower' => $memberId, 'lender' => $memberId]
        );
    }

    /** Damage claims not yet settled on any booking this member is party to (Plan §17). */
    public function countPendingClaimsForMember(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM damage_claims dc
               JOIN bookings b ON b.id = dc.booking_id
              WHERE (b.borrower_id = :borrower OR b.lender_id = :lender)
                AND dc.status NOT IN ('resolved','closed')",
            ['borrower' => $memberId, 'lender' => $memberId]
        );
    }

    /**
     * Who may see a handover or return photo: the booking's two parties and
     * the moderator of the item's division (Plan §10.1). Null when no booking
     * carries this path, so the proxy refuses it (§7.5).
     *
     * @return list<int>|null
     */
    public function conditionPhotoViewers(string $path): ?array
    {
        $row = $this->selectOne(
            "SELECT b.lender_id, b.borrower_id, d.moderator_id
               FROM bookings b
               JOIN items i        ON i.id = b.item_id
               JOIN gn_divisions d ON d.id = i.gn_division_id
          LEFT JOIN handover_records h ON h.booking_id = b.id
          LEFT JOIN return_records r   ON r.booking_id = b.id
              WHERE JSON_CONTAINS(COALESCE(h.lender_photos, '[]'), JSON_QUOTE(:p1))
                 OR JSON_CONTAINS(COALESCE(h.borrower_photos, '[]'), JSON_QUOTE(:p2))
                 OR JSON_CONTAINS(COALESCE(r.lender_photos, '[]'), JSON_QUOTE(:p3))
                 OR JSON_CONTAINS(COALESCE(r.borrower_photos, '[]'), JSON_QUOTE(:p4))
              LIMIT 1",
            ['p1' => $path, 'p2' => $path, 'p3' => $path, 'p4' => $path]
        );

        return $row === null ? null : self::ids($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<int>
     */
    public static function ids(array $row): array
    {
        return array_values(array_filter(
            array_map('intval', array_values($row)),
            static fn (int $id): bool => $id > 0
        ));
    }
}
