<?php

declare(strict_types=1);

/**
 * Member screens without a controller of their own (routes.php maps them by
 * view name). Record displays read the database. Role-specific screens delegate
 * to their own controllers; forms for unfinished actions stay disabled.
 */
final class DemoController extends Controller
{
    /** Pages every role can open; each role sees them in its own navigation. */
    private const SHARED_VIEWS = ['help/index', 'notifications/index', 'transparency/index'];

    /**
     * @param array{id?: string} $params the {id} from the URL, when the route has one
     */
    public function show(string $view, array $params = []): void
    {
        if (str_contains($view, '/disasters/contributions/')) {
            (new DisasterContributionController($this->pdo))->show($view, $params);
            return;
        }
        if (str_starts_with($view, 'moderator/')) {
            (new ModeratorScreenController($this->pdo))->show($view, $params);
            return;
        }
        if ((str_starts_with($view, 'sponsor/') || str_starts_with($view, 'sponsor-liaison/'))
            && !in_array($view, ['sponsor/notifications/index', 'sponsor/purchase-points/create'], true)) {
            (new SponsorScreenController($this->pdo))->show($view, $params);
            return;
        }
        $id = (int) ($params['id'] ?? 0);
        $data = match ($view) {
            'dashboard/index' => $this->dashboard(),
            'gifts/index'     => $this->gifts(),
            'wallet/index' => $this->wallet(),
            'ratings/index' => $this->ratings(),
            'trust/index' => $this->trust(),
            'notifications/index', 'sponsor/notifications/index' => $this->notifications(),
            'transparency/index' => $this->transparency(),
            'aid-grants/show' => $this->aidGrant($id),
            'donations/index', 'donations/handover' => $this->donation($id, $view),
            'community/create' => $this->community(),
            default           => [],
        };

        if ($data === null) {
            $this->notice(404, 'Record not found', 'This record is not available to your account.');
            return;
        }

        if (in_array($view, self::SHARED_VIEWS, true)) {
            $data['chrome'] = chrome_for($this->role());
        }

        if (isset($params['id'])) {
            $data['id'] = (int) $params['id'];
        }

        $this->render($view, $data);
    }

    /** @return array<string, mixed> */
    private function dashboard(): array
    {
        $me       = $this->userId();
        $users    = new User($this->pdo);
        $items    = new Item($this->pdo);
        $bookings = new Booking($this->pdo);
        $wallets  = new Wallet($this->pdo);
        $member   = $users->findWithDivision($me) ?? [];

        return [
            'member' => [
                'greeting'   => $this->greeting(User::shortName((string) ($member['full_name'] ?? ''))),
                'membership' => sprintf(
                    '%s GN Division  ·  Verified member since %s',
                    (string) ($member['division_name'] ?? ''),
                    date('Y', strtotime((string) ($member['joined_at'] ?? 'now')))
                ),
            ],
            'stats' => [
                [
                    'label'   => 'Points balance',
                    'value'   => number_format($wallets->balance($me)) . ' pts',
                    'note'    => 'Earned ' . $wallets->earnedThisMonth($me) . ' this month',
                    'primary' => true,
                ],
                [
                    'label' => 'Trust score',
                    'value' => (int) ($member['trust_score'] ?? 0) . ' / 100',
                    'note'  => $users->profileStats($me)['completed'] . ' completed transactions',
                    'href'  => base_url() . '/trust',
                ],
                [
                    'label' => 'Active borrowings',
                    'value' => (string) $bookings->countActiveBorrowings($me),
                    'note'  => $this->dueNote($bookings->countDueTomorrow($me)),
                ],
                [
                    'label' => 'Items listed',
                    'value' => (string) $items->ownedCounts($me)['all'],
                    'note'  => $items->countLentOut($me) . ' currently lent out',
                ],
            ],
            'activeBorrowings' => array_map(
                function (array $booking): array {
                    [$status, $glyph, $label] = $this->dueStatus((string) $booking['end_date']);

                    return [
                        'title'        => (string) $booking['item_title'],
                        'photo'        => empty($booking['photo']) ? null : photo_url((string) $booking['photo']),
                        'meta'         => sprintf(
                            'From %s  ·  borrowed %s  ·  due %s',
                            $booking['lender_name'],
                            date('j M', strtotime((string) $booking['start_date'])),
                            date('j M', strtotime((string) $booking['end_date']))
                        ),
                        'status'       => $status,
                        'status_glyph' => $glyph,
                        'status_label' => $label,
                        'href'         => base_url() . '/bookings/' . $booking['id'],
                    ];
                },
                $bookings->activeBorrowings($me)
            ),
            'listings' => array_map(
                function (array $item): array {
                    [$who, $due] = array_pad(explode('|', (string) ($item['lent_to'] ?? '')), 2, '');

                    return [
                        'title' => (string) $item['title'],
                        'photo' => empty($item['photo']) ? null : photo_url((string) $item['photo']),
                        'rate'  => $item['daily_rate'] . ' pts / day',
                        'meta'  => $who === ''
                            ? ($item['status'] === 'active' ? 'Available' : ucfirst(str_replace('_', ' ', $item['status'])))
                            : sprintf('Lent to %s  ·  due %s', User::shortName($who), date('j M', strtotime($due))),
                        'href'  => base_url() . '/items/' . $item['id'],
                    ];
                },
                $items->recentListings($me)
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function gifts(): array
    {
        $me        = $this->userId();
        $gifts     = new Gift($this->pdo);
        $box       = ($_GET['box'] ?? 'sent') === 'received' ? 'received' : 'sent';
        $sentToday = $gifts->sentToday($me);

        return [
            'tabs' => [
                ['label' => 'Sent (' . $gifts->countForMember($me, 'sent') . ')',         'box' => 'sent',     'active' => $box === 'sent'],
                ['label' => 'Received (' . $gifts->countForMember($me, 'received') . ')', 'box' => 'received', 'active' => $box === 'received'],
            ],
            'caps' => [
                ['label' => 'Sent today',     'value' => $sentToday . ' / ' . Gift::DAILY_CAP . ' pts daily cap'],
                ['label' => 'Sent this year', 'value' => $gifts->sentThisYear($me) . ' / ' . Gift::ANNUAL_CAP . ' pts annual cap'],
            ],
            'gifts' => array_map(
                fn (array $gift): array => [
                    'initials'  => User::initials((string) $gift['counterparty']),
                    'name'      => (string) $gift['counterparty'],
                    'note'      => '“' . $gift['reason'] . '”',
                    'amount'    => ($box === 'sent' ? '−' : '+') . $gift['amount'] . ' pts',
                    'direction' => $box === 'sent' ? 'out' : 'in',
                    'date'      => date('j M Y', strtotime((string) $gift['sent_at'])),
                ],
                $gifts->forMember($me, $box)
            ),
            // Feeds the Send a gift modal that this page includes.
            'recipients'    => (new User($this->pdo))->giftableExcept($me),
            'giftSentToday' => $sentToday,
            'giftRemaining' => max(0, Gift::DAILY_CAP - $sentToday),
        ];
    }

    private function wallet(): array
    {
        $wallet = new Wallet($this->pdo);
        $me = $this->userId();
        return [
            'balances' => [
                ['label' => 'Available balance', 'value' => number_format($wallet->balance($me)) . ' pts',
                 'note' => $wallet->earnedThisMonth($me) . ' pts earned this month', 'dark' => true],
                ['label' => 'Held in escrow', 'value' => number_format($wallet->escrow($me)) . ' pts',
                 'note' => 'Recorded ledger holds for open bookings', 'dark' => false],
            ],
            'activity' => array_map(static function (array $row): array {
                $incoming = (bool) $row['incoming'];
                $other = $incoming ? $row['sender_name'] : $row['recipient_name'];
                return ['icon' => $incoming ? 'arrow-down-left' : 'arrow-up-right',
                    'title' => ucfirst(str_replace('_', ' ', $row['reason']))
                        . (empty($row['item_title']) ? '' : ' — ' . $row['item_title']),
                    'note' => implode(' · ', array_filter([$other, $row['gift_reason']])),
                    'amount' => ($incoming ? '+' : '−') . number_format((int) $row['amount']) . ' pts',
                    'tone' => $incoming ? 'in' : 'out', 'date' => date('j M Y', strtotime($row['created_at']))];
            }, $wallet->activity($me)),
        ] + $this->gifts();
    }

    private function ratings(): array
    {
        $ratings = new Rating($this->pdo);
        $box = ($_GET['box'] ?? '') === 'given' ? 'given' : 'received';
        $tabs = [];
        foreach (['received', 'given'] as $direction) {
            $tabs[] = ['label' => ucfirst($direction) . ' (' . $ratings->countForMember($this->userId(), $direction) . ')',
                'box' => $direction, 'active' => $box === $direction];
        }
        return ['tabs' => $tabs, 'reviews' => array_map(static fn (array $row): array => [
            'initials' => User::initials($row['counterparty']), 'author' => $row['counterparty'],
            'rating' => (int) $row['stars'], 'text' => (string) $row['comment'],
            'meta' => date('j M Y', strtotime($row['created_at'])) . ' · ' . ($row['item_title'] ?? 'Review'),
        ], $ratings->forMember($this->userId(), $box))];
    }

    private function trust(): array
    {
        $users = new User($this->pdo);
        $member = $users->findWithDivision($this->userId()) ?? [];
        $stats = $users->profileStats($this->userId());
        return ['score' => ['value' => (string) ($member['trust_score'] ?? 0),
            'badge' => ['info', 'i', 'Current trust score'],
            'meta' => 'Out of 100 · ' . $stats['completed'] . ' completed transactions · ' . ($member['division_name'] ?? '')],
            'factors' => [], 'trustFacts' => [
                'Completed transactions' => (string) $stats['completed'],
                'Completed donations' => (string) $stats['donations'],
                'Member since' => empty($member['joined_at']) ? 'Not yet verified' : date('j M Y', strtotime($member['joined_at'])),
            ]];
    }

    private function notifications(): array
    {
        $group = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
        $filters = [];
        foreach (['' => 'All', 'bookings' => 'Bookings', 'gifts-aid' => 'Gifts & aid', 'system' => 'System'] as $slug => $label) {
            $filters[] = ['label' => $label, 'slug' => $slug, 'active' => $group === $slug];
        }
        return ['filters' => $filters,
            'notifications' => (new Notification($this->pdo))->displayForMember($this->userId(), $group)];
    }

    private function transparency(): array
    {
        $run = (new CronRun($this->pdo))->lastInvariantResult();
        return ['pools' => array_map(static fn (array $row): array => [
                'label' => $row['name'], 'value' => number_format((int) $row['balance']) . ' pts', 'note' => '',
            ], (new PointPool($this->pdo))->all()),
            'invariant' => ['badge' => $run === null ? 'No check recorded' : 'Last recorded check: ' . $run['status'],
                'line' => $run === null ? '' : date('j M Y, H:i', strtotime($run['started_at'])) . ' · ' . $run['notes'],
                'tone' => $run !== null && $run['status'] === 'success' ? 'success' : 'warning'],
            'contributions' => array_map(static fn (array $row): array => [
                'name' => $row['company_name'], 'split' => 'General ' . number_format((int) $row['general_points'])
                    . ' · Aid ' . number_format((int) $row['aid_points']),
                'amount' => number_format((int) $row['points']) . ' pts',
                'date' => date('j M Y', strtotime($row['recorded_at'])),
            ], (new Sponsor($this->pdo))->recentContributions())];
    }

    private function aidGrant(int $id): ?array
    {
        $records = (new AidGrant($this->pdo))->records($this->userId());
        if ($id > 0 && $this->role() === 'moderator') {
            $division = (new GnDivision($this->pdo))->moderatedBy($this->userId());
            $records = $division === null ? $records : (new AidGrant($this->pdo))->records(null, $division);
        }
        $row = null;
        foreach ($records as $record) {
            if (($id > 0 && (int) $record['id'] === $id)
                || ($id === 0 && !in_array($record['status'], ['closed', 'expired'], true))) {
                $row = $record;
                break;
            }
        }
        if ($id > 0 && $row === null) {
            return null;
        }
        return ['grant' => $row === null ? null : [
                'reference' => 'Aid grant #A-' . $row['id'], 'stage' => AidGrant::stage($row['status']),
                'badge' => ['info', 'i', ucfirst(str_replace('_', ' ', $row['status']))],
                'facts' => [
                    ['label' => 'Member', 'value' => $row['member_name']],
                    ['label' => 'Purpose', 'value' => $row['purpose']],
                    ['label' => 'Amount requested', 'value' => number_format((int) $row['requested_amount']) . ' pts'],
                    ['label' => 'Amount approved', 'value' => $row['approved_amount'] === null ? 'Not approved' : $row['approved_amount'] . ' pts'],
                    ['label' => 'Requested', 'value' => date('j M Y', strtotime($row['created_at']))],
                    ['label' => 'Division', 'value' => $row['division_name']],
                ], 'notice' => 'Status: ' . ucfirst(str_replace('_', ' ', $row['status'])),
            ], 'vouch' => ['initials' => User::initials((string) ($row['moderator_name'] ?? '')),
                'line' => empty($row['vouched_at']) ? 'Awaiting moderator vouch' : 'Vouched by ' . $row['moderator_name'] . ' · ' . date('j M Y', strtotime($row['vouched_at'])),
                'quote' => (string) ($row['moderator_vouch'] ?? ''),
                'badge' => empty($row['vouched_at']) ? 'Pending' : 'Vouch recorded'], 'cooling' => ''];
    }

    private function donation(int $id, string $view): ?array
    {
        $model = new Donation($this->pdo);
        $row = $model->forParticipant($id, $this->userId());
        if ($row === null || ($view === 'donations/index' && (int) $row['donor_id'] !== $this->userId())) {
            return null;
        }
        $requests = $model->requests($id, $this->userId());
        return ['donation' => ['id' => $id, 'item' => $row['title'],
                'photo' => empty($row['photo']) ? null : photo_url((string) $row['photo']),
                'meta' => ucfirst($row['status']), 'request_count' => count($requests) . ' requests',
                'first_come' => $row['selection_mode'] === 'first_come'],
            'requests' => array_map(static fn (array $request): array => [
                'initials' => User::initials($request['full_name']), 'name' => $request['full_name'],
                'meta' => 'Trust ' . $request['trust_score'] . ' · ' . ($request['division_name'] ?? '') . ' · ' . $request['status'],
                'message' => $request['message'], 'profile_href' => base_url() . '/members/' . $request['requester_id'],
            ], $requests),
            'recipient' => ['initials' => User::initials((string) ($row['recipient_name'] ?? '')),
                'name' => $row['recipient_name'] ?? 'No recipient selected',
                'meta' => $row['recipient_name'] === null ? '' : 'Trust ' . $row['trust_score']],
            'badge' => 'Donation status: ' . $row['status']];
    }

    private function community(): array
    {
        $home = (new User($this->pdo))->findWithDivision($this->userId());
        $temporary = (new UserDivision($this->pdo))->temporaryForUser($this->userId());
        return ['homeCommunity' => $home['division_name'] ?? 'No home division',
            'promotion' => ['temporary_name' => $temporary['name'] ?? 'No temporary community selected',
                'home_line' => $home['division_name'] ?? 'No home division',
                'temporary_line' => $temporary === null ? 'No temporary membership recorded'
                    : $temporary['name'] . ' · ' . $temporary['status']
                        . ($temporary['expires_at'] === null ? '' : ' · Expires ' . date('j M Y', strtotime($temporary['expires_at'])))],
            'divisions' => array_column(array_filter((new GnDivision($this->pdo))->activeNames(),
                static fn (array $row): bool => (int) $row['id'] !== (int) ($home['division_id'] ?? 0)), 'name'),
            'draft' => ['temporary_community' => ''], 'errors' => []];
    }


    private function dueNote(int $dueTomorrow): string
    {
        return $dueTomorrow === 0 ? 'Nothing due back' : $dueTomorrow . ' due back tomorrow';
    }

    /**
     * @return array{0: string, 1: string, 2: string} badge class, glyph, label
     */
    private function dueStatus(string $endDate): array
    {
        $days = (int) floor((strtotime($endDate) - strtotime('today')) / 86400);

        if ($days < 0) {
            return ['error', '✕', abs($days) . ' days overdue'];
        }

        if ($days <= 1) {
            return ['warning', '!', $days === 0 ? 'Due today' : 'Due tomorrow'];
        }

        return ['success', '✓', 'On track'];
    }
}
