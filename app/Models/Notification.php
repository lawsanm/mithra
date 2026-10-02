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

    /** Notifications per page (Rules/CONVENTIONS.md §9). */
    public const PER_PAGE = 20;

    /**
     * Filter pill => the notification types it covers. Every type a service
     * sends belongs to one pill, and displayForMember() words it from its own
     * payload unless it is listed there (I3).
     */
    public const GROUPS = [
        'bookings'  => [
            'booking_requested', 'booking_accepted', 'booking_declined', 'booking_cancelled', 'booking_auto_cancelled',
            'handover_ready', 'handover_complete', 'return_started', 'return_due', 'return_overdue', 'booking_completed',
            'claim_raised', 'dispute_opened',
            'donation_requested', 'donation_selected', 'donation_declined', 'donation_withdrawn', 'donation_confirm',
            'donation_completed',
        ],
        'gifts-aid' => ['gift_received', 'gift_pattern', 'aid_grant_requested', 'aid_grant_vouched', 'aid_grant_approved'],
        'system'    => [
            'listing_approved', 'listing_adjusted', 'listing_rejected', 'account_notice',
            'community_application', 'community_approved', 'community_rejected', 'community_expiring',
            'community_paused', 'community_expired',
        ],
    ];

    /**
     * One page of a member's notifications (page 0: all of them), or one
     * notification by id.
     *
     * @return list<array<string, mixed>>
     */
    public function forMember(int $memberId, string $group = '', int $page = 0, ?int $onlyId = null): array
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

        $sql .= $this->groupFilter($group, $params);

        if ($onlyId !== null) {
            $sql .= ' AND n.id = :only';
            $params['only'] = $onlyId;
        }

        $sql .= ' ORDER BY n.created_at DESC, n.id DESC';

        if ($page > 0) {
            // Whole numbers worked out here, never request text.
            $sql .= ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE);
        }

        return $this->select($sql, $params);
    }

    public function countForMember(int $memberId, string $group = ''): int
    {
        $params = ['member' => $memberId];

        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM notifications n WHERE n.user_id = :member' . $this->groupFilter($group, $params),
            $params
        );
    }

    /**
     * A fixed set of placeholders for one pill's types — the values stay bound.
     *
     * @param array<string, mixed> $params filled in with the bound types
     */
    private function groupFilter(string $group, array &$params): string
    {
        if (!isset(self::GROUPS[$group])) {
            return '';
        }

        $names = [];
        foreach (self::GROUPS[$group] as $index => $type) {
            $names[]               = ':type' . $index;
            $params['type' . $index] = $type;
        }

        return ' AND n.type IN (' . implode(', ', $names) . ')';
    }

    /**
     * One of this member's notifications as the list shows it, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findForMember(int $id, int $memberId): ?array
    {
        $rows = $this->forMember($memberId, '', 0, $id);

        return $rows === [] ? null : self::display($rows[0]);
    }

    /** Mark one read; the owner is part of the WHERE clause. */
    public function markRead(int $id, int $memberId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = :id AND user_id = :member'
        );
        $statement->execute(['id' => $id, 'member' => $memberId]);
    }

    public function markAllRead(int $memberId): int
    {
        $statement = $this->pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :member AND read_at IS NULL');
        $statement->execute(['member' => $memberId]);

        return $statement->rowCount();
    }

    /** Dismiss one; the owner is part of the WHERE clause. */
    public function deleteOwned(int $id, int $memberId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :member');
        $statement->execute(['id' => $id, 'member' => $memberId]);

        return $statement->rowCount() === 1;
    }

    public function unreadCount(int $memberId): int
    {
        return (int) $this->selectValue('SELECT COUNT(*) FROM notifications WHERE user_id = :id AND read_at IS NULL', ['id' => $memberId]);
    }

    /**
     * Entity names are resolved from IDs, so account and listing edits appear
     * immediately.
     *
     * @return list<array<string, mixed>>
     */
    public function displayForMember(int $memberId, string $group = '', int $page = 0): array
    {
        return array_map(static fn (array $row): array => self::display($row), $this->forMember($memberId, $group, $page));
    }

    /**
     * @param array<string, mixed> $row a forMember() row
     *
     * @return array<string, mixed>
     */
    private static function display(array $row): array
    {
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
        return ['id' => (int) $row['id'], 'icon' => (string) ($payload['icon'] ?? 'info'), 'title' => (string) ($payload['title'] ?? 'Notification'),
            'detail' => (string) ($payload['detail'] ?? ''), 'time' => date('j M Y, H:i', strtotime($row['created_at'])),
            'unread' => $row['read_at'] === null, 'href' => base_url() . $href, 'path' => $href];
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
