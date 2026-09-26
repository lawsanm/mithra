<?php

declare(strict_types=1);

/**
 * The Admin's screens. routes.php maps most of them by view name to show();
 * each builds its page from the database, and a missing record is a 404.
 * Division create / update / archive are the Admin's working CRUD.
 */
final class AdminController extends Controller
{
    /** Ledger reasons in the words the ledger page uses. */
    private const LEDGER_REASONS = [
        'sponsor_contribution' => 'Sponsor contribution',
        'welcome_bonus'        => 'Welcome bonus',
        'moderator_stipend'    => 'Moderator stipend',
        'community_reward'     => 'Community reward',
        'reserve_topup'        => 'Reserve top-up',
        'damage_penalty'       => 'Damage penalty',
        'aid_return'           => 'Aid return',
        'bond_forfeit'         => 'Bond forfeited',
        'account_closure'      => 'Account closure (Type A)',
        'parting_gift'         => 'Parting gift (Type B)',
        'recycle'              => 'Retired recycling',
        'rental_charge'        => 'Rental charge',
        'rental_payout'        => 'Rental payout',
        'late_fee'             => 'Late fee',
        'gift'                 => 'Gift',
        'aid_grant'            => 'Aid grant',
        'bond_hold'            => 'Bond hold',
        'bond_return'          => 'Bond return',
        'shortfall_cover'      => 'Reserve shortfall cover',
        'buffer_hold'          => 'Buffer hold',
        'buffer_refund'        => 'Buffer refund',
    ];

    /** Account status => badge class. */
    private const USER_BADGES = [
        'active'          => 'success',
        'pending'         => 'warning',
        'suspended'       => 'error',
        'closed_standard' => 'neutral',
        'closed_donation' => 'neutral',
    ];

    /**
     * GET routes mapped by view name.
     *
     * @param array{id?: string} $params the {id} from the URL, when the route has one
     */
    public function show(string $view, array $params = []): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;

        if ($view === 'admin/moderators/appoint' && $id === 0) {
            $id = (int) ($_GET['division'] ?? 0);

            if ($id === 0) {
                $this->redirect('/admin/moderators');

                return;
            }
        }

        $data = match ($view) {
            'admin/dashboard/index'        => $this->dashboard(),
            'admin/divisions/index'        => $this->divisions(),
            'admin/divisions/show'         => $this->division($id),
            'admin/divisions/approvals'    => $this->divisionApprovals($id),
            'admin/moderators/index'       => $this->moderators(),
            'admin/moderators/performance' => $this->moderatorPerformance(),
            'admin/moderators/appoint'     => $this->appointment($id),
            'admin/moderators/show'        => $this->moderator($id),
            'admin/disputes/index'         => $this->disputes(),
            'admin/disputes/show'          => $this->dispute($id),
            'admin/disaster/index'         => $this->disaster(),
            'admin/categories/index'       => $this->categories(),
            'admin/pools/index'            => $this->pools(),
            'admin/pools/reserve'          => $this->reserve(),
            'admin/pools/sponsor-ledger'   => $this->sponsorLedger(),
            'admin/pools/policies'         => $this->policies(),
            'admin/ledger/index'           => $this->ledger(),
            'admin/users/index'            => $this->users(),
            'admin/users/show'             => $this->user($id),
            'admin/cron/index'             => $this->cron(),
            'admin/notifications/index'    => $this->notifications(),
            'admin/settings/profile',
            'admin/settings/security',
            'admin/settings/notifications' => $this->settings(),
            // Design previews with no backend yet render their own sample content.
            default                        => [],
        };

        if ($data === null) {
            $this->notice(404, 'Record not found', 'It may have been removed. Return to the list to choose another.');

            return;
        }

        $this->render($view, $data);
    }

    // ── Division CRUD ───────────────────────────────────────────────────────

    /**
     * POST /admin/divisions.
     */
    public function createDivision(): void
    {
        $validator = $this->divisionInput();

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $id = $this->divisionService()->create($validator->value('name'), $validator->value('district'));
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/admin/divisions');

            return;
        }

        $this->flash($validator->value('name') . ' division created.');
        $this->redirect('/admin/divisions/' . $id);
    }

    /**
     * POST /admin/divisions/{id}.
     */
    public function updateDivision(int $id): void
    {
        $validator = $this->divisionInput();

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $this->divisionService()->update($id, $validator->value('name'), $validator->value('district'));
            $this->flash('Division details saved.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Division not found', 'Return to the division list to choose another.');

            return;
        }

        $this->redirect('/admin/divisions/' . $id);
    }

    /**
     * POST /admin/divisions/{id}/archive — the soft delete.
     */
    public function archiveDivision(int $id): void
    {
        try {
            $name = $this->divisionService()->archive($id);
            $this->flash($name . ' division archived. It no longer accepts new members.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Division not found', 'Return to the division list to choose another.');

            return;
        }

        $this->redirect('/admin/divisions');
    }

    private function divisionInput(): Validator
    {
        return (new Validator($_POST))
            ->required('name', 'Division name')
            ->maxLength('name', 'Division name', 120)
            ->required('district', 'District')
            ->maxLength('district', 'District', 100);
    }

    private function divisionService(): DivisionService
    {
        return new DivisionService(new GnDivision($this->pdo));
    }

    // ── Dashboard ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function dashboard(): array
    {
        $users     = new User($this->pdo);
        $bookings  = new Booking($this->pdo);
        $disputes  = new Dispute($this->pdo);
        $cronRuns  = new CronRun($this->pdo);
        $members   = $users->countByRole('member');
        $invariant = $cronRuns->lastInvariantResult();
        $admin     = (string) ($users->find($this->userId())['full_name'] ?? '');

        return [
            'admin'      => ['name' => User::shortName($admin)],
            'globalMeta' => [
                'division_count' => (new GnDivision($this->pdo))->countAll(),
                'member_count'   => number_format($members),
            ],
            'stats' => [
                ['label' => 'Total members',    'value' => number_format($members), 'note' => '+' . $users->countNewMembersThisMonth() . ' this month'],
                ['label' => 'Active bookings',  'value' => number_format($bookings->countActive()), 'note' => number_format($bookings->activeEscrowPoints()) . ' pts in escrow'],
                ['label' => 'Open disputes',    'value' => (string) $disputes->countOpen(), 'note' => $disputes->countPastTimer() . ' past 7-day timer'],
                ['label' => 'Points in system', 'value' => number_format((new PointPool($this->pdo))->totalBalance()), 'note' => 'All wallets + pools'],
            ],
            'invariant' => $this->invariantSummary($invariant),
            'cronJobs'  => array_map(fn (array $job): array => $this->jobRow($job), $cronRuns->recentJobs(5)),
        ];
    }

    // ── Divisions ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function divisions(): array
    {
        return [
            'divisions' => array_map(
                fn (array $row): array => [
                    'id'             => (int) $row['id'],
                    'name'           => (string) $row['name'],
                    'district'       => (string) $row['district'],
                    'member_count'   => (int) $row['member_count'],
                    'moderator_name' => $row['moderator_name'],
                    'href'           => base_url() . '/admin/divisions/' . $row['id'],
                ] + $this->divisionBadge($row),
                (new GnDivision($this->pdo))->allWithStaff()
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function division(int $id): ?array
    {
        $divisions = new GnDivision($this->pdo);
        $row       = $divisions->findWithStaff($id);

        if ($row === null) {
            return null;
        }

        $stats = $divisions->divisionStats($id);

        return [
            'division' => [
                'id'              => (int) $row['id'],
                'name'            => (string) $row['name'],
                'district'        => (string) $row['district'],
                'archived'        => $row['status'] === 'archived',
                'moderator_name'  => $row['moderator_name'],
                'moderator_since' => $row['moderator_since'] === null ? '' : date('j M Y', strtotime((string) $row['moderator_since'])),
            ] + $this->divisionBadge($row),
            'stats' => [
                ['label' => 'Members',         'value' => (string) $stats['members'],      'note' => 'verified residents'],
                ['label' => 'Active listings', 'value' => (string) $stats['items_listed'], 'note' => 'not archived or rejected'],
                ['label' => 'Open disputes',   'value' => (string) $stats['disputes'],     'note' => 'awaiting a ruling', 'error' => $stats['disputes'] > 0],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function divisionApprovals(int $id): ?array
    {
        $divisions = new GnDivision($this->pdo);
        $row       = $divisions->findWithStaff($id);

        if ($row === null) {
            return null;
        }

        $counts = $divisions->approvalStats($id);

        return [
            'division'      => ['id' => $id, 'name' => (string) $row['name']],
            'approvalStats' => [
                ['label' => 'Pending requests',            'value' => (string) $counts['pending']],
                ['label' => 'Approved members',            'value' => (string) $counts['approved']],
                ['label' => 'Progress to first moderator', 'value' => min($counts['approved'], 10) . ' / 10'],
            ],
            'pendingMembers' => array_map(
                fn (array $member): array => $this->applicantRow($member),
                $divisions->pendingApprovals($id)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{status: string, status_label: string}
     */
    private function divisionBadge(array $row): array
    {
        if ($row['status'] === 'archived') {
            return ['status' => 'neutral', 'status_label' => 'Archived'];
        }

        if (!empty($row['disaster_mode_active'])) {
            return ['status' => 'error', 'status_label' => 'Disaster mode'];
        }

        return $row['moderator_name'] === null
            ? ['status' => 'warning', 'status_label' => 'No moderator']
            : ['status' => 'success', 'status_label' => 'Active'];
    }

    // ── Moderators ──────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function moderators(): ?array
    {
        $divisions = new GnDivision($this->pdo);
        $rows      = $divisions->allWithStaff();

        if ($rows === []) {
            return null;
        }

        $requested = (int) ($_GET['division'] ?? 0);
        $ids       = array_map('intval', array_column($rows, 'id'));
        $id        = in_array($requested, $ids, true) ? $requested : $ids[0];
        $selected  = $rows[array_search($id, $ids, true)];
        $members   = (int) $selected['member_count'];
        $phase     = $selected['moderator_name'] !== null ? 3 : ($members < 10 ? 1 : 2);
        $labels    = [1 => 'Admin-run division', 2 => 'First moderator appointment', 3 => 'Moderator management'];

        $candidates = $this->candidateRows($id);
        $active     = array_values(array_filter(
            (new Moderator($this->pdo))->allActive(),
            static fn (array $row): bool => (int) $row['division_id'] === $id
        ));

        return [
            'selectedDivision' => $id,
            'division'         => ['id' => $id, 'name' => (string) $selected['name']],
            'divisions'        => array_map(static fn (array $row): array => $row + ['active' => (int) $row['id'] === $id], $rows),
            'phase'            => [
                'number'      => $phase,
                'label'       => $labels[$phase],
                'description' => $selected['name'] . ' · ' . $members . ' verified members',
            ],
            'divisionStats' => [
                ['label' => 'Members',           'value' => (string) $members],
                ['label' => 'Active moderators', 'value' => (string) count($active)],
                ['label' => 'Candidates',        'value' => (string) count($candidates)],
            ],
            'candidates'      => $candidates,
            'verifiedMembers' => $candidates,
            'eligibilityPool' => $candidates,
            'pendingMembers'  => array_map(fn (array $member): array => $this->applicantRow($member), $divisions->pendingApprovals($id)),
            'activeMods'      => array_map(static fn (array $row): array => [
                'initials'         => User::initials((string) $row['full_name']),
                'name'             => (string) $row['full_name'],
                'division'         => (string) $row['division_name'],
                'appointed_at'     => (string) $row['appointed_at'],
                'objection_status' => $row['status'] === 'active' ? 'success' : 'warning',
                'objection_label'  => ucfirst((string) $row['status']),
                'trust_score'      => (int) $row['trust_score'],
                'bond'             => $row['bond_points'] . ' pts',
                'href'             => base_url() . '/admin/moderators/' . $row['user_id'],
            ], $active),
        ];
    }

    /** @return array<string, mixed> */
    private function moderatorPerformance(): array
    {
        return [
            'moderators' => array_map(static fn (array $row): array => [
                'initials'     => User::initials((string) $row['full_name']),
                'name'         => (string) $row['full_name'],
                'division'     => (string) $row['division_name'],
                'meta'         => sprintf(
                    'Trust %d · %d items reviewed · %d disputes resolved · bond %d pts',
                    (int) $row['trust_score'],
                    (int) $row['items_reviewed'],
                    (int) $row['disputes_resolved'],
                    (int) $row['bond_points']
                ),
                'status'       => $row['status'] === 'active' ? 'success' : 'info',
                'status_label' => ucfirst((string) $row['status']),
                'action_href'  => base_url() . '/admin/moderators/' . $row['user_id'],
            ], (new Moderator($this->pdo))->allWithPerformance()),
        ];
    }

    /** @return array<string, mixed>|null */
    private function appointment(int $divisionId): ?array
    {
        $row = (new GnDivision($this->pdo))->findWithStaff($divisionId);

        if ($row === null) {
            return null;
        }

        $candidates = $this->candidateRows($divisionId);
        $chosen     = (int) ($_GET['member'] ?? 0);
        $selected   = null;

        foreach ($candidates as $candidate) {
            if ($candidate['id'] === $chosen) {
                $selected = $candidate;
            }
        }

        return [
            'division'    => ['id' => $divisionId, 'name' => (string) $row['name']],
            'candidates'  => $candidates,
            'selected'    => $selected,
            'currentStep' => $selected === null ? 1 : 2,
            // The objection window only opens once an appointment is made.
            'objections'  => [],
            'countdown'   => 'Opens when the appointment is made',
        ];
    }

    /** @return array<string, mixed>|null */
    private function moderator(int $userId): ?array
    {
        $moderators = new Moderator($this->pdo);
        $row        = $moderators->findByUserId($userId);

        if ($row === null) {
            return null;
        }

        $performance = [];
        foreach ($moderators->allWithPerformance() as $candidate) {
            if ((int) $candidate['user_id'] === $userId) {
                $performance = $candidate;
            }
        }

        $months = (int) floor((time() - strtotime((string) $row['appointed_at'])) / (30 * 86400));

        return [
            'moderator' => [
                'initials'     => User::initials((string) $row['full_name']),
                'name'         => (string) $row['full_name'],
                'division'     => (string) $row['division_name'],
                'status'       => $row['status'] === 'active' ? 'success' : 'info',
                'status_label' => ucfirst((string) $row['status']),
                'appointed_at' => date('j M Y', strtotime((string) $row['appointed_at'])),
            ],
            'bond' => [
                'value'        => (int) $row['bond_points'],
                'status'       => match ($row['bond_status']) {
                    'held'      => 'success',
                    'forfeited' => 'error',
                    default     => 'neutral',
                },
                'status_label' => ucfirst((string) $row['bond_status']),
            ],
            'activityStats' => [
                ['label' => 'Listings reviewed', 'value' => (string) (int) ($performance['items_reviewed'] ?? 0)],
                ['label' => 'Disputes resolved', 'value' => (string) (int) ($performance['disputes_resolved'] ?? 0)],
                ['label' => 'Months active',     'value' => (string) max(0, $months)],
            ],
        ];
    }

    /**
     * Members of one division eligible for appointment (Plan §16.2).
     *
     * @return list<array<string, mixed>>
     */
    private function candidateRows(int $divisionId): array
    {
        $rows = (new Moderator($this->pdo))->eligibleCandidates($divisionId);

        return array_map(static fn (array $row, int $index): array => [
            'id'           => (int) $row['id'],
            'name'         => (string) $row['full_name'],
            'initials'     => User::initials((string) $row['full_name']),
            'trust_score'  => (int) $row['trust_score'],
            'address'      => (string) $row['address'],
            'verified_at'  => (string) ($row['verified_at'] ?? ''),
            'member_since' => $row['joined_at'] === null ? '' : date('M Y', strtotime((string) $row['joined_at'])),
            'months'       => $row['joined_at'] === null ? 0 : max(1, (int) round((time() - strtotime((string) $row['joined_at'])) / (30 * 86400))),
            'transactions' => (int) $row['completed_bookings'],
            'record'       => (int) $row['disputes'] === 0 ? 'Clean' : $row['disputes'] . ' disputes',
            'gn_endorsed'  => (bool) $row['gn_endorsed'],
            // Conflict-of-interest checks are not built yet, so none is reported.
            'conflict'     => null,
            'recommended'  => $index === 0,
        ], $rows, array_keys($rows));
    }

    /**
     * @param array<string, mixed> $member a pending_approvals row
     *
     * @return array<string, mixed>
     */
    private function applicantRow(array $member): array
    {
        return [
            'id'          => (int) $member['id'],
            'initials'    => User::initials((string) $member['full_name']),
            'name'        => (string) $member['full_name'],
            'nic_ending'  => substr((string) $member['nic'], -4),
            'address'     => (string) $member['address'],
            'applied_ago' => 'Applied ' . date('j M Y', strtotime((string) $member['applied_at'])),
        ];
    }

    // ── Disputes ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function disputes(): array
    {
        return [
            'disputes' => array_map(fn (array $row): array => [
                'title'        => (string) ($row['item_title'] ?? 'Dispute #' . $row['id']),
                'case_number'  => $this->caseNumber((int) $row['id']),
                'division'     => (string) ($row['division_name'] ?? ''),
                'parties'      => ($row['lender_name'] ?? '') . ' vs ' . ($row['borrower_name'] ?? ''),
                'escalated_at' => date('d M Y', strtotime((string) $row['created_at'])),
                'reason'       => (int) $row['days_open'] . ' days open',
                'status'       => (int) $row['days_open'] > 7 ? 'error' : 'warning',
                'status_label' => (int) $row['days_open'] > 7 ? 'Past timer' : 'Open',
                'href'         => base_url() . '/admin/disputes/' . $row['id'],
            ], (new Dispute($this->pdo))->openList()),
        ];
    }

    /** @return array<string, mixed>|null */
    private function dispute(int $id): ?array
    {
        $row = (new Dispute($this->pdo))->findWithHistory($id);

        if ($row === null) {
            return null;
        }

        $history = [[
            'text' => sprintf('Dispute raised: %s', $row['reason']),
            'date' => date('j M Y', strtotime((string) $row['created_at'])),
        ]];

        if ($row['ruling_at'] !== null) {
            $history[] = [
                'text' => 'Ruling recorded: ' . ($row['resolution'] ?? ''),
                'date' => date('j M Y', strtotime((string) $row['ruling_at'])),
            ];
        }

        return [
            'dispute' => [
                'title'        => (string) ($row['item_title'] ?? 'Dispute'),
                'case_number'  => $this->caseNumber($id),
                'status'       => $row['status'] === 'open' ? 'error' : 'success',
                'status_label' => ucfirst((string) $row['status']),
            ],
            'history'      => $history,
            'proposed_pts' => '',
        ];
    }

    private function caseNumber(int $id): string
    {
        return 'DC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    // ── Disaster mode, categories ───────────────────────────────────────────

    /** @return array<string, mixed> */
    private function disaster(): array
    {
        return [
            'divisions' => array_map(static fn (array $row): array => [
                'id'           => (int) $row['id'],
                'name'         => (string) $row['name'],
                'active'       => (bool) $row['disaster_mode_active'],
                'meta'         => $row['disaster_mode_active']
                    ? 'Disaster mode active'
                    : 'Mod: ' . ($row['moderator_name'] ?? 'vacant') . ' · no active disaster',
                'status'       => $row['disaster_mode_active'] ? 'error' : 'success',
                'status_label' => $row['disaster_mode_active'] ? 'Active' : 'Normal',
            ], (new GnDivision($this->pdo))->allWithStaff()),
        ];
    }

    /** @return array<string, mixed> */
    private function categories(): array
    {
        return [
            'categories' => array_map(static fn (array $row): array => [
                'id'            => (int) $row['id'],
                'name'          => (string) $row['name'],
                'listing_count' => (int) $row['listing_count'],
                'status'        => $row['active'] ? 'success' : 'neutral',
                'status_label'  => $row['active'] ? 'Active' : 'Hidden',
            ], (new ItemCategory($this->pdo))->allWithListingCount()),
        ];
    }

    // ── Pools and ledger ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function pools(): array
    {
        $pools = (new PointPool($this->pdo))->all();
        $notes = [
            'sponsor'        => 'General contributions · welcome bonuses · stipends · bonds · rewards',
            'aid'            => 'Aid contributions · parting gifts · approved aid grants only',
            'reserve'        => 'Covers shortfalls — no negative balances',
            'in_flight'      => 'Rental charges + late-fee buffers',
            'member_wallets' => 'Sum of all member wallet balances',
            'retired'        => 'Type A closures, awaiting recycling to the Sponsor Pool',
        ];

        return [
            'pools' => array_map(static fn (array $pool): array => [
                'label' => (string) $pool['name'],
                'value' => number_format((int) $pool['balance']) . ' pts',
                'note'  => $notes[$pool['pool_code']] ?? '',
            ], $pools),
            'invariant' => $this->invariantSummary((new CronRun($this->pdo))->lastInvariantResult())
                + ['total' => number_format(array_sum(array_column($pools, 'balance'))) . ' pts'],
            'jobs' => array_map(fn (array $job): array => $this->jobRow($job), (new CronRun($this->pdo))->recentJobs(3)),
        ];
    }

    /** @return array<string, mixed> */
    private function reserve(): array
    {
        $covers = new ShortfallCover($this->pdo);
        $year   = $covers->yearStats();

        return [
            'stats' => [
                ['label' => 'Reserve Pool balance',                   'value' => number_format((new PointPool($this->pdo))->balance('reserve')) . ' pts', 'note' => 'Safety net — no member balance goes negative'],
                ['label' => 'Shortfalls covered (' . date('Y') . ')', 'value' => number_format($year['total_pts']) . ' pts',   'note' => $year['covers'] . ' covers paid to lenders'],
                ['label' => 'Top-ups from Sponsor Pool',              'value' => number_format($covers->topUpsThisYear()) . ' pts', 'note' => 'Recorded by the Sponsor Liaison this year'],
            ],
            'covers' => array_map(static fn (array $cover): array => [
                'initials' => User::initials((string) ($cover['borrower_name'] ?? '')),
                'title'    => ($cover['borrower_name'] ?? 'Unknown borrower') . ' → ' . ($cover['lender_name'] ?? 'lender'),
                'meta'     => trim(($cover['division_name'] ?? '') . ' · booking #' . ($cover['booking_id'] ?? '—')
                    . ' · ' . date('j M Y', strtotime((string) $cover['created_at'])), ' ·'),
                'amount'   => '+' . number_format((int) $cover['amount']) . ' pts',
            ], $covers->recent()),
        ];
    }

    /** @return array<string, mixed> */
    private function sponsorLedger(): array
    {
        $inflows  = (new Sponsor($this->pdo))->allContributions();
        $received = array_sum(array_map('intval', array_column($inflows, 'points')));
        $held     = (new PointPool($this->pdo))->balance('sponsor');

        return [
            'summary' => [
                'totalReceived' => ['value' => number_format($received) . ' pts', 'sub' => 'Rs ' . number_format($received) . ' converted at 1:1'],
                'totalUsed'     => ['value' => number_format($received - $held) . ' pts', 'sub' => 'Welcome bonuses · stipends · bonds'],
                'remaining'     => ['value' => number_format($held) . ' pts', 'sub' => 'Held in the Sponsor Pool'],
            ],
            'inflows' => array_map(static fn (array $row): array => [
                'date'         => date('d M Y', strtotime((string) $row['recorded_at'])),
                'sponsor'      => (string) $row['company_name'],
                'ref'          => (string) $row['receipt_number'],
                'category'     => sprintf('General %s · Aid %s', number_format((int) $row['general_points']), number_format((int) $row['aid_points'])),
                'cash'         => 'Rs ' . number_format((int) $row['cash_amount']),
                'pts'          => '+' . number_format((int) $row['points']),
                'status'       => 'success',
                'status_label' => 'Settled',
            ], $inflows),
        ];
    }

    /** @return array<string, mixed> */
    private function policies(): array
    {
        // Values the code enforces come from their constants; the rest are the
        // plan's figures until their modules are built.
        return [
            'groups' => [
                'Earning' => [
                    ['label' => 'Welcome bonus',     'value' => VerificationService::WELCOME_BONUS . ' pts, once, from the Sponsor Pool after moderator verification'],
                    ['label' => 'Moderator stipend', 'value' => '100 pts / month from the Sponsor Pool when the moderator acted that month'],
                ],
                'Gifting' => [
                    ['label' => 'Daily gift cap',  'value' => number_format(Gift::DAILY_CAP) . ' pts per member per day'],
                    ['label' => 'Annual gift cap', 'value' => number_format(Gift::ANNUAL_CAP) . ' pts per member per year'],
                ],
                'Moderation' => [
                    ['label' => 'Moderator bond',           'value' => '500 pts conduct bond, locked on appointment'],
                    ['label' => 'Dispute escalation timer', 'value' => 'Moderator disputes escalate to the Admin after 7 days'],
                ],
                'Aid' => [
                    ['label' => 'Aid grant cap', 'value' => '500 pts per member per year · one active grant · 60-day cooling period'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function ledger(): array
    {
        $filter = (string) ($_GET['filter'] ?? '');
        $filter = array_key_exists($filter, PointLedger::GROUPS) ? $filter : '';
        $search = trim((string) ($_GET['q'] ?? ''));
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = (new PointLedger($this->pdo))->adminList($filter, $search, $page);

        $filters = [['label' => 'All types', 'slug' => '', 'active' => $filter === '']];
        foreach (array_keys(PointLedger::GROUPS) as $group) {
            $filters[] = ['label' => ucfirst($group), 'slug' => $group, 'active' => $filter === $group];
        }

        return [
            'filters'     => $filters,
            'filter'      => $filter,
            'search'      => $search,
            'page'        => $page,
            'hasNextPage' => $page * $result['per_page'] < $result['total'],
            'entries'     => array_map(static function (array $row): array {
                $incoming = $row['to_user_id'] !== null;

                return [
                    'ref'          => 'TXN-' . $row['id'],
                    'date'         => date('d M Y', strtotime((string) $row['created_at'])),
                    'title'        => self::LEDGER_REASONS[$row['reason']] ?? ucfirst(str_replace('_', ' ', (string) $row['reason'])),
                    'meta'         => ($row['from_pool_code'] ?? $row['from_user_name'] ?? '—') . ' → ' . ($row['to_pool_code'] ?? $row['to_user_name'] ?? '—'),
                    'amount'       => ($incoming ? '+' : '−') . number_format((int) $row['amount']) . ' pts',
                    'amount_class' => $incoming ? 'success' : 'error',
                ];
            }, $result['rows']),
        ];
    }

    // ── Users ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function users(): array
    {
        $users  = new User($this->pdo);
        $status = (string) ($_GET['status'] ?? '');
        $status = array_key_exists($status, self::USER_BADGES) ? $status : '';
        $search = trim((string) ($_GET['q'] ?? ''));

        $filters = [];
        foreach (['' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'] as $slug => $label) {
            $filters[] = ['label' => $label, 'slug' => $slug, 'active' => $status === $slug];
        }

        return [
            'stats' => [
                ['label' => 'Total users',    'value' => number_format($users->countAll())],
                ['label' => 'Active',         'value' => number_format($users->countByStatus('active'))],
                ['label' => 'Suspended',      'value' => number_format($users->countByStatus('suspended'))],
                ['label' => 'New this month', 'value' => number_format($users->countJoinedThisMonth())],
            ],
            'filters' => $filters,
            'status'  => $status,
            'search'  => $search,
            'users'   => array_map(fn (array $row): array => [
                'initials' => User::initials((string) $row['full_name']),
                'name'     => (string) $row['full_name'],
                'division' => (string) ($row['division_name'] ?? '—'),
                'role'     => (string) $row['role_name'],
                'balance'  => number_format((int) $row['balance']) . ' pts',
                'href'     => base_url() . '/admin/users/' . $row['id'],
            ] + $this->userBadge((string) $row['status']), $users->adminList($status, $search)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function user(int $id): ?array
    {
        $users = new User($this->pdo);
        // Staff accounts (liaison, admin, sponsor) belong to no division.
        $row   = $users->findWithDivision($id) ?? $users->find($id);

        if ($row === null) {
            return null;
        }

        $stats = $users->profileStats($id);

        return [
            'user' => [
                'initials'    => User::initials((string) $row['full_name']),
                'name'        => (string) $row['full_name'],
                'email'       => (string) ($row['email'] ?? '') ?: '—',
                'phone'       => (string) $row['phone'],
                'trust_score' => (int) $row['trust_score'],
                'division'    => (string) ($row['division_name'] ?? '—'),
                'role'        => $users->roleName($id),
                'balance'     => number_format((new Wallet($this->pdo))->balance($id)) . ' pts',
                'joined_at'   => $row['joined_at'] === null ? '—' : date('j M Y', strtotime((string) $row['joined_at'])),
            ] + $this->userBadge((string) $row['status']),
            'stats' => [
                ['label' => 'Items listed', 'value' => (string) $stats['items']],
                ['label' => 'Transactions', 'value' => (string) $stats['completed']],
                ['label' => 'Disputes',     'value' => (string) $stats['disputes']],
            ],
            // The account's point movements, newest first.
            'activity' => array_map(static fn (array $entry): array => [
                'icon_type' => $entry['incoming'] ? 'return' : 'lend',
                'title'     => (self::LEDGER_REASONS[$entry['reason']] ?? ucfirst(str_replace('_', ' ', (string) $entry['reason'])))
                    . ' · ' . ($entry['incoming'] ? '+' : '−') . number_format((int) $entry['amount']) . ' pts',
                'meta'      => date('j M Y', strtotime((string) $entry['created_at'])),
            ], (new Wallet($this->pdo))->activity($id, 5)),
        ];
    }

    /**
     * @return array{status: string, status_label: string}
     */
    private function userBadge(string $status): array
    {
        return [
            'status'       => self::USER_BADGES[$status] ?? 'neutral',
            'status_label' => ucfirst(str_replace('_', ' ', $status)),
        ];
    }

    // ── Cron, notifications, settings ───────────────────────────────────────

    /** @return array<string, mixed> */
    private function cron(): array
    {
        return [
            'jobs' => array_map(fn (array $job): array => $this->jobRow($job) + [
                'description' => (string) ($job['notes'] ?? ''),
            ], (new CronRun($this->pdo))->allJobs()),
        ];
    }

    /**
     * The Admin's feed is built from platform events — disputes, pool health,
     * account actions, staffing — not from rows addressed to the Admin.
     *
     * @return array<string, mixed>
     */
    private function notifications(): array
    {
        $type    = (string) ($_GET['type'] ?? '');
        $cronRuns = new CronRun($this->pdo);
        $notices = [];

        foreach ((new Dispute($this->pdo))->recentOpen(10) as $row) {
            $notices[] = [
                'icon'       => '⚠',
                'category'   => 'disputes',
                'title'      => 'Open dispute — case #' . $this->caseNumber((int) $row['id']),
                'meta'       => ($row['lender_name'] ?? '?') . ' vs ' . ($row['borrower_name'] ?? '?') . ' · ' . $row['reason'],
                'created_at' => (string) $row['created_at'],
                'read'       => (time() - strtotime((string) $row['created_at'])) <= 7 * 86400,
            ];
        }

        $invariant = $cronRuns->lastInvariantResult();
        if ($invariant !== null) {
            $passed    = $invariant['status'] === 'success';
            $notices[] = [
                'icon'       => $passed ? '✓' : '✕',
                'category'   => 'pools',
                'title'      => $passed ? 'Invariant check passed' : 'Invariant check FAILED',
                'meta'       => (string) ($invariant['notes'] ?? ''),
                'created_at' => (string) ($invariant['finished_at'] ?? $invariant['started_at']),
                'read'       => $passed,
            ];
        }

        foreach ((new Sponsor($this->pdo))->recentContributions(5) as $row) {
            $notices[] = [
                'icon'       => '⚡',
                'category'   => 'pools',
                'title'      => 'Sponsor contribution recorded — ' . $row['receipt_number'],
                'meta'       => $row['company_name'] . ' · +' . number_format((int) $row['points']) . ' pts',
                'created_at' => (string) $row['recorded_at'],
                'read'       => true,
            ];
        }

        foreach ((new User($this->pdo))->recentlySuspended(5) as $row) {
            $notices[] = [
                'icon'       => '🔒',
                'category'   => 'users',
                'title'      => 'Account suspended — ' . $row['full_name'],
                'meta'       => 'Status set to suspended',
                'created_at' => (string) $row['updated_at'],
                'read'       => true,
            ];
        }

        $divisions = new GnDivision($this->pdo);

        foreach ($divisions->recentPendingApprovals(5) as $row) {
            $notices[] = [
                'icon'       => '📋',
                'category'   => 'users',
                'title'      => 'New verification pending — ' . $row['full_name'],
                'meta'       => $row['division_name'] . ' · awaiting moderator approval',
                'created_at' => (string) $row['created_at'],
                'read'       => false,
            ];
        }

        foreach ((new Moderator($this->pdo))->recentAppointments(5) as $row) {
            $notices[] = [
                'icon'       => '👤',
                'category'   => 'system',
                'title'      => 'Moderator appointed — ' . $row['full_name'],
                'meta'       => $row['division_name'] . ' division',
                'created_at' => (string) $row['appointed_at'],
                'read'       => true,
            ];
        }

        foreach ($divisions->vacant() as $row) {
            $notices[] = [
                'icon'       => '⚠',
                'category'   => 'system',
                'title'      => 'Moderator vacancy — ' . $row['name'],
                'meta'       => 'No moderator appointed for this division',
                'created_at' => (string) $row['created_at'],
                'read'       => false,
            ];
        }

        foreach ($cronRuns->recentFailed(5) as $row) {
            $notices[] = [
                'icon'       => '✕',
                'category'   => 'system',
                'title'      => 'Cron job failed — ' . str_replace('_', ' ', (string) $row['job_name']),
                'meta'       => (string) ($row['notes'] ?? 'Check logs for details'),
                'created_at' => (string) $row['started_at'],
                'read'       => false,
            ];
        }

        if ($type !== '') {
            $notices = array_values(array_filter($notices, static fn (array $notice): bool => $notice['category'] === $type));
        }

        usort($notices, static fn (array $a, array $b): int => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        $filters = [];
        foreach (['' => 'All', 'disputes' => 'Disputes', 'pools' => 'Pools', 'users' => 'Users', 'system' => 'System'] as $slug => $label) {
            $filters[] = ['label' => $label, 'slug' => $slug, 'active' => $type === $slug];
        }

        return [
            'filters' => $filters,
            'notices' => array_map(function (array $notice): array {
                $notice['time'] = $this->relativeTime($notice['created_at']);
                unset($notice['category'], $notice['created_at']);

                return $notice;
            }, array_slice($notices, 0, 20)),
        ];
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $admin = (new User($this->pdo))->find($this->userId()) ?? [];

        return [
            'admin' => [
                'name'     => (string) ($admin['full_name'] ?? ''),
                'email'    => (string) ($admin['email'] ?? ''),
                'phone'    => (string) ($admin['phone'] ?? ''),
                'division' => 'All divisions',
                'role'     => 'System Administrator',
                'joined'   => empty($admin['joined_at']) ? '—' : date('F Y', strtotime((string) $admin['joined_at'])),
            ],
        ];
    }

    // ── Shared formatting ───────────────────────────────────────────────────

    /**
     * @param array<string, mixed>|null $run the last invariant cron run
     *
     * @return array{passed: bool, last_run: string, summary: string}
     */
    private function invariantSummary(?array $run): array
    {
        return [
            'passed'   => $run !== null && $run['status'] === 'success',
            'last_run' => empty($run['finished_at']) ? 'never' : date('j M, H:i', strtotime((string) $run['finished_at'])),
            'summary'  => (string) ($run['notes'] ?? 'No invariant run recorded yet'),
        ];
    }

    /**
     * @param array<string, mixed> $job a cron_runs row
     *
     * @return array{name: string, last_run: string, status: string, status_label: string}
     */
    private function jobRow(array $job): array
    {
        return [
            'name'         => ucfirst(str_replace('_', ' ', (string) $job['job_name'])),
            'last_run'     => empty($job['started_at']) ? 'Never run' : 'Last run ' . date('j M, H:i', strtotime((string) $job['started_at'])),
            'status'       => match ($job['status']) {
                'success' => 'success',
                'failed'  => 'error',
                default   => 'info',
            },
            'status_label' => ucfirst((string) $job['status']),
        ];
    }

    private function relativeTime(string $datetime): string
    {
        $seconds = time() - strtotime($datetime);

        return match (true) {
            $seconds < 60     => 'just now',
            $seconds < 3600   => intdiv($seconds, 60) . ' min ago',
            $seconds < 86400  => intdiv($seconds, 3600) . ' hours ago',
            $seconds < 172800 => 'Yesterday',
            $seconds < 604800 => intdiv($seconds, 86400) . ' days ago',
            default           => intdiv($seconds, 604800) . (intdiv($seconds, 604800) === 1 ? ' week ago' : ' weeks ago'),
        };
    }
}
