<?php

declare(strict_types=1);

/**
 * donations + donation_requests — the Donations screens.
 */
final class Donation extends BaseModel
{
    protected string $table = 'donations';
    protected string $columns = 'id, item_id, donor_id, recipient_id, selection_mode, status';

    /**
     * @return array<string, mixed>|null
     */
    public function findForDonor(int $donationId, int $donorId): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.selection_mode, d.status, d.handover_at,
                    i.title AS item_title, i.declared_value, i.created_at AS listed_at,
                    r.id AS recipient_id, r.full_name AS recipient_name, r.trust_score AS recipient_trust,
                    (SELECT COUNT(*) FROM bookings b
                      WHERE b.borrower_id = r.id OR b.lender_id = r.id) AS recipient_transactions
               FROM donations d
               JOIN items i     ON i.id = d.item_id
          LEFT JOIN users r     ON r.id = d.recipient_id
              WHERE d.id = :id AND d.donor_id = :donor',
            ['id' => $donationId, 'donor' => $donorId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsFor(int $donationId): array
    {
        return $this->select(
            "SELECT dr.id, dr.message, dr.status, dr.requested_at,
                    u.id AS member_id, u.full_name, u.trust_score,
                    dv.name AS division_name,
                    (SELECT COUNT(*) FROM bookings b
                      WHERE b.borrower_id = u.id OR b.lender_id = u.id) AS transactions
               FROM donation_requests dr
               JOIN users u ON u.id = dr.requester_id
          LEFT JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = 'home'
          LEFT JOIN gn_divisions  dv  ON dv.id = ud.gn_division_id
              WHERE dr.donation_id = :id
              ORDER BY dr.requested_at",
            ['id' => $donationId]
        );
    }

    /**
     * The donor's Donor badge line: how many items they have given away.
     */
    public function countCompletedByDonor(int $donorId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM donations WHERE donor_id = :id AND status = 'completed'",
            ['id' => $donorId]
        );
    }
}
