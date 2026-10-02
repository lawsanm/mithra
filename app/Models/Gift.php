<?php

declare(strict_types=1);

/**
 * gifts — the Gifts screen, its cap meters and the round-trip check (Plan 4.5).
 *
 * The caps are summed from this table under the sender's wallet lock
 * (GiftService), so the gift_usage_counters table from the first schema is not
 * used.
 */
final class Gift extends BaseModel
{
    protected string $table = 'gifts';
    protected string $columns = 'id, sender_id, recipient_id, amount, reason, sent_at';

    /** Caps from the platform rules (schema §11.1). */
    public const DAILY_CAP  = 200;
    public const ANNUAL_CAP = 2000;

    public function countForMember(int $memberId, string $box): int
    {
        $column = $box === 'received' ? 'recipient_id' : 'sender_id';

        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM gifts WHERE {$column} = :member",
            ['member' => $memberId]
        );
    }

    public function sentToday(int $memberId): int
    {
        return (int) $this->selectValue(
            'SELECT COALESCE(SUM(amount), 0) FROM gifts
              WHERE sender_id = :id AND DATE(sent_at) = CURDATE()',
            ['id' => $memberId]
        );
    }

    public function sentThisYear(int $memberId): int
    {
        return (int) $this->selectValue(
            'SELECT COALESCE(SUM(amount), 0) FROM gifts
              WHERE sender_id = :id AND YEAR(sent_at) = YEAR(CURDATE())',
            ['id' => $memberId]
        );
    }

    public function create(int $senderId, int $recipientId, int $amount, string $reason): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO gifts (sender_id, recipient_id, amount, reason) VALUES (:sender, :recipient, :amount, :reason)'
        );
        $statement->execute(['sender' => $senderId, 'recipient' => $recipientId, 'amount' => $amount, 'reason' => $reason]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * One page of the gift history (Rules/CONVENTIONS.md §9).
     *
     * @return list<array<string, mixed>>
     */
    public function pageForMember(int $memberId, string $box, int $page, int $perPage): array
    {
        // Whitelisted, never interpolated from request input.
        $column = $box === 'received' ? 'g.recipient_id' : 'g.sender_id';
        $other  = $box === 'received' ? 'g.sender_id'    : 'g.recipient_id';

        $statement = $this->pdo->prepare(
            "SELECT g.id, g.amount, g.reason, g.sent_at, u.full_name AS counterparty
               FROM gifts g
               JOIN users u ON u.id = {$other}
              WHERE {$column} = :member
              ORDER BY g.sent_at DESC, g.id DESC
              LIMIT :take OFFSET :skip"
        );
        $statement->bindValue(':member', $memberId, PDO::PARAM_INT);
        $statement->bindValue(':take', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':skip', (max(1, $page) - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Pairs of members who sent each other gifts at least $trips times each
     * way within the window — the round-trip pattern of §19.
     *
     * @return list<array<string, mixed>>
     */
    public function roundTripPairs(int $days, int $trips): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.a, p.b, LEAST(p.ab, p.ba) AS trips, ua.full_name AS a_name, ub.full_name AS b_name
               FROM (SELECT LEAST(sender_id, recipient_id) AS a, GREATEST(sender_id, recipient_id) AS b,
                            SUM(sender_id < recipient_id) AS ab, SUM(sender_id > recipient_id) AS ba
                       FROM gifts
                      WHERE sent_at >= NOW() - INTERVAL :days DAY
                      GROUP BY LEAST(sender_id, recipient_id), GREATEST(sender_id, recipient_id)) p
               JOIN users ua ON ua.id = p.a
               JOIN users ub ON ub.id = p.b
              WHERE LEAST(p.ab, p.ba) >= :trips'
        );
        $statement->bindValue(':days', $days, PDO::PARAM_INT);
        $statement->bindValue(':trips', $trips, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
