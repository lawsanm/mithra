<?php

declare(strict_types=1);

final class Donation extends BaseModel
{
    protected string $table = 'donations';
    protected string $columns = 'id, item_id, donor_id, recipient_id, selection_mode, status, created_at';

    public function forParticipant(int $id, int $memberId): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.donor_id, d.recipient_id, d.selection_mode, d.status, d.created_at,
                    i.title, u.full_name AS recipient_name, u.trust_score
               FROM donations d JOIN items i ON i.id = d.item_id
          LEFT JOIN users u ON u.id = d.recipient_id
              WHERE d.id = :id AND (d.donor_id = :donor OR d.recipient_id = :recipient)',
            ['id' => $id, 'donor' => $memberId, 'recipient' => $memberId]
        );
    }

    public function requests(int $id, int $donorId): array
    {
        return $this->select(
            "SELECT r.id, r.requester_id, r.message, r.status, r.requested_at,
                    u.full_name, u.trust_score, gd.name AS division_name
               FROM donation_requests r JOIN donations d ON d.id = r.donation_id
               JOIN users u ON u.id = r.requester_id
          LEFT JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = 'home'
          LEFT JOIN gn_divisions gd ON gd.id = ud.gn_division_id
              WHERE d.id = :id AND d.donor_id = :donor ORDER BY r.requested_at",
            ['id' => $id, 'donor' => $donorId]
        );
    }
}
