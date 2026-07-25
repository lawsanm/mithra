<?php

declare(strict_types=1);

/**
 * TEMPORARY view-data assembler.
 *
 * Stands in for the controllers that don't exist yet: for a given view it calls
 * the models and returns the array a controller would hand the template. Lives
 * in /public beside preview.php so both are deleted together — no controller
 * logic leaks into /app.
 *
 * Views are display-only and receive plain arrays, exactly as they will from a
 * real controller (Rules/CONVENTIONS.md §6).
 */

require_once __DIR__ . '/../app/autoload.php';

/**
 * Build the view data for one "feature/action" key.
 *
 * @return array<string, mixed>
 */
function preview_data(string $view): array
{
    $pdo = Database::connection();
    $me  = (int) Config::get('demo_member_id', 4);

    $users    = new User($pdo);
    $items    = new Item($pdo);
    $bookings = new Booking($pdo);
    $wallets  = new Wallet($pdo);
    $gifts    = new Gift($pdo);
    $ratings  = new Rating($pdo);
    $notes    = new Notification($pdo);
    $dons     = new Donation($pdo);
    $aid      = new AidGrant($pdo);
    $pools    = new PointPool($pdo);
    $sponsors = new Sponsor($pdo);
    $cats     = new ItemCategory($pdo);

    $member   = $users->findWithDivision($me) ?? [];
    $division = (int) ($member['division_id'] ?? 1);

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
                'greeting'   => preview_greeting((string) ($member['full_name'] ?? '')),
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
                    'href'  => '/trust',
                ],
                [
                    'label' => 'Active borrowings',
                    'value' => (string) $bookings->countActiveBorrowings($me),
                    'note'  => preview_due_note($bookings->countDueTomorrow($me)),
                ],
                [
                    'label' => 'Items listed',
                    'value' => (string) $items->ownedCounts($me)['all'],
                    'note'  => $items->countLentOut($me) . ' currently lent out',
                ],
            ],
            'activeBorrowings' => array_map(
                static fn (array $b): array => [
                    'title'        => (string) $b['item_title'],
                    'meta'         => sprintf(
                        'From %s  ·  borrowed %s  ·  due %s',
                        $b['lender_name'],
                        date('j M', strtotime((string) $b['start_date'])),
                        date('j M', strtotime((string) $b['end_date']))
                    ),
                    'status'       => preview_due_status((string) $b['end_date'])[0],
                    'status_glyph' => preview_due_status((string) $b['end_date'])[1],
                    'status_label' => preview_due_status((string) $b['end_date'])[2],
                    'href'         => '/bookings/' . $b['id'],
                ],
                $bookings->activeBorrowings($me)
            ),
            'listings' => array_map(
                static function (array $i): array {
                    [$who, $due] = array_pad(explode('|', (string) ($i['lent_to'] ?? '')), 2, '');

                    return [
                        'title' => (string) $i['title'],
                        'rate'  => $i['daily_rate'] . ' pts / day',
                        'meta'  => $who === ''
                            ? 'Available'
                            : sprintf('Lent to %s  ·  due %s', preview_short_name($who), date('j M', strtotime($due))),
                        'href'  => '/items/' . $i['id'],
                    ];
                },
                $items->recentListings($me)
            ),
        ],

        default => [],
    };

    return $shared + $data;
}

function preview_greeting(string $fullName): string
{
    $hour = (int) date('G');
    $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
    $last = trim((string) strrchr($fullName, ' ')) ?: $fullName;

    return sprintf('Good %s, %s', $part, $last);
}

function preview_due_note(int $dueTomorrow): string
{
    return $dueTomorrow === 0 ? 'Nothing due back' : $dueTomorrow . ' due back tomorrow';
}

/**
 * @return array{0:string,1:string,2:string} status, glyph, label
 */
function preview_due_status(string $endDate): array
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

function preview_short_name(string $fullName): string
{
    $parts = explode(' ', trim($fullName));

    return end($parts) ?: $fullName;
}
