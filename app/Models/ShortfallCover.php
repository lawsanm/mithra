<?php

declare(strict_types=1);

final class WriteOff extends BaseModel
{
    protected string $table = 'point_ledger';
    protected string $columns = 'id, amount, reason, created_at';

    /** @return list<array<string, mixed>> Overdue bookings that could be written off */
    public function candidates(): array
    {
        return $this->select(
            'SELECT b.id, b.rental_charge AS amount, b.end_date,
                    DATEDIFF(CURDATE(), b.end_date) AS days_overdue,
                    borrower.id AS user_id, borrower.full_name AS user_name, borrower.status AS user_status,
                    i.title AS item_title,
                    d.name AS division_name
               FROM bookings b
               JOIN users borrower ON borrower.id = b.borrower_id
               JOIN items i ON i.id = b.item_id
               JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE b.status IN (\'in_progress\', \'awaiting_return\')
                AND b.end_date < CURDATE() - INTERVAL 60 DAY
              ORDER BY b.end_date ASC'
        );
    }

    /** @return array<string, mixed> */
    public function yearStats(): array
    {
        return [
            'total_pts' => (int) $this->selectValue(
                'SELECT COALESCE(SUM(amount), 0) FROM point_ledger
                  WHERE reason = \'shortfall_writeoff\' AND YEAR(created_at) = YEAR(CURDATE())'
            ),
            'accounts' => (int) $this->selectValue(
                'SELECT COUNT(DISTINCT COALESCE(from_user_id, to_user_id)) FROM point_ledger
                  WHERE reason = \'shortfall_writeoff\' AND YEAR(created_at) = YEAR(CURDATE())'
            ),
        ];
    }
}
