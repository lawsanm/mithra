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
    private const SHARED_VIEWS = ['help/index', 'transparency/index'];

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
            && $view !== 'sponsor/purchase-points/create') {
            (new SponsorScreenController($this->pdo))->show($view, $params);
            return;
        }
        $data = match ($view) {
            'dashboard/index' => $this->dashboard(),
            'wallet/index' => $this->wallet(),
            'transparency/index' => $this->transparency(),
            'help/index' => $this->help(),
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
            'actions' => $this->actionItems($me),
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

    /**
     * The dashboard's "Needs your action" panel: every step waiting on this
     * member, each linking to where it is done.
     *
     * @return list<array{label: string, href: string}>
     */
    private function actionItems(int $me): array
    {
        $items = array_map(static fn (array $row): array => match ($row['kind']) {
            'answer_request'   => ['label' => 'Answer the request to borrow ' . $row['title'], 'href' => base_url() . '/bookings/' . $row['record_id']],
            'accept_handover'  => ['label' => 'Photograph and accept the handover of ' . $row['title'], 'href' => base_url() . '/bookings/' . $row['record_id'] . '#handover'],
            'check_return'     => ['label' => 'Check the return of ' . $row['title'], 'href' => base_url() . '/bookings/' . $row['record_id'] . '#return'],
            'answer_claim'     => ['label' => 'Answer the damage claim on ' . $row['title'], 'href' => base_url() . '/bookings/' . $row['record_id'] . '#claim'],
            'sign_resolution'  => ['label' => 'Sign the moderator’s resolution for ' . $row['title'], 'href' => base_url() . '/bookings/' . $row['record_id'] . '#claim'],
            'confirm_donation' => ['label' => 'Confirm the handover of ' . $row['title'], 'href' => base_url() . '/donations/' . $row['record_id'] . '/handover'],
            default            => ['label' => 'Choose who receives ' . $row['title'], 'href' => base_url() . '/donations/' . $row['record_id']],
        }, (new Booking($this->pdo))->needsAction($me));

        $toRate = count((new Rating($this->pdo))->waitingFor($me));

        if ($toRate > 0) {
            $items[] = ['label' => 'Rate ' . $toRate . ' finished booking' . ($toRate === 1 ? '' : 's') . ' or donation' . ($toRate === 1 ? '' : 's'), 'href' => base_url() . '/ratings'];
        }

        return $items;
    }

    private function wallet(): array
    {
        $wallet = new Wallet($this->pdo);
        $me = $this->userId();
        $group = is_string($_GET['filter'] ?? null) && isset(PointLedger::GROUPS[$_GET['filter']]) ? $_GET['filter'] : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        return [
            'filter' => $group,
            'page' => $page,
            'hasNextPage' => $page * Wallet::PER_PAGE < $wallet->countActivity($me, $group),
            'filters' => array_merge([''], array_keys(PointLedger::GROUPS)),
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
            }, $wallet->activity($me, $page, $group)),
        ] + GiftController::modalData($this->pdo, $me);
    }

    /**
     * Help: who to contact. A member reaches their own division's moderator
     * (I23); staff and visitors get the general line.
     *
     * @return array<string, mixed>
     */
    private function help(): array
    {
        $moderator = $this->userId() > 0 ? (new User($this->pdo))->homeModerator($this->userId()) : null;

        return ['moderator' => [
            'line'  => ($moderator === null ? 'Your GN division moderator' : 'Your moderator, ' . $moderator['name'] . ',')
                . ' can help with verification, disputes and anything division-specific.',
            'phone' => $moderator['phone'] ?? '',
            'name'  => $moderator['name'] ?? '',
        ]];
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
