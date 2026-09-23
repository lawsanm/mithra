<?php

declare(strict_types=1);

/**
 * notifications — the Notifications screen and the nav bell.
 *
 * Display copy lives in the JSON payload written by Notifier::push().
 */
final class Notification extends BaseModel
{
    protected string $table = 'notifications';
    protected string $columns = 'id, user_id, type, payload, created_at, read_at';

    /** Filter pill => the notification types it covers. */
    private const GROUPS = [
        'bookings'   => ['booking_accepted', 'booking_requested', 'return_due', 'handover_ready'],
        'gifts-aid'  => ['gift_received', 'aid_grant_vouched', 'aid_grant_approved'],
        'system'     => ['listing_approved', 'listing_adjusted', 'listing_rejected', 'account_notice'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function forMember(int $memberId, string $group = ''): array
    {
        $sql    = 'SELECT id, type, payload, created_at, read_at
                     FROM notifications WHERE user_id = :member';
        $params = ['member' => $memberId];

        if (isset(self::GROUPS[$group])) {
            // Build a fixed set of placeholders — the values stay bound.
            $names = [];
            foreach (self::GROUPS[$group] as $index => $type) {
                $name          = 'type' . $index;
                $names[]       = ':' . $name;
                $params[$name] = $type;
            }
            $sql .= ' AND type IN (' . implode(', ', $names) . ')';
        }

        return $this->select($sql . ' ORDER BY created_at DESC', $params);
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_keys(self::GROUPS);
    }

    /**
     * Leave one in-app notification (Plan §21.4).
     *
     * @param array{title:string, detail:string, icon:string, href:string} $payload
     */
    public function push(int $userId, string $type, array $payload): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, type, payload) VALUES (:user, :type, :payload)'
        );

        $statement->execute([
            'user'    => $userId,
            'type'    => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }
}
