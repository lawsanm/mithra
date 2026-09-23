<?php

declare(strict_types=1);

/**
 * Member screens without a controller of their own (routes.php maps them by
 * view name). Two read real data — the dashboard and the gifts list; the rest
 * are design previews that render their own sample content until their module
 * is built.
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
        $data = match ($view) {
            'dashboard/index' => $this->dashboard(),
            'gifts/index'     => $this->gifts(),
            default           => [],
        };

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
                'greeting'   => $this->greeting((string) ($member['full_name'] ?? '')),
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
                        'rate'  => $item['daily_rate'] . ' pts / day',
                        'meta'  => $who === ''
                            ? 'Available'
                            : sprintf('Lent to %s  ·  due %s', $this->lastName($who), date('j M', strtotime($due))),
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

    private function greeting(string $fullName): string
    {
        $hour = (int) date('G');
        $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');

        return sprintf('Good %s, %s', $part, $this->lastName($fullName));
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

    private function lastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return end($parts) ?: $fullName;
    }
}
