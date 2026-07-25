<?php

declare(strict_types=1);

/**
 * gifts + gift_usage_counters — the Gifts screen and its cap meters.
 */
final class Gift extends BaseModel
{
    protected string $table = 'gifts';
    protected string $columns = 'id, sender_id, recipient_id, amount, reason, sent_at';

    /** Caps from the platform rules (schema §11.1). */
    public const DAILY_CAP  = 200;
    public const ANNUAL_CAP = 2000;

    /**
     * @return list<array<string, mixed>>
     */
    public function forMember(int $memberId, string $box): array
    {
        // Whitelisted, never interpolated from request input.
        $column = $box === 'received' ? 'g.recipient_id' : 'g.sender_id';
        $other  = $box === 'received' ? 'g.sender_id'    : 'g.recipient_id';

        return $this->select(
            "SELECT g.id, g.amount, g.reason, g.sent_at, u.full_name AS counterparty
               FROM gifts g
               JOIN users u ON u.id = {$other}
              WHERE {$column} = :member
              ORDER BY g.sent_at DESC",
            ['member' => $memberId]
        );
    }

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
}
