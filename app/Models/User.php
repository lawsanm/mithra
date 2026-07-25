<?php

declare(strict_types=1);

/**
 * users — profiles, trust and the headline counts shown on member profiles.
 */
final class User extends BaseModel
{
    protected string $table = 'users';
    protected string $columns = 'id, full_name, email, phone, address, trust_score, status, joined_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDivision(int $id): ?array
    {
        return $this->selectOne(
            'SELECT u.id, u.full_name, u.email, u.phone, u.address, u.trust_score,
                    u.gift_receive_enabled, u.joined_at, d.id AS division_id, d.name AS division_name
               FROM users u
               JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = \'home\'
               JOIN gn_divisions  d  ON d.id = ud.gn_division_id
              WHERE u.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Headline figures for a public profile.
     *
     * @return array<string, mixed>
     */
    public function profileStats(int $id): array
    {
        return [
            'completed'  => (int) $this->selectValue(
                'SELECT COUNT(*) FROM bookings WHERE (borrower_id = :a OR lender_id = :b) AND status = \'completed\'',
                ['a' => $id, 'b' => $id]
            ),
            'items'      => (int) $this->selectValue(
                'SELECT COUNT(*) FROM items WHERE owner_id = :id AND status <> \'archived\'',
                ['id' => $id]
            ),
            'times_lent' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM bookings WHERE lender_id = :id',
                ['id' => $id]
            ),
            'disputes'   => (int) $this->selectValue(
                'SELECT COUNT(*) FROM disputes WHERE raised_by = :id',
                ['id' => $id]
            ),
            'on_time'    => (int) $this->selectValue(
                'SELECT COALESCE(ROUND(100 * AVG(r.return_at <= b.end_date + INTERVAL 1 DAY)), 100)
                   FROM bookings b JOIN return_records r ON r.booking_id = b.id
                  WHERE b.borrower_id = :id AND r.return_at IS NOT NULL',
                ['id' => $id]
            ),
            'donations'  => (int) $this->selectValue(
                'SELECT COUNT(*) FROM donations WHERE donor_id = :id AND status = \'completed\'',
                ['id' => $id]
            ),
        ];
    }

    /**
     * Members this person may send a gift to.
     *
     * @return list<array<string, mixed>>
     */
    public function giftableExcept(int $id): array
    {
        return $this->select(
            'SELECT id, full_name FROM users
              WHERE id <> :id AND status = \'active\' AND gift_receive_enabled = 1
              ORDER BY full_name',
            ['id' => $id]
        );
    }

    /**
     * Initials for the avatar, e.g. "T.H.K. Madushan" -> "TM".
     */
    public static function initials(string $fullName): string
    {
        preg_match_all('/\b([A-Za-z])/', $fullName, $matches);
        $letters = $matches[1] ?? [];

        if ($letters === []) {
            return '?';
        }

        return strtoupper($letters[0] . ($letters[count($letters) - 1] ?? ''));
    }
}
