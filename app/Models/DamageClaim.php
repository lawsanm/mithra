<?php

declare(strict_types=1);

/**
 * damage_claims — a lender's claim that an item came back damaged, on the
 * simple path or the moderator path (Plan 3.4, §10.3–10.6). Claim statuses
 * move only along TRANSITIONS.
 */
final class DamageClaim extends BaseModel
{
    protected string $table = 'damage_claims';
    protected string $columns = 'id, booking_id, raised_by, severity, description, status, created_at';

    /** from => the statuses a claim may move to next. */
    public const TRANSITIONS = [
        'awaiting_borrower' => ['pending_moderator', 'closed', 'escalated'],
        'pending_moderator' => ['resolved', 'escalated'],
        'escalated'         => ['resolved', 'closed', 'pending_moderator'],
    ];

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function forDivision(int $divisionId): array
    {
        return $this->select(
            'SELECT c.id, c.severity, c.description, c.status, c.created_at,
                    b.borrower_id, b.lender_id, b.moderator_involved,
                    i.title AS item_title, lender.full_name AS lender_name, borrower.full_name AS borrower_name,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, \'$[0]\')) AS photo,
                    r.notes, r.met_at, r.lender_signoff_at, r.borrower_signoff_at, r.closed_at,
                    m.full_name AS moderator_name
               FROM damage_claims c JOIN bookings b ON b.id = c.booking_id
               JOIN items i ON i.id = b.item_id
               JOIN users lender ON lender.id = b.lender_id
               JOIN users borrower ON borrower.id = b.borrower_id
          LEFT JOIN moderator_resolutions r ON r.damage_claim_id = c.id
          LEFT JOIN users m ON m.id = r.moderator_id
              WHERE i.gn_division_id = :division ORDER BY c.created_at DESC, c.id DESC',
            ['division' => $divisionId]
        );
    }

    /**
     * Who may see a piece of damage evidence: the booking's two parties and
     * the item's division moderator. Null when no claim carries the path.
     *
     * @return list<int>|null
     */
    public function evidenceViewers(string $path): ?array
    {
        $row = $this->selectOne(
            "SELECT b.lender_id, b.borrower_id, d.moderator_id
               FROM damage_claims c
               JOIN bookings b     ON b.id = c.booking_id
               JOIN items i        ON i.id = b.item_id
               JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE c.evidence_path = :path
                 OR JSON_CONTAINS(COALESCE(c.evidence_photos, '[]'), JSON_QUOTE(:photo))
              LIMIT 1",
            ['path' => $path, 'photo' => $path]
        );

        return $row === null ? null : self::ids($row);
    }

    /**
     * Open a claim on a booking. Callers pass an already-checked set.
     *
     * @param array{booking_id:int, raised_by:int, severity:string, description:?string,
     *              proposed_penalty:int, track:string, status:string, evidence_photos?:list<string>} $data
     */
    public function open(array $data): int
    {
        return $this->insert(
            'INSERT INTO damage_claims
                 (booking_id, raised_by, severity, description, proposed_penalty, evidence_photos, track, status)
             VALUES
                 (:booking_id, :raised_by, :severity, :description, :proposed_penalty, :evidence_photos, :track, :status)',
            ['evidence_photos' => json_encode($data['evidence_photos'] ?? [], JSON_UNESCAPED_SLASHES)] + $data
        );
    }

    /**
     * The latest claim on a booking, with its moderator resolution, for the
     * booking page.
     *
     * @return array<string, mixed>|null
     */
    public function latestForBooking(int $bookingId): ?array
    {
        return $this->selectOne(
            'SELECT c.id, c.booking_id, c.raised_by, c.severity, c.description, c.proposed_penalty,
                    c.evidence_path, c.evidence_photos, c.track, c.borrower_response, c.responded_at,
                    c.status, c.created_at,
                    r.id AS resolution_id, r.outcome_category, r.penalty_points, r.notes AS resolution_notes,
                    r.met_at, r.lender_signoff_at, r.borrower_signoff_at, r.closed_at AS resolution_closed_at,
                    m.full_name AS moderator_name
               FROM damage_claims c
          LEFT JOIN moderator_resolutions r ON r.damage_claim_id = c.id
          LEFT JOIN users m ON m.id = r.moderator_id
              WHERE c.booking_id = :booking
              ORDER BY c.id DESC LIMIT 1',
            ['booking' => $bookingId]
        );
    }

    /**
     * One claim with its booking and resolution, row-locked for the rest of
     * the transaction.
     *
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array
    {
        return $this->selectOne(
            'SELECT c.id, c.booking_id, c.raised_by, c.severity, c.proposed_penalty, c.track,
                    c.borrower_response, c.status, c.created_at,
                    r.id AS resolution_id, r.penalty_points, r.met_at,
                    r.lender_signoff_at, r.borrower_signoff_at, r.closed_at AS resolution_closed_at
               FROM damage_claims c
          LEFT JOIN moderator_resolutions r ON r.damage_claim_id = c.id
              WHERE c.id = :id
              FOR UPDATE',
            ['id' => $id]
        );
    }

    /**
     * Move a claim on, guarded by the status it is expected to be in. A change
     * TRANSITIONS does not list is a bug, never a write.
     */
    public function move(int $id, string $from, string $to, ?string $track = null, ?string $response = null): bool
    {
        if (!self::canMove($from, $to)) {
            throw new LogicException(sprintf('A damage claim cannot move from %s to %s.', $from, $to));
        }

        return $this->execute(
            'UPDATE damage_claims
                SET status = :to,
                    track = COALESCE(:track, track),
                    borrower_response = COALESCE(:response, borrower_response),
                    responded_at = IF(:responded = 1, NOW(), responded_at)
              WHERE id = :id AND status = :from',
            ['to' => $to, 'track' => $track, 'response' => $response, 'responded' => $response === null ? 0 : 1, 'id' => $id, 'from' => $from]
        ) === 1;
    }

    /**
     * Simple-path claims the borrower has not answered within the window.
     *
     * @return list<array{id: int}>
     */
    public function unansweredSince(int $hours): array
    {
        return $this->select(
            "SELECT id FROM damage_claims
              WHERE status = 'awaiting_borrower' AND created_at < NOW() - INTERVAL :hours HOUR
              ORDER BY id LIMIT 500",
            ['hours' => $hours]
        );
    }

    /** One party's sign-off on the moderator's recorded resolution (§10.5). */
    public function signOff(int $resolutionId, string $side): void
    {
        $column = $side === 'lender' ? 'lender_signoff_at' : 'borrower_signoff_at';

        $this->execute("UPDATE moderator_resolutions SET {$column} = COALESCE({$column}, NOW()) WHERE id = :id", ['id' => $resolutionId]);
    }

    public function closeResolution(int $resolutionId): void
    {
        $this->execute('UPDATE moderator_resolutions SET closed_at = NOW() WHERE id = :id AND closed_at IS NULL', ['id' => $resolutionId]);
    }

    /**
     * Unsettled claims against this member as borrower — gifting waits for
     * them (§11.1).
     */
    public function countPendingAgainst(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM damage_claims c JOIN bookings b ON b.id = c.booking_id
              WHERE b.borrower_id = :member AND c.status NOT IN ('resolved','closed')",
            ['member' => $memberId]
        );
    }

    /** Upheld claims against this member as borrower in the last 12 months — a trust-score penalty (§6.3). */
    public function countUpheldAgainst(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM damage_claims c JOIN bookings b ON b.id = c.booking_id
              WHERE b.borrower_id = :member AND c.status = 'resolved'
                AND c.created_at >= NOW() - INTERVAL 12 MONTH",
            ['member' => $memberId]
        );
    }
}
