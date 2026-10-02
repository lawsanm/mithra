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

    /**
     * Average stars and how many ratings a member has received — the trust
     * score's rating factor (§6.3).
     *
     * @return array{average: float, count: int}
     */
    public function summaryFor(int $memberId): array
    {
        $row = $this->selectOne(
            'SELECT COALESCE(AVG(stars), 0) AS average, COUNT(*) AS total FROM ratings WHERE ratee_id = :id',
            ['id' => $memberId]
        ) ?? [];

        return ['average' => (float) ($row['average'] ?? 0), 'count' => (int) ($row['total'] ?? 0)];
    }

    /** The record kinds a rating can be about, whitelisted for column names. */
    public const KINDS = ['booking', 'donation', 'gift'];

    /**
     * This rater's rating of one record, if they have given one.
     *
     * @return array<string, mixed>|null
     */
    public function byRater(int $raterId, string $kind, int $recordId): ?array
    {
        $column = self::column($kind);

        return $this->selectOne(
            "SELECT id, ratee_id, stars, comment, tags, created_at FROM ratings
              WHERE rater_id = :rater AND {$column} = :record LIMIT 1",
            ['rater' => $raterId, 'record' => $recordId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOwned(int $id, int $raterId): ?array
    {
        return $this->selectOne(
            'SELECT r.id, r.rater_id, r.ratee_id, r.booking_id, r.donation_id, r.gift_id, r.context,
                    r.stars, r.comment, r.tags, r.created_at, u.full_name AS ratee_name
               FROM ratings r JOIN users u ON u.id = r.ratee_id
              WHERE r.id = :id AND r.rater_id = :rater',
            ['id' => $id, 'rater' => $raterId]
        );
    }

    /**
     * @param array{kind:string, record_id:int, rater_id:int, ratee_id:int, context:string,
     *              stars:int, comment:?string, tags:list<string>} $data
     */
    public function create(array $data): int
    {
        $column    = self::column($data['kind']);
        $statement = $this->pdo->prepare(
            "INSERT INTO ratings ({$column}, rater_id, ratee_id, context, stars, comment, tags)
             VALUES (:record, :rater, :ratee, :context, :stars, :comment, :tags)"
        );
        $statement->execute([
            'record'  => $data['record_id'],
            'rater'   => $data['rater_id'],
            'ratee'   => $data['ratee_id'],
            'context' => $data['context'],
            'stars'   => $data['stars'],
            'comment' => $data['comment'],
            'tags'    => json_encode($data['tags'], JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<string> $tags
     */
    public function updateOwned(int $id, int $raterId, int $stars, ?string $comment, array $tags): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE ratings SET stars = :stars, comment = :comment, tags = :tags WHERE id = :id AND rater_id = :rater'
        );
        $statement->execute(['stars' => $stars, 'comment' => $comment, 'tags' => json_encode($tags), 'id' => $id, 'rater' => $raterId]);
    }

    public function deleteOwned(int $id, int $raterId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM ratings WHERE id = :id AND rater_id = :rater');
        $statement->execute(['id' => $id, 'rater' => $raterId]);
    }

    /**
     * Records this member took part in and has not rated yet: bookings that
     * finished or were cancelled after acceptance-time, and completed
     * donations, from the last 30 days.
     *
     * @return list<array<string, mixed>>
     */
    public function waitingFor(int $memberId): array
    {
        return $this->select(
            "SELECT 'booking' AS kind, b.id AS record_id,
                    IF(b.borrower_id = :m1, b.lender_id, b.borrower_id) AS ratee_id,
                    u.full_name AS ratee_name, i.title, b.status, b.closed_at AS ended_at
               FROM bookings b
               JOIN items i ON i.id = b.item_id
               JOIN users u ON u.id = IF(b.borrower_id = :m2, b.lender_id, b.borrower_id)
              WHERE (b.borrower_id = :m3 OR b.lender_id = :m4)
                AND b.status IN ('completed','cancelled','auto_cancelled')
                AND b.closed_at >= NOW() - INTERVAL 30 DAY
                AND NOT EXISTS (SELECT 1 FROM ratings r WHERE r.booking_id = b.id AND r.rater_id = :m5)
             UNION ALL
             SELECT 'donation', d.id, IF(d.donor_id = :m6, d.recipient_id, d.donor_id),
                    u.full_name, i.title, d.status, d.handover_at
               FROM donations d
               JOIN items i ON i.id = d.item_id
               JOIN users u ON u.id = IF(d.donor_id = :m7, d.recipient_id, d.donor_id)
              WHERE (d.donor_id = :m8 OR d.recipient_id = :m9)
                AND d.status = 'completed'
                AND d.handover_at >= NOW() - INTERVAL 30 DAY
                AND NOT EXISTS (SELECT 1 FROM ratings r WHERE r.donation_id = d.id AND r.rater_id = :m10)
              ORDER BY ended_at DESC
              LIMIT 20",
            ['m1' => $memberId, 'm2' => $memberId, 'm3' => $memberId, 'm4' => $memberId, 'm5' => $memberId,
             'm6' => $memberId, 'm7' => $memberId, 'm8' => $memberId, 'm9' => $memberId, 'm10' => $memberId]
        );
    }

    private static function column(string $kind): string
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new LogicException('Unknown rating kind.');
        }

        return $kind . '_id';
    }
}
