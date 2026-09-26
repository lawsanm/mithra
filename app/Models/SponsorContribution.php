<?php

declare(strict_types=1);

final class SponsorContribution extends BaseModel
{
    protected string $table = 'sponsor_contributions';
    protected string $columns = 'id, sponsor_id, cash_amount, general_points, aid_points, receipt_number, recorded_at';

    public function records(?int $userId = null): array
    {
        return $this->select(
            'SELECT c.id, c.sponsor_id, c.cash_amount, c.general_points, c.aid_points,
                    c.receipt_number, c.recorded_at, s.company_name, u.full_name AS recorded_by_name
               FROM sponsor_contributions c JOIN sponsors s ON s.id = c.sponsor_id
               JOIN users u ON u.id = c.recorded_by
              WHERE (:all_sponsors = 1 OR s.user_id = :user)
              ORDER BY c.recorded_at DESC, c.id DESC',
            ['all_sponsors' => (int) ($userId === null), 'user' => $userId ?? 0]
        );
    }
}
