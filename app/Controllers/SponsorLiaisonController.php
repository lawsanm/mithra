<?php

declare(strict_types=1);

/**
 * The Sponsor Liaison's screens.
 *
 * Sponsors are the working CRUD (Plan §20.4 module 4.2): list, view, onboard,
 * edit, deactivate and reactivate, read from and written to the database.
 * Onboarding can also create the company's sponsor login (Plan §15.10).
 *
 * Contributions and aid grants read the same records used by member and admin
 * screens. Their submission and approval actions remain unavailable.
 */
final class SponsorLiaisonController extends Controller
{
    private const SORTS = ['name', 'recently_added'];

    public function sponsors(): void
    {
        $search = $this->queryValue('q');
        $sort   = in_array($this->queryValue('sort'), self::SORTS, true) ? $this->queryValue('sort') : '';
        $status = array_key_exists($this->queryValue('status'), SponsorService::AGREEMENT_STATUSES)
            ? $this->queryValue('status')
            : '';
        $page   = max(1, (int) $this->queryValue('page'));

        $sponsors = $this->sponsorModel();
        $rows     = array_map(fn (array $row): array => $this->sponsorRow($row), $sponsors->search($search, $status, $sort, $page));
        $total    = $sponsors->countSearch($search, $status);

        $this->render('sponsor-liaison/sponsors/index', [
            'sponsors'          => $rows,
            'search'            => $search,
            'sort'              => $sort,
            'status'            => $status,
            'agreementStatuses' => SponsorService::AGREEMENT_STATUSES,
            'page'              => $page,
            'hasNextPage'       => $page * Sponsor::PER_PAGE < $total,
        ]);
    }

    /**
     * GET /sponsor-liaison/sponsors/{id} — one sponsor's profile.
     */
    public function sponsor(int $id): void
    {
        $row = $this->sponsorModel()->findProfile($id);

        if ($row === null) {
            $this->sponsorNotFound();

            return;
        }

        $this->render('sponsor-liaison/sponsors/show', ['record' => $this->sponsorRow($row) + [
            'contact_name'       => (string) ($row['contact_name'] ?? ''),
            'contact_phone'      => (string) ($row['contact_phone'] ?? ''),
            'agreement_details'  => (string) ($row['agreement_details'] ?? ''),
            'internal_notes'     => (string) ($row['internal_notes'] ?? ''),
            'login'              => $this->loginLabel($row),
            'account'            => $row['user_id'] === null
                ? 'Not linked yet'
                : trim((string) $row['account_name'] . ' · ' . (string) ($row['account_email'] ?? ''), ' ·'),
            'contributions'      => (int) $row['contribution_count'] === 0
                ? 'None recorded yet'
                : $row['contribution_count'] . ' · last on ' . date('j M Y', strtotime((string) $row['last_contribution_at'])),
            'onboarded'          => date('j M Y', strtotime((string) $row['created_at'])),
        ]]);
    }

    // ── Sponsors: create ────────────────────────────────────────────────────

    /**
     * GET /sponsor-liaison/sponsors/onboarding.
     */
    public function createForm(): void
    {
        $this->renderSponsorForm('sponsor-liaison/sponsors/onboarding', [], []);
    }

    /**
     * POST /sponsor-liaison/sponsors.
     */
    public function store(): void
    {
        // The login is in the contact person's name and signs in with the contact
        // email, so onboarding needs both — say so with every other problem at once.
        $validator = $this->sponsorInput()
            ->required('contact_person', 'Contact person')
            ->required('contact_email', 'Contact email')
            ->required('login_nic', 'NIC number')
            ->required('login_phone', 'Mobile number')
            ->required('login_address', 'Company address');

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $id = $this->accounts()->onboardWithLogin(
                $validator->values(),
                $this->postedPassword('password'),
                $this->postedPassword('password_confirmation')
            );
        } catch (ValidationException $exception) {
            $this->renderSponsorForm('sponsor-liaison/sponsors/onboarding', $exception->errors(), $validator->values());

            return;
        }

        $this->flash($validator->value('company_name') . ' onboarded as a sponsor.'
            . ' Their login is open: share the starting password with ' . $validator->value('contact_email') . '.');
        $this->redirect('/sponsor-liaison/sponsors/' . $id);
    }

    // ── Sponsors: update ────────────────────────────────────────────────────

    /**
     * GET /sponsor-liaison/sponsors/{id}/edit.
     */
    public function editForm(int $id): void
    {
        $row = $this->sponsorModel()->find($id);

        if ($row === null) {
            $this->sponsorNotFound();

            return;
        }

        $this->renderSponsorForm('sponsor-liaison/sponsors/edit', [], $this->sponsorAsInput($row), $row);
    }

    /**
     * POST /sponsor-liaison/sponsors/{id}.
     */
    public function update(int $id): void
    {
        $row = $this->sponsorModel()->find($id);

        if ($row === null) {
            $this->sponsorNotFound();

            return;
        }

        $validator = $this->sponsorInput();

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $this->sponsorService()->update($id, $validator->values());
        } catch (ValidationException $exception) {
            $this->renderSponsorForm('sponsor-liaison/sponsors/edit', $exception->errors(), $validator->values(), $row);

            return;
        } catch (RecordNotFoundException $exception) {
            $this->sponsorNotFound();

            return;
        }

        $this->flash('Sponsor details saved.');
        $this->redirect('/sponsor-liaison/sponsors/' . $id);
    }

    // ── Sponsors: deactivate (the soft delete) and reactivate ───────────────

    /**
     * POST /sponsor-liaison/sponsors/{id}/deactivate.
     */
    public function deactivate(int $id): void
    {
        $this->changeActive(
            $id,
            fn (): string => $this->sponsorService()->deactivate($id),
            '%s deactivated. Its contribution history stays on record.'
        );
    }

    /**
     * POST /sponsor-liaison/sponsors/{id}/reactivate.
     */
    public function reactivate(int $id): void
    {
        $this->changeActive(
            $id,
            fn (): string => $this->sponsorService()->reactivate($id),
            '%s is an active sponsor again.'
        );
    }

    /**
     * Deactivate and reactivate differ only in which service method runs; the
     * outcome handling — flash, redirect, refusal page — is identical.
     *
     * @param callable(): string $change returns the sponsor's company name
     */
    private function changeActive(int $id, callable $change, string $message): void
    {
        try {
            $this->flash(sprintf($message, $change()));
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->sponsorNotFound();

            return;
        }

        $this->redirect('/sponsor-liaison/sponsors/' . $id);
    }

    // ── Sponsors: helpers ───────────────────────────────────────────────────

    private function sponsorInput(): Validator
    {
        return (new Validator($_POST))
            ->required('company_name', 'Company name')
            ->maxLength('company_name', 'Company name', 150)
            ->words('company_name', 'Company name')
            ->maxLength('contact_person', 'Contact person', 100)
            ->personName('contact_person', 'Contact person')
            ->maxLength('contact_phone', 'Contact phone', 20)
            ->maxLength('contact_email', 'Contact email', 150)
            ->required('agreement_status', 'Agreement status')
            ->inList('agreement_status', 'Agreement status', array_keys(SponsorService::AGREEMENT_STATUSES))
            ->maxLength('agreement_details', 'Agreement details', 255)
            ->words('agreement_details', 'Agreement details')
            ->maxLength('internal_notes', 'Internal notes', 500)
            ->words('internal_notes', 'Internal notes')
            ->maxLength('login_nic', 'NIC number', 20)
            ->maxLength('login_phone', 'Mobile number', 20)
            ->maxLength('login_address', 'Company address', 255)
            ->words('login_address', 'Company address');
    }

    /**
     * @param array<string, string>     $errors
     * @param array<string, string>     $input
     * @param array<string, mixed>|null $row the sponsor being edited
     */
    private function renderSponsorForm(string $view, array $errors, array $input, ?array $row = null): void
    {
        $fields = ['company_name', 'contact_person', 'contact_phone', 'contact_email',
            'agreement_status', 'agreement_details', 'internal_notes',
            'login_nic', 'login_phone', 'login_address'];

        $this->render($view, [
            'draft'             => array_map(static fn (string $field): string => (string) ($input[$field] ?? ''), array_combine($fields, $fields)),
            'errors'            => $errors,
            'agreementStatuses' => SponsorService::AGREEMENT_STATUSES,
            'sponsor'           => $row === null ? null : [
                'id'     => (int) $row['id'],
                'name'   => (string) $row['company_name'],
                'active' => (int) $row['active'] === 1,
            ],
        ]);
    }

    /**
     * A stored sponsor in the form's field names.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function sponsorAsInput(array $row): array
    {
        return [
            'company_name'      => (string) $row['company_name'],
            'contact_person'    => (string) ($row['contact_name'] ?? ''),
            'contact_phone'     => (string) ($row['contact_phone'] ?? ''),
            'contact_email'     => (string) ($row['contact_email'] ?? ''),
            'agreement_status'  => (string) $row['agreement_status'],
            'agreement_details' => (string) ($row['agreement_details'] ?? ''),
            'internal_notes'    => (string) ($row['internal_notes'] ?? ''),
        ];
    }

    /**
     * What the list and the profile page both show about a sponsor.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function sponsorRow(array $row): array
    {
        $active = (int) $row['active'] === 1;
        $email  = (string) ($row['contact_email'] ?? '');
        $person = (string) ($row['contact_name'] ?? '');

        return [
            'id'              => (int) $row['id'],
            'name'            => (string) $row['company_name'],
            'email'           => $email,
            'contact'         => $email !== '' ? $email : ($person !== '' ? $person : 'No contact on file'),
            'points'        => number_format((int) $row['total_contributed']) . ' pts',
            'agreement'       => (string) $row['agreement_status'],
            'agreement_label' => SponsorService::AGREEMENT_STATUSES[(string) $row['agreement_status']] ?? '',
            'active'          => $active,
            'badge'           => $active ? 'success' : 'neutral',
            'badge_label'     => $active ? 'Active' : 'Inactive',
        ];
    }

    /**
     * Who signs in for this sponsor, as the profile page shows it.
     *
     * @param array<string, mixed> $row
     */
    private function loginLabel(array $row): string
    {
        if (($row['account_status'] ?? null) === null) {
            return 'No login — the company cannot sign in';
        }

        $state = match ((string) $row['account_status']) {
            'active'    => 'active',
            'suspended' => 'suspended',
            default     => 'closed',
        };

        return (string) $row['login_email'] . ' · ' . $state;
    }

    private function sponsorNotFound(): void
    {
        $this->notice(404, 'Sponsor not found', 'Return to the sponsor list to choose another.');
    }

    private function sponsorModel(): Sponsor
    {
        return new Sponsor($this->pdo);
    }

    private function sponsorService(): SponsorService
    {
        return new SponsorService($this->sponsorModel());
    }

    private function accounts(): SponsorAccountService
    {
        return new SponsorAccountService($this->pdo, new User($this->pdo), $this->sponsorModel());
    }

    // ── Contributions and aid grants (design preview) ───────────────────────

    public function purchases(): void
    {
        $search = $this->queryValue('q');
        $sponsor = $this->queryValue('sponsor');
        $dateRange = $this->queryValue('date_range');
        $purchases = array_values(array_filter($this->purchaseRows(), static fn (array $row): bool =>
            str_contains(strtolower($row['receipt']), strtolower($search))
            && ($sponsor === '' || $row['sponsor'] === $sponsor)
            && ($dateRange === '' || str_contains(strtolower($row['date']), strtolower($dateRange)))));
         $sponsorOptions = array_column((new Sponsor($this->pdo))->summaries(), 'company_name');
        $this->render('sponsor-liaison/purchases/index', compact('purchases', 'search', 'sponsor', 'dateRange', 'sponsorOptions'));
    }

    public function purchase(int $id): void
    {
        $this->record('purchases/show', $this->purchaseRows(), $id);
    }

    public function grants(): void
    {
        $status = $this->queryValue('status');
        $labels = ['' => 'All', 'awaiting_vouch' => 'Awaiting vouch', 'awaiting_approval' => 'Awaiting approval',
            'approved' => 'Approved', 'declined' => 'Declined', 'disbursed' => 'Disbursed',
            'partially_returned' => 'Partially returned', 'expired' => 'Expired', 'closed' => 'Closed'];
        $rows = $this->grantRows();
        $filters = [];
        foreach ($labels as $slug => $label) {
            $count = count(array_filter($rows, static fn (array $row): bool => $slug === '' || $row['status_label'] === $label));
            $filters[] = ['slug' => $slug, 'label' => $label . ' (' . $count . ')'];
        }
        $grants = array_values(array_filter($rows, static fn (array $row): bool =>
            $status === '' || $row['status_label'] === ($labels[$status] ?? '')));
        $this->render('sponsor-liaison/aid-grants/index', compact('grants', 'status', 'filters'));
    }

    public function grant(int $id): void
    {
        $row = $this->find($this->grantRows(), $id);
        if ($row === null) {
            $this->notice(404, 'Record not found', 'Return to the list to select a sponsor, contribution or aid grant.');
            return;
        }
        $record = $row['record'];
        $grant = $row + ['grant_number' => '#A-' . $id];
        $request = ['purpose' => $record['purpose'], 'amount_requested' => $record['requested_amount'] . ' pts',
            'pool_balance' => number_format((new PointPool($this->pdo))->balance('aid')) . ' pts',
            'prior_grants' => (string) max(0, count((new AidGrant($this->pdo))->records((int) $record['member_id'])) - 1),
            'note' => $record['purpose']];
        $vouch = ['initials' => User::initials((string) $record['moderator_name']),
            'name' => empty($record['vouched_at']) ? 'Awaiting moderator vouch' : 'Vouched by ' . $record['moderator_name']
                . ' · ' . date('j M Y', strtotime($record['vouched_at'])),
            'note' => (string) ($record['moderator_vouch'] ?? '')];
        $draft = ['approved_amount' => (string) ($record['approved_amount'] ?? $record['requested_amount']), 'reason' => ''];
        $this->render('sponsor-liaison/aid-grants/show', compact('grant', 'request', 'vouch', 'draft'));
    }

    public function exportGrants(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="approved-grants.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Grant ID', 'Member', 'Details', 'Status'], ',', '"', '');
        foreach ($this->grantRows() as $grant) {
            if ($grant['status_label'] === 'Approved') {
                fputcsv($output, [$grant['id'], $grant['name'], $grant['meta'], $grant['status_label']], ',', '"', '');
            }
        }
        fclose($output);
    }

    private function record(string $view, array $rows, int $id): void
    {
        $record = $this->find($rows, $id);
        if ($record === null) {
            $this->notice(404, 'Record not found', 'Return to the list to select a sponsor, contribution or aid grant.');
            return;
        }
        $this->render('sponsor-liaison/' . $view, compact('record'));
    }

    /**
     * Contribution rows with their current sponsor and recorder names.
     *
     * @return list<array<string, mixed>>
     */
    private function purchaseRows(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'date' => date('j M Y', strtotime($row['recorded_at'])),
            'sponsor' => $row['company_name'], 'receipt' => $row['receipt_number'],
            'allocation' => 'General ' . number_format((int) $row['general_points']) . ' pts · Aid '
                . number_format((int) $row['aid_points']) . ' pts · Recorded by ' . $row['recorded_by_name'],
            'amount' => 'LKR ' . number_format((int) $row['cash_amount']),
        ], (new SponsorContribution($this->pdo))->records());
    }

    private function grantRows(): array
    {
        return array_map(static function (array $row): array {
            $label = match ($row['status']) {
                'requested', 'info_requested' => 'Awaiting vouch',
                'vouched' => 'Awaiting approval',
                'rejected_moderator', 'rejected_liaison' => 'Declined',
                default => ucfirst(str_replace('_', ' ', $row['status'])),
            };
            return ['id' => (int) $row['id'], 'name' => $row['member_name'],
                'initials' => User::initials($row['member_name']), 'status' => 'info', 'status_label' => $label,
                'meta' => $row['requested_amount'] . ' pts · ' . $row['purpose'] . ' · ' . $row['division_name']
                    . ' · ' . date('j M Y', strtotime($row['created_at'])),
                'action' => $row['status'] === 'vouched' ? 'review' : 'view', 'record' => $row];
        }, (new AidGrant($this->pdo))->records());
    }

    private function find(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ($row['id'] === $id) return $row;
        }
        return null;
    }

    private function queryValue(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
    }
}
