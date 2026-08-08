<?php

declare(strict_types=1);

final class Dispute extends BaseModel
{
    protected string $table = 'disputes';
    protected string $columns = 'id, booking_id, damage_claim_id, raised_by, admin_id, reason, status, resolution, ruling_at, created_at';

    public function countOpen(): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM disputes WHERE status = \'open\''
        );
    }

    public function countPastTimer(): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM disputes
              WHERE status = \'open\'
                AND created_at < NOW() - INTERVAL 7 DAY'
        );
    }

    /** @return list<array<string, mixed>> */
    public function openList(): array
    {
        return $this->select(
            'SELECT dp.id, dp.reason, dp.status, dp.created_at,
                    b.id AS booking_id,
                    i.title AS item_title,
                    borrower.full_name AS borrower_name,
                    lender.full_name AS lender_name,
                    d.name AS division_name,
                    TIMESTAMPDIFF(DAY, dp.created_at, NOW()) AS days_open
               FROM disputes dp
               LEFT JOIN bookings b ON b.id = dp.booking_id
               LEFT JOIN items i ON i.id = b.item_id
               LEFT JOIN users borrower ON borrower.id = b.borrower_id
               LEFT JOIN users lender ON lender.id = b.lender_id
               LEFT JOIN items i2 ON i2.id = b.item_id
               LEFT JOIN gn_divisions d ON d.id = i2.gn_division_id
              WHERE dp.status = \'open\'
              ORDER BY dp.created_at ASC'
        );
    }

    /**
     * Most recently opened disputes, for the admin notification feed.
     *
     * @return list<array<string, mixed>>
     */
    public function recentOpen(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            "SELECT dp.id, dp.reason, dp.status, dp.created_at,
                    borrower.full_name AS borrower_name, lender.full_name AS lender_name
               FROM disputes dp
               LEFT JOIN bookings b ON b.id = dp.booking_id
               LEFT JOIN users borrower ON borrower.id = b.borrower_id
               LEFT JOIN users lender ON lender.id = b.lender_id
              WHERE dp.status = 'open'
              ORDER BY dp.created_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findWithHistory(int $id): ?array
    {
        return $this->selectOne(
            'SELECT dp.id, dp.reason, dp.status, dp.resolution, dp.ruling_at, dp.created_at,
                    b.id AS booking_id, b.start_date, b.end_date,
                    i.title AS item_title, i.id AS item_id,
                    borrower.id AS borrower_id, borrower.full_name AS borrower_name,
                    lender.id AS lender_id, lender.full_name AS lender_name,
                    d.name AS division_name,
                    mod_user.full_name AS moderator_name
               FROM disputes dp
               LEFT JOIN bookings b ON b.id = dp.booking_id
               LEFT JOIN items i ON i.id = b.item_id
               LEFT JOIN users borrower ON borrower.id = b.borrower_id
               LEFT JOIN users lender ON lender.id = b.lender_id
               LEFT JOIN gn_divisions d ON d.id = i.gn_division_id
               LEFT JOIN users mod_user ON mod_user.id = d.moderator_id
              WHERE dp.id = :id',
            ['id' => $id]
        );
    }
}
