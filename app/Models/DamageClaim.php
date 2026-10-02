<?php

declare(strict_types=1);

final class DamageClaim extends BaseModel
{
    protected string $table = 'damage_claims';
    protected string $columns = 'id, booking_id, raised_by, severity, description, status, created_at';

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
              LIMIT 1",
            ['path' => $path]
        );

        return $row === null ? null : Booking::ids($row);
    }

    /**
     * Open a claim on a booking. Callers pass an already-checked set.
     *
     * @param array{booking_id:int, raised_by:int, severity:string, description:?string,
     *              proposed_penalty:int, track:string, status:string} $data
     */
    public function open(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, track, status)
             VALUES (:booking_id, :raised_by, :severity, :description, :proposed_penalty, :track, :status)'
        );
        $statement->execute([
            'booking_id'       => $data['booking_id'],
            'raised_by'        => $data['raised_by'],
            'severity'         => $data['severity'],
            'description'      => $data['description'],
            'proposed_penalty' => $data['proposed_penalty'],
            'track'            => $data['track'],
            'status'           => $data['status'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
