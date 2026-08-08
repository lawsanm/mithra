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
 * @param  array<string, string> $params route parameters, e.g. ['id' => '3']
 * @return array<string, mixed>
 */
function preview_data(string $view, array $params = []): array
{
    if (str_starts_with($view, 'admin/')) {
        return preview_admin_dispatch($view, $params);
    }

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

        'gifts/index' => (static function () use ($gifts, $users, $me): array {
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
                    static fn (array $g): array => [
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

// ── Admin preview-data helpers ──────────────────────────────────────────────

function preview_admin_dispatch(string $view, array $params): array
{
    $pdo = Database::connection();

    $divisions  = new GnDivision($pdo);
    $disputes   = new Dispute($pdo);
    $cronRuns   = new CronRun($pdo);
    $moderators = new Moderator($pdo);
    $ledger     = new PointLedger($pdo);
    $writeoffs  = new WriteOff($pdo);
    $users      = new User($pdo);
    $cats       = new ItemCategory($pdo);
    $pools      = new PointPool($pdo);
    $bookings   = new Booking($pdo);
    $wallets    = new Wallet($pdo);
    $notes      = new Notification($pdo);
    $sponsors   = new Sponsor($pdo);

    $adminUser = $users->find(6) ?? ['full_name' => 'System Admin'];

    $shared = [
        'currentAdmin' => [
            'initials'  => User::initials((string) $adminUser['full_name']),
            'full_name' => (string) $adminUser['full_name'],
        ],
    ];

    $data = match ($view) {
        'admin/dashboard/index'        => preview_admin_dashboard($shared, $users, $disputes, $pools, $bookings, $cronRuns, $divisions),
        'admin/divisions/index'        => preview_admin_divisions_index($shared, $divisions),
        'admin/divisions/show'         => preview_admin_divisions_show($shared, $divisions, $params),
        'admin/divisions/approvals'    => preview_admin_divisions_approvals($shared, $divisions, $params),
        'admin/moderators/index'       => preview_admin_moderators_index($shared, $divisions),
        'admin/moderators/performance' => preview_admin_moderators_performance($shared, $moderators),
        'admin/moderators/appoint'     => preview_admin_moderators_appoint($shared, $divisions, $moderators, $params),
        'admin/moderators/objections'  => $shared,
        'admin/moderators/show'        => preview_admin_moderators_show($shared, $moderators, $params),
        'admin/disputes/index'         => preview_admin_disputes_index($shared, $disputes),
        'admin/disputes/show'          => preview_admin_disputes_show($shared, $disputes, $params),
        'admin/disaster/index'         => preview_admin_disaster_index($shared, $divisions),
        'admin/categories/index'       => preview_admin_categories_index($shared, $cats),
        'admin/pools/index'            => preview_admin_pools_index($shared, $pools, $cronRuns),
        'admin/pools/writeoffs'        => preview_admin_writeoffs_index($shared, $writeoffs, $pools),
        'admin/pools/sponsor-ledger'   => preview_admin_sponsor_ledger($shared, $sponsors, $pools),
        'admin/pools/policies'         => preview_admin_policies($shared),
        'admin/ledger/index'           => preview_admin_ledger_index($shared, $ledger),
        'admin/users/index'            => preview_admin_users_index($shared, $users, $divisions),
        'admin/users/show'             => preview_admin_users_show($shared, $pdo, $users, $params),
        'admin/cron/index'             => preview_admin_cron_index($shared, $cronRuns),
        'admin/notifications/index'    => preview_admin_notifications_index($shared, $disputes, $cronRuns, $sponsors, $users, $divisions, $moderators),
        'admin/settings/profile'       => preview_admin_settings($shared, $adminUser, 'profile'),
        'admin/settings/security'      => preview_admin_settings($shared, $adminUser, 'security'),
        'admin/settings/notifications' => preview_admin_settings($shared, $adminUser, 'notifications'),
        default => $shared,
    };

    return $data;
}

// ── Dashboard ───────────────────────────────────────────────────────────────

function preview_admin_dashboard(array $shared, User $users, Dispute $disputes, PointPool $pools, Booking $bookings, CronRun $cronRuns, GnDivision $divisions): array
{
    $totalMembers = $users->countByRole('member');
    $newThisMonth = $users->countNewMembersThisMonth();
    $activeBookings = $bookings->countActive();
    $escrowPts = $bookings->activeEscrowPoints();
    $openDisputes = $disputes->countOpen();
    $pastTimer = $disputes->countPastTimer();
    $totalPts = $pools->totalBalance();

    $invariantRun = $cronRuns->lastInvariantResult();
    $invariantPassed = $invariantRun !== null && $invariantRun['status'] === 'success';

    $cronJobs = array_map(static function (array $job): array {
        return [
            'name'         => str_replace('_', ' ', ucfirst($job['job_name'])),
            'schedule'     => 'Last ' . ($job['started_at'] ? date('j M H:i', strtotime($job['started_at'])) : 'never'),
            'last_run'     => $job['started_at'] ?? '',
            'status'       => $job['status'] === 'success' ? 'success' : 'error',
            'status_label' => $job['status'] === 'success' ? 'OK' : 'Failed',
        ];
    }, $cronRuns->recentJobs(5));

    $divisionCount = $divisions->countAll();

    return $shared + [
        'admin' => ['name' => trim((string) strrchr($shared['currentAdmin']['full_name'], ' ')) ?: $shared['currentAdmin']['full_name']],
        'globalMeta' => ['division_count' => $divisionCount, 'member_count' => number_format($totalMembers)],
        'stats' => [
            ['label' => 'Total members', 'value' => number_format($totalMembers), 'note' => '+' . $newThisMonth . ' this month', 'primary' => true],
            ['label' => 'Active bookings', 'value' => number_format($activeBookings), 'note' => number_format($escrowPts) . ' pts in escrow'],
            ['label' => 'Open disputes', 'value' => (string) $openDisputes, 'note' => $pastTimer . ' past 7-day timer'],
            ['label' => 'Points in system', 'value' => number_format($totalPts), 'note' => 'All wallets + pools', 'primary' => true],
        ],
        'invariant' => [
            'passed'   => $invariantPassed,
            'last_run' => $invariantRun['finished_at'] ?? '',
            'summary'  => $invariantRun['notes'] ?? 'No invariant run recorded',
        ],
        'cronJobs' => $cronJobs,
    ];
}

// ── Divisions ───────────────────────────────────────────────────────────────

function preview_admin_divisions_index(array $shared, GnDivision $divisions): array
{
    $rows = $divisions->allWithStaff();

    return $shared + [
        'divisions' => array_map(static function (array $d): array {
            $hasMod = !empty($d['moderator_name']);
            return [
                'id'                   => $d['id'],
                'name'                 => $d['name'],
                'district'             => $d['district'],
                'member_count'         => (int) $d['member_count'],
                'moderator_name'       => $d['moderator_name'],
                'liaison_name'         => 'Pending',
                'disaster_mode_active' => (bool) $d['disaster_mode_active'],
                'status'               => $hasMod ? 'success' : 'warning',
                'status_label'         => $hasMod ? 'Active' : 'No moderator',
                'href'                 => '/admin/divisions/' . $d['id'],
            ];
        }, $rows),
    ];
}

function preview_admin_divisions_show(array $shared, GnDivision $divisions, array $params): array
{
    $id = (int) ($params['id'] ?? 1);
    $div = $divisions->findWithStaff($id);
    if ($div === null) {
        return $shared;
    }
    $stats = $divisions->divisionStats($id);

    $statusBadge = $div['moderator_name'] ? 'success' : 'warning';
    $statusLabel = $div['moderator_name'] ? 'Active' : 'No moderator';

    return $shared + [
        'division' => [
            'id'                   => $div['id'],
            'name'                 => $div['name'],
            'district'             => $div['district'],
            'disaster_mode_active' => (bool) $div['disaster_mode_active'],
            'moderator_name'       => $div['moderator_name'],
            'moderator_since'      => $div['moderator_since'] ? date('Y-m-d', strtotime($div['moderator_since'])) : null,
            'status'               => $statusBadge,
            'status_label'         => $statusLabel,
        ],
        'stats' => [
            ['label' => 'Members', 'value' => (string) $stats['members'], 'note' => 'verified residents', 'primary' => true],
            ['label' => 'Active listings', 'value' => (string) $stats['items_listed'], 'note' => 'items available'],
            ['label' => 'Open disputes', 'value' => (string) $stats['disputes'], 'note' => 'pending resolution', 'error' => $stats['disputes'] > 0],
        ],
    ];
}

function preview_admin_divisions_approvals(array $shared, GnDivision $divisions, array $params): array
{
    $id = (int) ($params['id'] ?? 1);
    $div = $divisions->findWithStaff($id);
    $approvalStats = $divisions->approvalStats($id);
    $pending = $divisions->pendingApprovals($id);

    return $shared + [
        'division'      => ['id' => $id, 'name' => $div['name'] ?? 'Division'],
        'pending'       => array_map(static fn(array $p): array => [
            'id'         => $p['id'],
            'full_name'  => $p['full_name'],
            'nic'        => $p['nic'],
            'applied_at' => $p['applied_at'],
            'address'    => $p['address'],
        ], $pending),
        'approvalStats' => [
            ['label' => 'Pending', 'value' => (string) $approvalStats['pending']],
            ['label' => 'Approved', 'value' => (string) $approvalStats['approved']],
            ['label' => 'Progress', 'value' => $approvalStats['approved'] . ' / ' . ($approvalStats['approved'] + $approvalStats['pending'])],
        ],
    ];
}

// ── Moderators ──────────────────────────────────────────────────────────────

function preview_admin_moderators_index(array $shared, GnDivision $divisions): array
{
    $allDivs = $divisions->allWithStaff();
    $vacantDivisions = array_values(array_filter($allDivs, static fn(array $d): bool => empty($d['moderator_name'])));

    return $shared + [
        'activeTab'       => 'nominations',
        'vacantDivisions' => $vacantDivisions,
        'divisions'       => $allDivs,
    ];
}

function preview_admin_moderators_performance(array $shared, Moderator $moderators): array
{
    $rows = $moderators->allWithPerformance();

    return $shared + [
        'moderators' => array_map(static fn(array $m): array => [
            'id'            => $m['user_id'],
            'initials'      => User::initials($m['full_name']),
            'name'          => $m['full_name'],
            'full_name'     => $m['full_name'],
            'trust_score'   => (int) $m['trust_score'],
            'division'      => $m['division_name'],
            'division_name' => $m['division_name'],
            'meta'          => 'Trust ' . $m['trust_score'] . ' · ' . $m['items_reviewed'] . ' items reviewed · ' . $m['disputes_resolved'] . ' disputes resolved',
            'bond_balance'  => (int) $m['bond_points'],
            'bond_status'   => $m['bond_status'],
            'status'        => 'success',
            'status_label'  => ucfirst($m['status']),
            'action_style'  => 'ghost',
            'action_href'   => '/admin/moderators/' . $m['user_id'],
            'action_label'  => 'View',
            'appointed_at'  => $m['appointed_at'],
        ], $rows),
    ];
}

function preview_admin_moderators_appoint(array $shared, GnDivision $divisions, Moderator $moderators, array $params): array
{
    $id = (int) ($params['id'] ?? 1);
    $div = $divisions->findWithStaff($id);
    $candidates = $moderators->eligibleCandidates($id);

    return $shared + [
        'division'   => ['id' => $id, 'name' => $div['name'] ?? 'Division'],
        'candidates' => array_map(static function (array $c, int $i): array {
            $months = $c['joined_at'] ? max(1, (int) round((time() - strtotime($c['joined_at'])) / 2592000)) : 0;
            return [
                'id'           => $c['id'],
                'name'         => $c['full_name'],
                'full_name'    => $c['full_name'],
                'initials'     => User::initials($c['full_name']),
                'trust_score'  => (int) $c['trust_score'],
                'address'      => $c['address'],
                'verified_at'  => $c['verified_at'] ?? '',
                'member_since' => $c['joined_at'] ? date('M Y', strtotime($c['joined_at'])) : '',
                'transactions' => (int) $c['completed_bookings'],
                'months'       => $months,
                'record'       => 'No disputes on record',
                'gn_endorsed'  => false,
                'recommended'  => $i === 0,
            ];
        }, $candidates, array_keys($candidates)),
    ];
}

function preview_admin_moderators_show(array $shared, Moderator $moderators, array $params): array
{
    $id = (int) ($params['id'] ?? 1);
    $mod = $moderators->findByUserId($id);

    if (!$mod) {
        return $shared;
    }

    return $shared + [
        'moderator' => [
            'id'           => $mod['user_id'],
            'name'         => $mod['full_name'],
            'initials'     => User::initials($mod['full_name']),
            'trust_score'  => (int) $mod['trust_score'],
            'phone'        => $mod['phone'] ?? '',
            'email'        => $mod['email'] ?? '',
            'address'      => $mod['address'] ?? '',
            'division'     => $mod['division_name'],
            'division_id'  => $mod['division_id'],
            'bond_balance' => (int) $mod['bond_points'],
            'bond_status'  => $mod['bond_status'],
            'status'       => $mod['status'] === 'active' ? 'success' : 'info',
            'status_label' => ucfirst($mod['status']),
            'appointed_at' => $mod['appointed_at'] ? date('j M Y', strtotime($mod['appointed_at'])) : '',
        ],
    ];
}

// ── Disputes ────────────────────────────────────────────────────────────────

function preview_admin_disputes_index(array $shared, Dispute $disputes): array
{
    $rows = $disputes->openList();

    return $shared + [
        'disputes' => array_map(static function (array $d): array {
            $daysOpen = (int) $d['days_open'];
            $pastTimer = $daysOpen > 7;
            return [
                'id'           => $d['id'],
                'title'        => $d['item_title'] ?? 'Dispute #' . $d['id'],
                'case_number'  => 'DC-' . str_pad((string) $d['id'], 4, '0', STR_PAD_LEFT),
                'division'     => $d['division_name'] ?? '',
                'parties'      => ($d['lender_name'] ?? '') . ' vs ' . ($d['borrower_name'] ?? ''),
                'escalated_at' => date('d M Y', strtotime($d['created_at'])),
                'reason'       => $daysOpen . ' days open',
                'status'       => $pastTimer ? 'error' : 'warning',
                'status_label' => $pastTimer ? 'Past timer' : 'Open',
                'href'         => '/admin/disputes/' . $d['id'],
            ];
        }, $rows),
    ];
}

function preview_admin_disputes_show(array $shared, Dispute $disputes, array $params): array
{
    $id = (int) ($params['id'] ?? 1);
    $d = $disputes->findWithHistory($id);
    if ($d === null) {
        return $shared;
    }

    return $shared + [
        'dispute' => [
            'id'             => $d['id'],
            'item_title'     => $d['item_title'] ?? '',
            'claim_number'   => 'DC-' . str_pad((string) $d['id'], 4, '0', STR_PAD_LEFT),
            'lender_name'    => $d['lender_name'] ?? '',
            'borrower_name'  => $d['borrower_name'] ?? '',
            'moderator_name' => $d['moderator_name'] ?? '',
            'reason'         => $d['reason'],
            'resolution'     => $d['resolution'],
            'status'         => $d['status'],
            'created_at'     => $d['created_at'],
            'division_name'  => $d['division_name'] ?? '',
        ],
        'evidence' => [],
    ];
}

// ── Disaster ────────────────────────────────────────────────────────────────

function preview_admin_disaster_index(array $shared, GnDivision $divisions): array
{
    $allDivs = $divisions->allWithStaff();

    return $shared + [
        'divisions' => array_map(static function (array $d): array {
            $active = (bool) $d['disaster_mode_active'];
            return [
                'id'     => $d['id'],
                'name'   => $d['name'],
                'active' => $active,
                'meta'   => $active
                    ? 'Disaster mode active'
                    : 'Mod: ' . ($d['moderator_name'] ?? 'Vacant') . ' · no active disaster',
                'status'       => $active ? 'error' : 'success',
                'status_label' => $active ? 'Active' : 'Normal',
            ];
        }, $allDivs),
    ];
}

// ── Categories ──────────────────────────────────────────────────────────────

function preview_admin_categories_index(array $shared, ItemCategory $cats): array
{
    $rows = $cats->allWithListingCount();

    return $shared + [
        'categories' => array_map(static fn(array $c): array => [
            'id'            => $c['id'],
            'name'          => $c['name'],
            'listing_count' => (int) $c['listing_count'],
            'status'        => $c['active'] ? 'success' : 'neutral',
            'status_label'  => $c['active'] ? 'Active' : 'Hidden',
        ], $rows),
    ];
}

// ── Pools ───────────────────────────────────────────────────────────────────

function preview_admin_pools_index(array $shared, PointPool $pools, CronRun $cronRuns): array
{
    $allPools = $pools->all();
    $invariant = $pools->lastInvariantRun();
    $totalPts = array_sum(array_column($allPools, 'balance'));
    $invariantPassed = $invariant !== null && $invariant['status'] === 'success';

    $poolDescriptions = [
        'sponsor'        => 'welcome bonuses · stipends · festival drops',
        'aid'            => 'aid grants · 15% of every injection',
        'reserve'        => 'covers shortfalls — no negative balances',
        'in_flight'      => 'rental charges + late-fee buffers',
        'member_wallets' => 'sum of all member wallet balances',
        'retired'        => 'closed accounts & write-offs',
    ];

    return $shared + [
        'pools' => array_map(static fn(array $p) => [
            'label' => $p['name'],
            'value' => number_format((int) $p['balance']) . ' pts',
            'note'  => $poolDescriptions[$p['pool_code']] ?? '',
        ], $allPools),
        'invariant' => [
            'passed'      => $invariantPassed,
            'total'       => number_format($totalPts) . ' pts',
            'summary'     => $invariant['notes'] ?? 'No invariant run recorded',
            'verified_at' => $invariant['finished_at'] ? date('j M, H:i', strtotime($invariant['finished_at'])) : 'never',
        ],
        'jobs' => array_map(static fn(array $j) => [
            'name'         => str_replace('_', ' ', ucfirst($j['job_name'])),
            'schedule'     => 'Last ' . date('j M H:i', strtotime($j['started_at'])),
            'status'       => $j['status'] === 'success' ? 'success' : 'error',
            'status_label' => $j['status'] === 'success' ? 'OK' : 'Failed',
        ], $cronRuns->recentJobs(3)),
    ];
}

function preview_admin_writeoffs_index(array $shared, WriteOff $writeoffs, PointPool $pools): array
{
    $reservePool = $pools->all();
    $reserveBalance = 0;
    foreach ($reservePool as $p) {
        if ($p['pool_code'] === 'reserve') {
            $reserveBalance = (int) $p['balance'];
        }
    }

    $candidates = $writeoffs->candidates();
    $yearStats = $writeoffs->yearStats();

    return $shared + [
        'reserveBalance' => $reserveBalance,
        'candidates' => array_map(static fn(array $c): array => [
            'id'           => $c['id'],
            'initials'     => User::initials($c['user_name']),
            'name'         => $c['user_name'],
            'division'     => $c['division_name'],
            'amount'       => number_format((int) $c['amount']) . ' pts',
            'reason'       => 'Overdue debt — booking #' . $c['id'],
            'age'          => $c['days_overdue'] . ' days overdue',
            'extra'        => $c['user_status'] === 'suspended' ? 'Account suspended' : '',
            'status'       => $c['user_status'] === 'suspended' ? 'error' : 'warning',
            'status_label' => ucfirst($c['user_status']),
        ], $candidates),
        'yearStats' => ['total_pts' => $yearStats['total_pts'], 'accounts' => $yearStats['accounts']],
    ];
}

function preview_admin_sponsor_ledger(array $shared, Sponsor $sponsors, PointPool $pools): array
{
    $inflows = $sponsors->allInflows();

    $totalReceived = array_sum(array_column($inflows, 'points_credited'));

    $sponsorPool = $pools->balance('sponsor');

    return $shared + [
        'summary' => [
            'totalReceived' => ['value' => number_format($totalReceived) . ' pts', 'sub' => 'Rs ' . number_format($totalReceived) . ' converted at 1:1 ratio'],
            'totalUsed'     => ['value' => number_format($totalReceived - (int) $sponsorPool) . ' pts', 'sub' => 'Welcome bonuses · Stipends · Infrastructure'],
            'remaining'     => ['value' => number_format((int) $sponsorPool) . ' pts', 'sub' => 'Held in Sponsor Pool'],
        ],
        'inflows' => array_map(static fn(array $r): array => [
            'date'     => date('d M Y', strtotime($r['recorded_at'])),
            'sponsor'  => $r['company_name'],
            'ref'      => $r['receipt_number'],
            'category' => 'Sponsorship',
            'cash'     => 'Rs ' . number_format((int) $r['cash_amount']),
            'pts'      => '+' . number_format((int) $r['points_credited']),
            'status'   => 'success',
            'status_label' => 'Settled',
        ], $inflows),
    ];
}

function preview_admin_policies(array $shared): array
{
    return $shared + [
        'policies' => [
            ['name' => 'Daily gift cap', 'value' => '200 pts', 'description' => 'Maximum points a member can gift per day'],
            ['name' => 'Annual gift cap', 'value' => '2,000 pts', 'description' => 'Maximum points a member can gift per year'],
            ['name' => 'Moderator bond', 'value' => '500 pts', 'description' => 'Conduct bond deposited on appointment'],
            ['name' => 'Write-off threshold', 'value' => '60 days', 'description' => 'Overdue debts become write-off candidates after this period'],
            ['name' => 'Dispute escalation timer', 'value' => '7 days', 'description' => 'Moderator disputes auto-escalate to admin after this period'],
        ],
    ];
}

// ── Ledger ───────────────────────────────────────────────────────────────────

function preview_admin_ledger_index(array $shared, PointLedger $ledger): array
{
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['q'] ?? '';
    $page   = max(1, (int) ($_GET['page'] ?? 1));

    $result = $ledger->adminList($filter, $search, $page);

    $reasonLabels = [
        'sponsor_injection' => 'Sponsor injection',
        'welcome_bonus'     => 'Welcome bonus',
        'rental_charge'     => 'Rental charge',
        'rental_payout'     => 'Rental payout',
        'late_fee'          => 'Late fee',
        'gift'              => 'Gift',
        'aid_grant'         => 'Aid grant',
        'bond_hold'         => 'Bond hold',
        'bond_return'       => 'Bond return',
        'shortfall_writeoff'=> 'Write-off',
        'buffer_hold'       => 'Buffer hold',
        'buffer_refund'     => 'Buffer refund',
    ];

    return $shared + [
        'filter'  => $filter,
        'search'  => $search,
        'page'    => $page,
        'entries' => array_map(static function (array $r) use ($reasonLabels): array {
            $fromLabel = $r['from_pool_code'] ?? $r['from_user_name'] ?? '—';
            $toLabel   = $r['to_pool_code'] ?? $r['to_user_name'] ?? '—';
            $incoming  = $r['to_user_id'] !== null;

            return [
                'ref'          => 'TXN-' . $r['id'],
                'date'         => date('d M Y', strtotime($r['created_at'])),
                'title'        => $reasonLabels[$r['reason']] ?? ucfirst(str_replace('_', ' ', $r['reason'])),
                'meta'         => $fromLabel . ' → ' . $toLabel,
                'amount'       => ($incoming ? '+' : '−') . number_format((int) $r['amount']) . ' pts',
                'amount_class' => $incoming ? 'success' : 'error',
            ];
        }, $result['rows']),
    ];
}

// ── Users ───────────────────────────────────────────────────────────────────

function preview_admin_users_index(array $shared, User $users, GnDivision $divisions): array
{
    $statusFilter = $_GET['status'] ?? '';
    $search = $_GET['q'] ?? '';

    $rows = $users->adminList($statusFilter, $search);

    $totalUsers = $users->countAll();
    $activeUsers = $users->countByStatus('active');
    $frozenUsers = $users->countByStatus('suspended');
    $newMonth = $users->countJoinedThisMonth();

    $statusMap = ['active' => 'success', 'suspended' => 'error', 'pending' => 'warning', 'closed_standard' => 'neutral', 'closed_donation' => 'neutral'];

    return $shared + [
        'stats' => [
            ['label' => 'Total users', 'value' => number_format($totalUsers)],
            ['label' => 'Active', 'value' => number_format($activeUsers)],
            ['label' => 'Frozen', 'value' => number_format($frozenUsers)],
            ['label' => 'New this month', 'value' => number_format($newMonth)],
        ],
        'search' => $search,
        'users'  => array_map(static fn(array $u): array => [
            'initials'     => User::initials($u['full_name']),
            'name'         => $u['full_name'],
            'division'     => $u['division_name'] ?? '—',
            'role'         => $u['role_name'],
            'balance'      => number_format((int) $u['balance']) . ' pts',
            'status'       => $statusMap[$u['status']] ?? 'neutral',
            'status_label' => ucfirst(str_replace('_', ' ', $u['status'])),
            'href'         => '/admin/users/' . $u['id'],
        ], $rows),
        'divisions' => $divisions->allNames(),
    ];
}

function preview_admin_users_show(array $shared, PDO $pdo, User $users, array $params): array
{
    $id = (int) ($params['id'] ?? 1);
    $user = $users->findWithDivision($id);
    if ($user === null) {
        return $shared;
    }

    $wallets = new Wallet($pdo);
    $balance = $wallets->balance($id);
    $pStats = $users->profileStats($id);

    $roleName = $users->roleName($id);

    $statusMap = ['active' => 'success', 'suspended' => 'error', 'pending' => 'warning'];

    return $shared + [
        'user' => [
            'id'           => $user['id'],
            'name'         => $user['full_name'],
            'full_name'    => $user['full_name'],
            'initials'     => User::initials($user['full_name']),
            'email'        => $user['email'] ?? '',
            'phone'        => $user['phone'] ?? '',
            'address'      => $user['address'] ?? '',
            'trust_score'  => (int) $user['trust_score'],
            'division'     => $user['division_name'] ?? '',
            'role'         => $roleName,
            'balance'      => number_format($balance) . ' pts',
            'status'       => $statusMap[$user['status'] ?? 'active'] ?? 'neutral',
            'status_label' => ucfirst(str_replace('_', ' ', $user['status'] ?? 'active')),
            'joined_at'    => $user['joined_at'] ? date('j M Y', strtotime($user['joined_at'])) : '',
        ],
        'stats' => [
            ['label' => 'Items listed', 'value' => (string) $pStats['items']],
            ['label' => 'Transactions', 'value' => (string) $pStats['completed']],
            ['label' => 'Disputes', 'value' => (string) $pStats['disputes']],
        ],
    ];
}

// ── Cron ─────────────────────────────────────────────────────────────────────

function preview_admin_cron_index(array $shared, CronRun $cronRuns): array
{
    $jobs = $cronRuns->allJobs();

    return $shared + [
        'jobs' => array_map(static fn(array $j): array => [
            'name'         => $j['job_name'],
            'description'  => str_replace('_', ' ', ucfirst($j['job_name'])),
            'schedule'     => '',
            'last_run'     => $j['started_at'] ?? '',
            'next_run'     => '',
            'status'       => $j['status'] === 'success' ? 'success' : ($j['status'] === 'failed' ? 'error' : 'info'),
            'status_label' => ucfirst($j['status']),
            'notes'        => $j['notes'] ?? '',
        ], $jobs),
    ];
}

// ── Notifications ───────────────────────────────────────────────────────────

/**
 * Admin notifications are system-generated, not user-addressed rows in
 * `notifications` — an admin cares about platform-wide events (disputes,
 * pool health, account actions, staffing), not personal messages. This
 * synthesizes that feed from the tables that actually drive each event.
 */
function preview_admin_notifications_index(array $shared, Dispute $disputes, CronRun $cronRuns, Sponsor $sponsors, User $users, GnDivision $divisions, Moderator $moderators): array
{
    $typeFilter = $_GET['type'] ?? '';

    $notices = [];

    // Disputes — new/open cases needing moderator or admin attention.
    $disputeRows = $disputes->recentOpen(10);
    foreach ($disputeRows as $d) {
        $daysOpen = (int) floor((time() - strtotime($d['created_at'])) / 86400);
        $notices[] = [
            'icon'     => '⚠',
            'category' => 'disputes',
            'title'    => 'Open dispute — case #DC-' . str_pad((string) $d['id'], 4, '0', STR_PAD_LEFT),
            'meta'     => ($d['lender_name'] ?? '?') . ' vs ' . ($d['borrower_name'] ?? '?') . ' · ' . $d['reason'],
            'created_at' => $d['created_at'],
            'read'     => $daysOpen > 7 ? false : true,
        ];
    }

    // Pools — nightly invariant check outcome.
    $invariant = $cronRuns->lastInvariantResult();
    if ($invariant) {
        $passed = $invariant['status'] === 'success';
        $notices[] = [
            'icon'     => $passed ? '✓' : '✕',
            'category' => 'pools',
            'title'    => $passed ? 'Invariant check passed' : 'Invariant check FAILED',
            'meta'     => (string) ($invariant['notes'] ?? ''),
            'created_at' => $invariant['finished_at'] ?? $invariant['started_at'] ?? date('Y-m-d H:i:s'),
            'read'     => $passed,
        ];
    }

    // Pools — recent sponsor injections.
    $sponsorRows = $sponsors->recentInjections(5);
    foreach ($sponsorRows as $s) {
        $notices[] = [
            'icon'     => '⚡',
            'category' => 'pools',
            'title'    => 'Sponsor injection received — ' . $s['receipt_number'],
            'meta'     => $s['company_name'] . ' · +' . number_format((int) $s['points_credited']) . ' pts',
            'created_at' => $s['recorded_at'],
            'read'     => true,
        ];
    }

    // Users — frozen / suspended accounts.
    $frozenRows = $users->recentlySuspended(5);
    foreach ($frozenRows as $u) {
        $notices[] = [
            'icon'     => '🔒',
            'category' => 'users',
            'title'    => 'Account frozen — ' . $u['full_name'],
            'meta'     => 'Status set to suspended',
            'created_at' => $u['updated_at'],
            'read'     => true,
        ];
    }

    // Users — pending division approvals (new residents awaiting verification).
    $pendingRows = $divisions->recentPendingApprovals(5);
    foreach ($pendingRows as $p) {
        $notices[] = [
            'icon'     => '📋',
            'category' => 'users',
            'title'    => 'New verification pending — ' . $p['full_name'],
            'meta'     => $p['division_name'] . ' · awaiting moderator approval',
            'created_at' => $p['created_at'],
            'read'     => false,
        ];
    }

    // System — moderator appointments.
    $modRows = $moderators->recentAppointments(5);
    foreach ($modRows as $m) {
        $notices[] = [
            'icon'     => '👤',
            'category' => 'system',
            'title'    => 'Moderator appointed — ' . $m['full_name'],
            'meta'     => $m['division_name'] . ' division · appointed by Admin',
            'created_at' => $m['appointed_at'],
            'read'     => true,
        ];
    }

    // System — divisions with no moderator (vacant staffing gap).
    $vacantRows = $divisions->vacant();
    foreach ($vacantRows as $v) {
        $notices[] = [
            'icon'     => '⚠',
            'category' => 'system',
            'title'    => 'Moderator vacancy — ' . $v['name'],
            'meta'     => 'No moderator appointed for this division',
            'created_at' => $v['created_at'],
            'read'     => false,
        ];
    }

    // System — failed cron jobs.
    $failedJobs = $cronRuns->recentFailed(5);
    foreach ($failedJobs as $j) {
        $notices[] = [
            'icon'     => '✕',
            'category' => 'system',
            'title'    => 'Cron job failed — ' . str_replace('_', ' ', $j['job_name']),
            'meta'     => (string) ($j['notes'] ?? 'Check logs for details'),
            'created_at' => $j['started_at'],
            'read'     => false,
        ];
    }

    if ($typeFilter !== '') {
        $notices = array_values(array_filter($notices, static fn(array $n): bool => $n['category'] === $typeFilter));
    }

    usort($notices, static fn(array $a, array $b): int => strtotime($b['created_at']) <=> strtotime($a['created_at']));

    $notices = array_map(static function (array $n): array {
        $n['time'] = preview_relative_time($n['created_at']);
        unset($n['category'], $n['created_at']);
        return $n;
    }, array_slice($notices, 0, 20));

    $filters = [
        ['label' => 'All',       'slug' => '',        'active' => $typeFilter === ''],
        ['label' => 'Disputes',  'slug' => 'disputes', 'active' => $typeFilter === 'disputes'],
        ['label' => 'Pools',     'slug' => 'pools',    'active' => $typeFilter === 'pools'],
        ['label' => 'Users',     'slug' => 'users',    'active' => $typeFilter === 'users'],
        ['label' => 'System',    'slug' => 'system',   'active' => $typeFilter === 'system'],
    ];

    return $shared + [
        'filters' => $filters,
        'notices' => $notices,
    ];
}

function preview_relative_time(string $datetime): string
{
    $diff = time() - strtotime($datetime);

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' hours ago';
    }
    if ($diff < 172800) {
        return 'Yesterday';
    }
    if ($diff < 604800) {
        return (int) floor($diff / 86400) . ' days ago';
    }

    return (int) floor($diff / 604800) . ' week' . ((int) floor($diff / 604800) === 1 ? '' : 's') . ' ago';
}

// ── Settings ────────────────────────────────────────────────────────────────

function preview_admin_settings(array $shared, array $adminUser, string $tab): array
{
    return $shared + [
        'activeTab' => $tab,
        'admin' => [
            'id'        => $adminUser['id'] ?? 6,
            'name'      => $adminUser['full_name'],
            'full_name' => $adminUser['full_name'],
            'email'     => $adminUser['email'] ?? 'admin@mithra.lk',
            'phone'     => $adminUser['phone'] ?? '',
            'division'  => 'All Divisions',
            'role'      => 'System Administrator',
            'joined'    => isset($adminUser['joined_at']) ? date('F Y', strtotime($adminUser['joined_at'])) : 'September 2024',
        ],
    ];
}
