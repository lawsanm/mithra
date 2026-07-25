<?php

declare(strict_types=1);

/**
 * ratings — the Ratings screen and the recent reviews on a public profile.
 */
final class Rating extends BaseModel
{
    protected string $table = 'ratings';
    protected string $columns = 'id, rater_id, ratee_id, stars, comment, created_at';

    /**
     * @return list<array<string, mixed>>
     */
    public function forMember(int $memberId, string $box, int $limit = 20): array
    {
        // Whitelisted, never interpolated from request input.
        $column = $box === 'given' ? 'r.rater_id' : 'r.ratee_id';
        $other  = $box === 'given' ? 'r.ratee_id' : 'r.rater_id';

        $statement = $this->pdo->prepare(
            "SELECT r.id, r.stars, r.comment, r.created_at,
                    u.full_name AS counterparty, i.title AS item_title
               FROM ratings r
               JOIN users u    ON u.id = {$other}
          LEFT JOIN bookings b ON b.id = r.booking_id
          LEFT JOIN items i    ON i.id = b.item_id
              WHERE {$column} = :member
              ORDER BY r.created_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':member', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countForMember(int $memberId, string $box): int
    {
        $column = $box === 'given' ? 'rater_id' : 'ratee_id';

        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM ratings WHERE {$column} = :member",
            ['member' => $memberId]
        );
    }

    public function averageStars(int $memberId): float
    {
        return (float) $this->selectValue(
            'SELECT COALESCE(AVG(stars), 0) FROM ratings WHERE ratee_id = :id',
            ['id' => $memberId]
        );
    }
}
