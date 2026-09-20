<?php

declare(strict_types=1);

/**
 * The Sponsor Liaison's screens.
 *
 * Sponsors are the working CRUD (Plan §20.4 module 4.2): list, view, onboard,
 * edit, deactivate and reactivate, read from and written to the database.
 *
 * Contributions and aid grants are still a design preview over fixed sample
 * records, so their lists, filters and detail pages can be clicked through.
 * Nothing there is read or written until modules 4.3 and 4.4 are built.
 */
final class SponsorLiaisonController extends Controller
{
    private const PURCHASES = [
    ['id' => 1, 'date' => '15 Jul', 'sponsor' => 'Northwind Co', 'receipt' => 'INV-0312', 'allocation' => 'allocation 70% Sponsor · 30% Aid', 'amount' => 'LKR 10,000'],
    ['id' => 2, 'date' => '05 Jul', 'sponsor' => 'ACM Corp',     'receipt' => 'INV-0306', 'allocation' => 'allocation 50% Sponsor · 50% Aid', 'amount' => 'LKR 7,500'],
    ['id' => 3, 'date' => '28 Jun', 'sponsor' => 'MNM',          'receipt' => 'INV-0298', 'allocation' => 'allocation 100% Aid',              'amount' => 'LKR 6,500'],
    ['id' => 4, 'date' => '19 Jun', 'sponsor' => 'Global Ltd',   'receipt' => 'INV-0265', 'allocation' => 'allocation 70% Sponsor · 30% Aid', 'amount' => 'LKR 5,000'],
    ['id' => 5, 'date' => '10 Jun', 'sponsor' => 'Texa',         'receipt' => 'INV-0276', 'allocation' => 'allocation 60% Sponsor · 40% Aid', 'amount' => 'LKR 5,000'],
];

    private const GRANTS = [
    ['id' => 1, 'initials' => 'ML', 'name' => 'M. Lawsan',       'meta' => '300 pts · school supplies · vouched by Mod. J. Kavipriya · 15 Jul', 'status' => 'info',    'status_label' => 'Awaiting approval', 'action' => 'review'],
    ['id' => 2, 'initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => '200 pts · medical costs · vouched by Mod. J. Kavipriya · 14 Jul',    'status' => 'info',    'status_label' => 'Awaiting approval', 'action' => 'review'],
    ['id' => 3, 'initials' => 'JK', 'name' => 'J. Kavipriya',    'meta' => '450 pts · roof repair · awaiting moderator vouch · 16 Jul',         'status' => 'warning', 'status_label' => 'Awaiting vouch',    'action' => 'view'],
    ['id' => 4, 'initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => '500 pts · flood recovery · approved 01 Jul · funded by Northwind Co', 'status' => 'success', 'status_label' => 'Approved',          'action' => 'view'],
    ['id' => 5, 'initials' => 'AA', 'name' => 'J. Kavipriya',    'meta' => '400 pts · declined 28 Jun · insufficient evidence, may re-apply',   'status' => 'error',   'status_label' => 'Declined',          'action' => 'view'],
];

    private const SORTS = ['name', 'recently_added'];

    // ── Sponsors: read ──────────────────────────────────────────────────────

    /**
     * GET /sponsor-liaison/sponsors — the sponsor list.
     */
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
        $validator = $this->sponsorInput();

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $id = $this->sponsorService()->create($validator->values());
        } catch (ValidationException $exception) {
            $this->renderSponsorForm('sponsor-liaison/sponsors/onboarding', $exception->errors(), $validator->values());

            return;
        }

        $this->flash($validator->value('company_name') . ' onboarded as a sponsor.');
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
            ->integer('user_id', 'Sponsor account', 1)
            ->maxLength('contact_person', 'Contact person', 100)
            ->maxLength('contact_phone', 'Contact phone', 20)
            ->maxLength('contact_email', 'Contact email', 150)
            ->required('agreement_status', 'Agreement status')
            ->inList('agreement_status', 'Agreement status', array_keys(SponsorService::AGREEMENT_STATUSES))
            ->maxLength('agreement_details', 'Agreement details', 255)
            ->maxLength('internal_notes', 'Internal notes', 500);
    }

    /**
     * @param array<string, string>     $errors
     * @param array<string, string>     $input
     * @param array<string, mixed>|null $row the sponsor being edited
     */
    private function renderSponsorForm(string $view, array $errors, array $input, ?array $row = null): void
    {
        $fields = ['user_id', 'company_name', 'contact_person', 'contact_phone', 'contact_email',
            'agreement_status', 'agreement_details', 'internal_notes'];

        $this->render($view, [
            'draft'             => array_map(static fn (string $field): string => (string) ($input[$field] ?? ''), array_combine($fields, $fields)),
            'errors'            => $errors,
            'agreementStatuses' => SponsorService::AGREEMENT_STATUSES,
            'accounts'          => $this->sponsorModel()->availableAccounts((int) ($row['user_id'] ?? 0)),
            'sponsor'           => $row === null ? null : [
                'id'     => (int) $row['id'],
                'name'   => (string) $row['company_name'],
                'active' => (int) $row['active'] === 1,
                'linked' => $row['user_id'] !== null,
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
            'user_id'           => (string) ($row['user_id'] ?? ''),
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

    // ── Contributions and aid grants (design preview) ───────────────────────

    public function purchases(): void
    {
        $search = $this->queryValue('q');
        $sponsor = $this->queryValue('sponsor');
        $dateRange = $this->queryValue('date_range');
        $purchases = array_values(array_filter(self::PURCHASES, static fn (array $row): bool =>
            str_contains(strtolower($row['receipt']), strtolower($search))
            && ($sponsor === '' || $row['sponsor'] === $sponsor)
            && ($dateRange === '' || str_contains(strtolower($row['date']), strtolower($dateRange)))));
        $this->render('sponsor-liaison/purchases/index', compact('purchases', 'search', 'sponsor', 'dateRange'));
    }

    public function purchase(int $id): void
    {
        $this->record('purchases/show', self::PURCHASES, $id);
    }

    public function grants(): void
    {
        $status = $this->queryValue('status');
        $labels = ['awaiting_vouch' => 'Awaiting vouch', 'awaiting_approval' => 'Awaiting approval',
            'approved' => 'Approved', 'declined' => 'Declined'];
        $grants = array_values(array_filter(self::GRANTS, static fn (array $row): bool =>
            $status === '' || $row['status_label'] === ($labels[$status] ?? '')));
        $this->render('sponsor-liaison/aid-grants/index', compact('grants', 'status'));
    }

    public function grant(int $id): void
    {
        $row = $this->find(self::GRANTS, $id);
        if ($row === null) {
            $this->notice(404, 'Record not found', 'Return to the list to select a sponsor, contribution or aid grant.');
            return;
        }
        $parts = explode(' · ', $row['meta']);
        $grant = $row + ['grant_number' => '#A-' . (1041 + $id)];
        $request = ['purpose' => ucfirst($parts[1] ?? 'Aid request'), 'amount_requested' => $parts[0],
            'pool_balance' => 'Sample balance', 'prior_grants' => 'Not shown in this preview',
            'note' => $row['meta']];
        $vouch = ['initials' => 'JK', 'name' => 'Moderator review', 'note' => $parts[2] ?? 'See request status'];
        $draft = ['approved_amount' => (string) (int) $parts[0], 'reason' => ''];
        $this->render('sponsor-liaison/aid-grants/show', compact('grant', 'request', 'vouch', 'draft'));
    }

    public function exportGrants(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="approved-grants-demo.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Demo grant ID', 'Member', 'Details', 'Status'], ',', '"', '');
        foreach (self::GRANTS as $grant) {
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
