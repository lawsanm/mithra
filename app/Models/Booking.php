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
    private const OPEN_STATES = "('requested','accepted','awaiting_handover','in_progress','awaiting_return','pending_moderator','escalated')";

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
            "SELECT b.id, b.item_id, b.start_date, b.end_date, b.rate_basis, b.agreed_rate, b.rental_charge,
                    b.late_buffer, b.status, b.requested_at, b.accepted_at, b.closed_at,
                    b.message, b.decline_reason, b.cancelled_by, b.moderator_involved,
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

    /**
     * Open bookings this member is party to on items listed in one division —
     * leaving that community, or moving home out of it, waits for them.
     */
    public function countOpenInDivisionFor(int $memberId, int $divisionId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings b JOIN items i ON i.id = b.item_id
              WHERE i.gn_division_id = :division
                AND (b.borrower_id = :borrower OR b.lender_id = :lender)
                AND b.status IN ('requested','accepted','awaiting_handover','in_progress',
                                 'awaiting_return','pending_moderator','escalated')",
            ['division' => $divisionId, 'borrower' => $memberId, 'lender' => $memberId]
        );
    }

    /** Statuses in which a booking holds its dates against every other use of the item. */
    public const DATE_HOLDING_STATES = ['accepted', 'awaiting_handover', 'in_progress', 'awaiting_return', 'pending_moderator', 'escalated'];

    /**
     * Bookings on this item sharing at least one day with the range, in the
     * given statuses. Both ends are inclusive.
     *
     * @param list<string> $states
     */
    public function countOverlapping(int $itemId, string $start, string $end, array $states, int $exceptId = 0): int
    {
        $names  = [];
        $params = ['item' => $itemId, 'start' => $start, 'end' => $end, 'except' => $exceptId];

        foreach (array_values($states) as $index => $state) {
            $names[]                  = ':state' . $index;
            $params['state' . $index] = $state;
        }

        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM bookings
              WHERE item_id = :item AND id <> :except
                AND start_date <= :end AND end_date >= :start
                AND status IN (' . implode(', ', $names) . ')',
            $params
        );
    }

    /**
     * The date ranges an item is already booked for, from today on — the
     * item page shows them as "Booked".
     *
     * @return list<array{start_date: string, end_date: string}>
     */
    public function bookedRanges(int $itemId): array
    {
        return $this->select(
            "SELECT start_date, end_date FROM bookings
              WHERE item_id = :item AND end_date >= CURDATE()
                AND status IN ('accepted','awaiting_handover','in_progress','awaiting_return','pending_moderator','escalated')
              ORDER BY start_date
              LIMIT 50",
            ['item' => $itemId]
        );
    }

    /**
     * Insert a new request. Callers pass an already-checked set.
     *
     * @param array{item_id:int, borrower_id:int, lender_id:int, start_date:string, end_date:string,
     *              rate_basis:string, agreed_rate:int, rental_charge:int, late_buffer:int,
     *              moderator_involved:bool, message:?string} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO bookings
                 (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                  rental_charge, late_buffer, moderator_involved, message, status)
             VALUES
                 (:item_id, :borrower_id, :lender_id, :start_date, :end_date, :rate_basis, :agreed_rate,
                  :rental_charge, :late_buffer, :moderator_involved, :message, 'requested')"
        );

        $statement->execute([
            'item_id'            => $data['item_id'],
            'borrower_id'        => $data['borrower_id'],
            'lender_id'          => $data['lender_id'],
            'start_date'         => $data['start_date'],
            'end_date'           => $data['end_date'],
            'rate_basis'         => $data['rate_basis'],
            'agreed_rate'        => $data['agreed_rate'],
            'rental_charge'      => $data['rental_charge'],
            'late_buffer'        => $data['late_buffer'],
            'moderator_involved' => $data['moderator_involved'] ? 1 : 0,
            'message'            => $data['message'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * One booking with what every state change needs, row-locked until the
     * surrounding transaction ends (Rules/CONVENTIONS.md §8).
     *
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array
    {
        return $this->selectOne(
            'SELECT b.id, b.item_id, b.borrower_id, b.lender_id, b.start_date, b.end_date, b.rate_basis,
                    b.agreed_rate, b.rental_charge, b.late_buffer, b.moderator_involved, b.status,
                    b.requested_at, b.accepted_at, b.closed_at, b.overdue_flagged_at,
                    i.title AS item_title, i.declared_value, i.daily_rate, i.monthly_rate, i.gn_division_id,
                    i.status AS item_status, i.listing_type
               FROM bookings b JOIN items i ON i.id = b.item_id
              WHERE b.id = :id
              FOR UPDATE',
            ['id' => $id]
        );
    }

    /**
     * Move a booking on, guarded by the status it is expected to be in, so a
     * concurrent change makes this a no-op instead of a double move.
     */
    public function move(int $id, string $from, string $to): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE bookings
                SET status = :to,
                    accepted_at = IF(:to2 = 'awaiting_handover', NOW(), accepted_at),
                    closed_at = IF(:to3 IN ('completed'), NOW(), closed_at)
              WHERE id = :id AND status = :from"
        );
        $statement->execute(['to' => $to, 'to2' => $to, 'to3' => $to, 'id' => $id, 'from' => $from]);

        return $statement->rowCount() === 1;
    }

    /** The lender says no; the status guard keeps it to waiting requests. */
    public function decline(int $id, ?string $reason): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE bookings SET status = 'rejected', decline_reason = :reason, closed_at = NOW()
              WHERE id = :id AND status = 'requested'"
        );
        $statement->execute(['reason' => $reason, 'id' => $id]);
    }

    /** Close in a cancelled state, recording who cancelled (null for the system). */
    public function close(int $id, string $from, string $to, ?int $cancelledBy): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE bookings SET status = :to, cancelled_by = :by, closed_at = NOW()
              WHERE id = :id AND status = :from'
        );
        $statement->execute(['to' => $to, 'by' => $cancelledBy, 'id' => $id, 'from' => $from]);

        return $statement->rowCount() === 1;
    }

    /**
     * Other waiting requests on the same item whose dates overlap this one —
     * declined when this one is accepted.
     *
     * @return list<array<string, mixed>>
     */
    public function overlappingRequests(int $bookingId): array
    {
        return $this->select(
            "SELECT o.id, o.borrower_id
               FROM bookings b
               JOIN bookings o ON o.item_id = b.item_id AND o.id <> b.id
              WHERE b.id = :id AND o.status = 'requested'
                AND o.start_date <= b.end_date AND o.end_date >= b.start_date",
            ['id' => $bookingId]
        );
    }

    /**
     * Requests nobody answered within the window.
     *
     * @return list<array{id: int}>
     */
    public function staleRequests(int $hours): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM bookings
              WHERE status = 'requested' AND requested_at < NOW() - INTERVAL :hours HOUR
              ORDER BY id LIMIT 500"
        );
        $statement->bindValue(':hours', $hours, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Bookings per page on My Bookings (Rules/CONVENTIONS.md §9). */
    public const PER_PAGE = 20;

    /**
     * My Bookings, one page: current bookings, or past ones (finished,
     * declined or cancelled) with ?state=past.
     *
     * @return list<array<string, mixed>>
     */
    public function pageForMember(int $memberId, string $role, bool $past, int $page): array
    {
        // Whitelisted, never interpolated from request input.
        $column = $role === 'lender' ? 'b.lender_id' : 'b.borrower_id';
        $other  = $role === 'lender' ? 'b.borrower_id' : 'b.lender_id';
        $states = $past ? self::PAST_STATES : self::OPEN_STATES;
        $order  = $past ? 'COALESCE(b.closed_at, b.requested_at) DESC' : 'b.start_date';

        $statement = $this->pdo->prepare(
            "SELECT b.id, b.start_date, b.end_date, b.rental_charge, b.status,
                    i.title AS item_title, o.full_name AS counterparty,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo
               FROM bookings b
               JOIN items i ON i.id = b.item_id
               JOIN users o ON o.id = {$other}
              WHERE {$column} = :member AND b.status IN {$states}
              ORDER BY {$order}, b.id DESC
              LIMIT :take OFFSET :skip"
        );
        $statement->bindValue(':member', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':take', self::PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':skip', (max(1, $page) - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countPastForMember(int $memberId, string $role): int
    {
        $column = $role === 'lender' ? 'lender_id' : 'borrower_id';

        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings WHERE {$column} = :member AND status IN " . self::PAST_STATES,
            ['member' => $memberId]
        );
    }

    /** Statuses a booking ends in. */
    private const PAST_STATES = "('completed','rejected','cancelled','auto_cancelled')";

    /**
     * Accepted bookings whose handover is still unfinished this many hours
     * after the start date.
     *
     * @return list<array{id: int}>
     */
    public function staleHandovers(int $hours): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM bookings
              WHERE status = 'awaiting_handover'
                AND TIMESTAMP(start_date) < NOW() - INTERVAL :hours HOUR
              ORDER BY id LIMIT 500"
        );
        $statement->bindValue(':hours', $hours, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Bookings still out (nothing returned) more than this many hours after
     * the end of their end date.
     *
     * @param bool $unflaggedOnly only those the moderator has not been told about
     *
     * @return list<array<string, mixed>>
     */
    public function unreturnedPast(int $hours, bool $unflaggedOnly): array
    {
        $statement = $this->pdo->prepare(
            "SELECT b.id, b.borrower_id, b.lender_id, i.title AS item_title, i.gn_division_id
               FROM bookings b
               JOIN items i ON i.id = b.item_id
          LEFT JOIN return_records r ON r.booking_id = b.id
              WHERE b.status = 'in_progress' AND r.return_at IS NULL
                AND TIMESTAMP(b.end_date + INTERVAL 1 DAY) < NOW() - INTERVAL :hours HOUR"
            . ($unflaggedOnly ? ' AND b.overdue_flagged_at IS NULL' : '') . '
              ORDER BY b.id LIMIT 500'
        );
        $statement->bindValue(':hours', $hours, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function markOverdueFlagged(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE bookings SET overdue_flagged_at = NOW()
              WHERE id = :id AND status = 'in_progress' AND overdue_flagged_at IS NULL"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    /** Items this member borrowed that are past their return date and not back yet. */
    public function countOverdueBorrowings(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM bookings
              WHERE borrower_id = :member AND status = 'in_progress' AND end_date < CURDATE()",
            ['member' => $memberId]
        );
    }

    /**
     * What is waiting on this member across bookings, claims and donations —
     * the dashboard's "Needs your action" panel.
     *
     * @return list<array{kind: string, record_id: int, title: string}>
     */
    public function needsAction(int $memberId): array
    {
        // Native prepares forbid reusing a placeholder, so each use binds its own.
        $params = [];
        foreach (range(1, 10) as $n) {
            $params['m' . $n] = $memberId;
        }

        return $this->select(
            "SELECT 'answer_request' AS kind, b.id AS record_id, i.title
               FROM bookings b JOIN items i ON i.id = b.item_id
              WHERE b.lender_id = :m1 AND b.status = 'requested'
             UNION ALL
             SELECT 'accept_handover', b.id, i.title
               FROM bookings b JOIN items i ON i.id = b.item_id
          LEFT JOIN handover_records h ON h.booking_id = b.id
              WHERE b.status = 'awaiting_handover'
                AND ((b.lender_id = :m2 AND h.lender_accepted_at IS NULL)
                  OR (b.borrower_id = :m3 AND h.borrower_accepted_at IS NULL))
             UNION ALL
             SELECT 'check_return', b.id, i.title
               FROM bookings b JOIN items i ON i.id = b.item_id
               JOIN return_records r ON r.booking_id = b.id
              WHERE b.lender_id = :m4 AND b.status = 'awaiting_return' AND r.lender_decision IS NULL
             UNION ALL
             SELECT 'answer_claim', b.id, i.title
               FROM damage_claims c JOIN bookings b ON b.id = c.booking_id JOIN items i ON i.id = b.item_id
              WHERE b.borrower_id = :m5 AND c.status = 'awaiting_borrower'
             UNION ALL
             SELECT 'sign_resolution', b.id, i.title
               FROM damage_claims c JOIN bookings b ON b.id = c.booking_id JOIN items i ON i.id = b.item_id
               JOIN moderator_resolutions m ON m.damage_claim_id = c.id
              WHERE c.status = 'pending_moderator' AND m.met_at IS NOT NULL AND m.closed_at IS NULL
                AND ((b.lender_id = :m6 AND m.lender_signoff_at IS NULL)
                  OR (b.borrower_id = :m7 AND m.borrower_signoff_at IS NULL))
             UNION ALL
             SELECT 'confirm_donation', d.id, i.title
               FROM donations d JOIN items i ON i.id = d.item_id
              WHERE d.status = 'recipient_selected'
                AND ((d.donor_id = :m8 AND d.donor_confirmed_at IS NULL)
                  OR (d.recipient_id = :m9 AND d.recipient_confirmed_at IS NULL))
             UNION ALL
             SELECT 'choose_recipient', d.id, i.title
               FROM donations d JOIN items i ON i.id = d.item_id
              WHERE d.donor_id = :m10 AND d.status = 'open'
                AND EXISTS (SELECT 1 FROM donation_requests r WHERE r.donation_id = d.id AND r.status = 'pending')
              LIMIT 20",
            $params
        );
    }
}
