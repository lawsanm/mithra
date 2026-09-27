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
}
