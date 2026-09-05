<?php

declare(strict_types=1);

/** Read-only interim screens. Items has its own controller for saved changes. */
final class DemoController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function show(string $view, array $params = []): void
    {
        $_GET = $params + $_GET;
        extract($this->data($view), EXTR_SKIP);
        require dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

    private function data(string $view): array
    {
        $pdo = $this->pdo;
        $me  = (int) Config::get('demo_member_id', 4);

        $users    = new User($pdo);
        $items    = new Item($pdo);
        $bookings = new Booking($pdo);
        $wallets  = new Wallet($pdo);
        $gifts    = new Gift($pdo);

        $member   = $users->findWithDivision($me) ?? [];

        // Nav chrome, shown on every page.
        $shared = [
            'currentMember' => [
                'initials'       => User::initials((string) ($member['full_name'] ?? '')),
                'points_balance' => number_format($wallets->balance($me)) . ' pts',
            ],
        ];

        $data = match ($view) {

            'dashboard/index' => [
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
                    fn (array $b): array => [
                        'title'        => (string) $b['item_title'],
                        'meta'         => sprintf(
                            'From %s  ·  borrowed %s  ·  due %s',
                            $b['lender_name'],
                            date('j M', strtotime((string) $b['start_date'])),
                            date('j M', strtotime((string) $b['end_date']))
                        ),
                        'status'       => $this->dueStatus((string) $b['end_date'])[0],
                        'status_glyph' => $this->dueStatus((string) $b['end_date'])[1],
                        'status_label' => $this->dueStatus((string) $b['end_date'])[2],
                        'href'         => base_url() . '/bookings/' . $b['id'],
                    ],
                    $bookings->activeBorrowings($me)
                ),
                'listings' => array_map(
                    function (array $i): array {
                        [$who, $due] = array_pad(explode('|', (string) ($i['lent_to'] ?? '')), 2, '');

                        return [
                            'title' => (string) $i['title'],
                            'rate'  => $i['daily_rate'] . ' pts / day',
                            'meta'  => $who === ''
                                ? 'Available'
                                : sprintf('Lent to %s  ·  due %s', $this->shortName($who), date('j M', strtotime($due))),
                            'href'  => base_url() . '/items/' . $i['id'],
                        ];
                    },
                    $items->recentListings($me)
                ),
            ],

            'gifts/index' => (function () use ($gifts, $users, $me): array {
                $box = ($_GET['box'] ?? 'sent') === 'received' ? 'received' : 'sent';

                return [
                    'tabs' => [
                        [
                            'label'  => 'Sent (' . $gifts->countForMember($me, 'sent') . ')',
                            'box'    => 'sent',
                            'active' => $box === 'sent',
                        ],
                        [
                            'label'  => 'Received (' . $gifts->countForMember($me, 'received') . ')',
                            'box'    => 'received',
                            'active' => $box === 'received',
                        ],
                    ],
                    'caps' => [
                        [
                            'label' => 'Sent today',
                            'value' => $gifts->sentToday($me) . ' / ' . Gift::DAILY_CAP . ' pts daily cap',
                        ],
                        [
                            'label' => 'Sent this year',
                            'value' => $gifts->sentThisYear($me) . ' / ' . Gift::ANNUAL_CAP . ' pts annual cap',
                        ],
                    ],
                    'gifts' => array_map(
                        fn (array $g): array => [
                            'initials'  => User::initials((string) $g['counterparty']),
                            'name'      => (string) $g['counterparty'],
                            'note'      => '“' . $g['reason'] . '”',
                            'amount'    => ($box === 'sent' ? '−' : '+') . $g['amount'] . ' pts',
                            'direction' => $box === 'sent' ? 'out' : 'in',
                            'date'      => date('j M Y', strtotime((string) $g['sent_at'])),
                        ],
                        $gifts->forMember($me, $box)
                    ),
                    // Feeds the Send a gift modal that this page includes.
                    'recipients'    => $users->giftableExcept($me),
                    'giftSentToday' => $gifts->sentToday($me),
                    'giftRemaining' => max(0, Gift::DAILY_CAP - $gifts->sentToday($me)),
                ];
            })(),

            default => [],
        };

        return $shared + $data;
    }

    private function greeting(string $fullName): string
    {
        $hour = (int) date('G');
        $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
        $last = trim((string) strrchr($fullName, ' ')) ?: $fullName;

        return sprintf('Good %s, %s', $part, $last);
    }

    private function dueNote(int $dueTomorrow): string
    {
        return $dueTomorrow === 0 ? 'Nothing due back' : $dueTomorrow . ' due back tomorrow';
    }

    /**
     * @return array{0:string,1:string,2:string} status, glyph, label
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

    private function shortName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return end($parts) ?: $fullName;
    }
}
