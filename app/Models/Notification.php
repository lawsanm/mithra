<?php

declare(strict_types=1);

/**
 * notifications — the Notifications screen and the nav bell.
 *
 * Display copy lives in the JSON payload written by Notification::push().
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
        $sql    = 'SELECT n.id, n.type, n.payload, n.created_at, n.read_at,
                          b.id AS booking_id, b.end_date, bi.title AS booking_title,
                          lender.full_name AS lender_name, g.id AS gift_id, g.amount AS gift_amount,
                          g.reason AS gift_reason, sender.full_name AS sender_name,
                          ag.id AS grant_id, moderator.full_name AS moderator_name,
                          i.id AS item_id, i.title AS item_title, d.name AS division_name
                     FROM notifications n
                LEFT JOIN bookings b ON b.id = JSON_UNQUOTE(JSON_EXTRACT(n.payload, \'$.booking_id\'))
                                    AND n.user_id IN (b.borrower_id, b.lender_id)
                LEFT JOIN items bi ON bi.id = b.item_id
                LEFT JOIN users lender ON lender.id = b.lender_id
                LEFT JOIN gifts g ON g.id = JSON_UNQUOTE(JSON_EXTRACT(n.payload, \'$.gift_id\'))
                                 AND g.recipient_id = n.user_id
                LEFT JOIN users sender ON sender.id = g.sender_id
                LEFT JOIN aid_grants ag ON ag.id = JSON_UNQUOTE(JSON_EXTRACT(n.payload, \'$.aid_grant_id\'))
                                      AND ag.member_id = n.user_id
                LEFT JOIN users moderator ON moderator.id = ag.moderator_id
                LEFT JOIN items i ON i.id = JSON_UNQUOTE(JSON_EXTRACT(n.payload, \'$.item_id\'))
                                 AND i.owner_id = n.user_id
                LEFT JOIN gn_divisions d ON d.id = i.gn_division_id
                    WHERE n.user_id = :member';
        $params = ['member' => $memberId];

        if (isset(self::GROUPS[$group])) {
            // Build a fixed set of placeholders — the values stay bound.
            $names = [];
            foreach (self::GROUPS[$group] as $index => $type) {
                $name          = 'type' . $index;
                $names[]       = ':' . $name;
                $params[$name] = $type;
            }
            $sql .= ' AND n.type IN (' . implode(', ', $names) . ')';
        }

        return $this->select($sql . ' ORDER BY n.created_at DESC, n.id DESC', $params);
    }

    public function unreadCount(int $memberId): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM notifications WHERE user_id = :id AND read_at IS NULL', ['id' => $memberId]);
    }

    /** Entity names are resolved from IDs, so account and listing edits appear immediately. */
    public function displayForMember(int $memberId, string $group = ''): array
    {
        return array_map(static function (array $row): array {
            $payload = json_decode((string) $row['payload'], true) ?: [];
            if ($row['booking_id'] !== null) {
                // Only these two types are re-worded from live data; every
                // other booking notification keeps the wording it was sent
                // with, so a decline is never titled as an acceptance.
                if ($row['type'] === 'return_due') {
                    $payload['title'] = 'Return due ' . date('j M Y', strtotime($row['end_date'])) . ': ' . $row['booking_title'];
                    $payload['detail'] = 'Return by ' . date('j M Y', strtotime($row['end_date'])) . '.';
                } elseif ($row['type'] === 'booking_accepted') {
                    $payload['title'] = $row['lender_name'] . ' accepted your request for ' . $row['booking_title'];
                    $payload['detail'] = 'View your booking for handover details.';
                }
                $payload['href'] = '/bookings/' . $row['booking_id'];
            }
            if ($row['gift_id'] !== null) {
                $payload['title'] = $row['sender_name'] . ' sent you a gift of ' . $row['gift_amount'] . ' pts';
                $payload['detail'] = $row['gift_reason'];
            }
            if ($row['grant_id'] !== null) {
                $payload['detail'] = 'Moderator ' . ($row['moderator_name'] ?? 'not assigned') . ' · request #A-' . $row['grant_id'];
            }
            if ($row['item_id'] !== null) {
                $label = match ($row['type']) {
                    'listing_adjusted' => 'Listing approved with a new value',
                    'listing_rejected' => 'Listing rejected',
                    default => 'Listing approved',
                };
                $payload['title'] = $label . ': ' . $row['item_title'];
                if ($row['type'] === 'listing_approved') {
                    $payload['detail'] = 'Approved for ' . $row['division_name'] . ' members.';
                }
            }
            $href = (string) ($payload['href'] ?? '/notifications');
            if (!str_starts_with($href, '/') || str_starts_with($href, '//')) {
                $href = '/notifications';
            }
            return ['icon' => (string) ($payload['icon'] ?? 'info'), 'title' => (string) ($payload['title'] ?? 'Notification'),
                'detail' => (string) ($payload['detail'] ?? ''), 'time' => date('j M Y, H:i', strtotime($row['created_at'])),
                'unread' => $row['read_at'] === null, 'href' => base_url() . $href];
        }, $this->forMember($memberId, $group));
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_keys(self::GROUPS);
    }

    /**
     * Whether this kind of notice, about this subject key (the payload's
     * "pair"), already went to this account within the last few days — so a
     * daily job does not repeat itself.
     */
    public function sentRecently(int $userId, string $type, string $key, int $days): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications
              WHERE user_id = :user AND type = :type
                AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.pair')) = :pair
                AND created_at >= NOW() - INTERVAL :days DAY"
        );
        $statement->bindValue(':user', $userId, PDO::PARAM_INT);
        $statement->bindValue(':type', $type);
        $statement->bindValue(':pair', $key);
        $statement->bindValue(':days', $days, PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn() > 0;
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
