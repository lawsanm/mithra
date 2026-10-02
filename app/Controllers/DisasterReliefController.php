<?php

declare(strict_types=1);

/**
 * The moderator's Disaster relief screen (Plan §14.1 step 5): list, record,
 * edit and delete the relief handed out while their division is in Disaster
 * Mode.
 *
 * HTTP plumbing only; the rules live in DisasterReliefService. RbacMiddleware
 * has already limited /moderator to moderators.
 */
final class DisasterReliefController extends Controller
{
    private const FIELDS = ['relief_type', 'description', 'location', 'households_reached',
        'estimated_value', 'distributed_on', 'sponsor_id', 'notes'];

    // ── Read ────────────────────────────────────────────────────────────────

    /**
     * GET /moderator/disasters — the active disaster and every relief record
     * in the division.
     */
    public function index(): void
    {
        $page = $this->page();

        try {
            $division = $this->service()->divisionFor($this->userId());
            $disaster = $this->service()->activeDisaster($this->userId());
        } catch (AccessDeniedException $exception) {
            $this->notice(403, 'No division here', 'This account does not moderate a division.');

            return;
        }

        $records = new DisasterReliefRecord($this->pdo);
        $totals  = $disaster === null ? null : $records->totalsForEvent((int) $disaster['id']);

        $this->render('moderator/disasters/index', [
            'requests' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'], 'initials' => User::initials($row['member_name']), 'name' => $row['member_name'],
                'meta' => $row['requested_amount'] . ' pts · ' . $row['purpose'] . ' · ' . date('j M Y', strtotime($row['created_at'])),
            ], array_values(array_filter((new AidGrant($this->pdo))->records(null, $division),
                static fn (array $row): bool => in_array($row['status'], ['requested', 'info_requested'], true)))),
            'disaster'    => $disaster === null ? null : [
                'division' => (string) $disaster['division_name'],
                'note'     => (string) $disaster['reason'] . '  ·  started ' . date('j M Y', strtotime((string) $disaster['started_at']))
                    . ($disaster['planned_end_at'] === null ? '' : '  ·  planned to end ' . date('j M Y', strtotime((string) $disaster['planned_end_at']))),
            ],
            'stats'       => $totals === null ? [] : [
                ['label' => 'Relief records',     'value' => (string) $totals['records']],
                ['label' => 'Households reached', 'value' => number_format($totals['households'])],
                ['label' => 'Estimated value',    'value' => 'LKR ' . number_format($totals['value']), 'note' => 'Record-keeping only; no points move'],
            ],
            'records'     => array_map(fn (array $row): array => $this->recordRow($row), $records->forDivision($division, $page)),
            'reports'     => array_map(fn (array $row): array => [
                'id'     => (int) $row['id'],
                'title'  => (string) $row['reason'],
                'period' => $this->period($row),
                'active' => $row['ended_at'] === null,
            ], $this->service()->disasters($this->userId())),
            'page'        => $page,
            'hasNextPage' => $page * DisasterReliefRecord::PER_PAGE < $records->countForDivision($division),
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────────────

    /**
     * GET /moderator/disasters/relief/create.
     */
    public function createForm(): void
    {
        if (!$this->disasterActive()) {
            return;
        }

        $this->renderForm('moderator/disasters/create', [], ['distributed_on' => date('Y-m-d')]);
    }

    /**
     * POST /moderator/disasters/relief.
     */
    public function store(): void
    {
        $validator = $this->reliefInput();

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $this->service()->create($this->userId(), $validator->values(), date('Y-m-d'));
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['disaster'])) {
                $this->flash($exception->errors()['disaster'], 'error');
                $this->redirect('/moderator/disasters');

                return;
            }

            http_response_code(422);
            $this->renderForm('moderator/disasters/create', $exception->errors(), $validator->values());

            return;
        } catch (AccessDeniedException $exception) {
            $this->notice(403, 'No division here', 'This account does not moderate a division.');

            return;
        }

        $this->flash('Relief recorded.');
        $this->redirect('/moderator/disasters');
    }

    // ── Update ──────────────────────────────────────────────────────────────

    /**
     * GET /moderator/disasters/relief/{id}/edit.
     */
    public function editForm(int $id): void
    {
        $row = $this->editableOrRefuse($id);

        if ($row !== null) {
            $this->renderForm('moderator/disasters/edit', [], $this->recordAsInput($row), $id);
        }
    }

    /**
     * POST /moderator/disasters/relief/{id}.
     */
    public function update(int $id): void
    {
        if ($this->editableOrRefuse($id) === null) {
            return;
        }

        $validator = $this->reliefInput();

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $this->service()->update($this->userId(), $id, $validator->values(), date('Y-m-d'));
        } catch (ValidationException $exception) {
            http_response_code(422);
            $this->renderForm('moderator/disasters/edit', $exception->errors(), $validator->values(), $id);

            return;
        } catch (AccessDeniedException | RecordNotFoundException $exception) {
            $this->recordNotFound();

            return;
        }

        $this->flash('Relief record saved.');
        $this->redirect('/moderator/disasters');
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    /**
     * POST /moderator/disasters/relief/{id}/delete.
     */
    public function destroy(int $id): void
    {
        try {
            $this->flash('Deleted the record for ' . $this->service()->delete($this->userId(), $id) . '.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (AccessDeniedException | RecordNotFoundException $exception) {
            $this->recordNotFound();

            return;
        }

        $this->redirect('/moderator/disasters');
    }

    // ── Report ──────────────────────────────────────────────────────────────

    /**
     * GET /moderator/disasters/{id}/report — the printable relief report.
     */
    public function report(int $id): void
    {
        $report = $this->reportOrRefuse($id);

        if ($report === null) {
            return;
        }

        $disaster = $report['disaster'];
        $summary  = $report['summary'];

        $this->render('moderator/disasters/report', [
            'report'  => [
                'id'          => $id,
                'division'    => (string) $disaster['division_name'],
                'reason'      => (string) $disaster['reason'],
                'period'      => $this->period($disaster),
                'active'      => $disaster['ended_at'] === null,
                'prepared_by' => (string) ((new User($this->pdo))->find($this->userId())['full_name'] ?? ''),
                'prepared_at' => date('j M Y, g:i a'),
            ],
            'stats'   => [
                ['label' => 'Relief records',     'value' => (string) $summary['records']],
                ['label' => 'Households reached', 'value' => number_format($summary['households'])],
                ['label' => 'Estimated value',    'value' => 'LKR ' . number_format($summary['value'])],
                ['label' => 'Sponsors involved',  'value' => (string) $summary['sponsors']],
            ],
            'byType'    => $this->groupRows($summary['by_type']),
            'bySponsor' => $this->groupRows($summary['by_sponsor']),
            'records'   => array_map(static fn (array $row): array => [
                'date'       => date('j M Y', strtotime((string) $row['distributed_on'])),
                'type'       => DisasterReliefService::RELIEF_TYPES[(string) $row['relief_type']] ?? '',
                'what'       => (string) $row['description'],
                'location'   => (string) $row['location'],
                'households' => number_format((int) $row['households_reached']),
                'sponsor'    => (string) ($row['sponsor_name'] ?? '—'),
                'value'      => $row['estimated_value'] === null ? '—' : 'LKR ' . number_format((int) $row['estimated_value']),
            ], $report['records']),
        ]);
    }

    /**
     * GET /moderator/disasters/{id}/report/csv — every record as a spreadsheet.
     */
    public function reportCsv(int $id): void
    {
        $report = $this->reportOrRefuse($id);

        if ($report === null) {
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relief-report-' . $id . '-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Date handed out', 'Relief type', 'What was given', 'Location', 'Households reached',
            'Sponsor', 'Estimated value (LKR)', 'Notes', 'Recorded by'], ',', '"', '');

        foreach ($report['records'] as $row) {
            fputcsv($output, [
                (string) $row['distributed_on'],
                DisasterReliefService::RELIEF_TYPES[(string) $row['relief_type']] ?? '',
                self::csvSafe((string) $row['description']),
                self::csvSafe((string) $row['location']),
                (int) $row['households_reached'],
                self::csvSafe((string) ($row['sponsor_name'] ?? '')),
                $row['estimated_value'] === null ? '' : (int) $row['estimated_value'],
                self::csvSafe((string) ($row['notes'] ?? '')),
                self::csvSafe((string) $row['moderator_name']),
            ], ',', '"', '');
        }

        fclose($output);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * The report when this disaster is in the moderator's division; otherwise
     * the refusal has already been sent and null comes back.
     *
     * @return array<string, mixed>|null
     */
    private function reportOrRefuse(int $id): ?array
    {
        try {
            return $this->service()->report($this->userId(), $id);
        } catch (AccessDeniedException | RecordNotFoundException $exception) {
            $this->notice(404, 'Disaster not found', 'This disaster is not in your division, or it no longer exists.');
        }

        return null;
    }

    /**
     * A disaster's dates as one line: when it began and when it ended, or is
     * planned to.
     *
     * @param array<string, mixed> $disaster
     */
    private function period(array $disaster): string
    {
        $day = static fn (mixed $value): string => date('j M Y', strtotime((string) $value));

        return $day($disaster['started_at']) . ' – ' . match (true) {
            $disaster['ended_at'] !== null       => $day($disaster['ended_at']),
            $disaster['planned_end_at'] !== null => 'ongoing (planned end ' . $day($disaster['planned_end_at']) . ')',
            default                              => 'ongoing',
        };
    }

    /**
     * A report breakdown as the tables show it.
     *
     * @param list<array{label: string, records: int, households: int, value: int}> $groups
     *
     * @return list<array<string, string>>
     */
    private function groupRows(array $groups): array
    {
        return array_map(static fn (array $group): array => [
            'label'      => $group['label'],
            'records'    => (string) $group['records'],
            'households' => number_format($group['households']),
            'value'      => 'LKR ' . number_format($group['value']),
        ], $groups);
    }

    /**
     * A spreadsheet treats a cell starting with = + - or @ as a formula; a
     * leading apostrophe keeps a typed value as plain text.
     */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    private function reliefInput(): Validator
    {
        return (new Validator($_POST))
            ->required('relief_type', 'Relief type')
            ->inList('relief_type', 'Relief type', array_keys(DisasterReliefService::RELIEF_TYPES))
            ->required('description', 'What was given')
            ->maxLength('description', 'What was given', 255)
            ->required('location', 'Location')
            ->maxLength('location', 'Location', 150)
            ->words('location', 'Location')
            ->required('households_reached', 'Households reached')
            ->integer('households_reached', 'Households reached', 1, 65535)
            ->integer('estimated_value', 'Estimated value', 0, 100000000)
            ->required('distributed_on', 'Date handed out')
            ->integer('sponsor_id', 'Sponsor', 1)
            ->maxLength('notes', 'Notes', 500)
            ->words('notes', 'Notes');
    }

    /**
     * Only offer the form while there is a disaster to record against.
     */
    private function disasterActive(): bool
    {
        try {
            $disaster = $this->service()->activeDisaster($this->userId());
        } catch (AccessDeniedException $exception) {
            $this->notice(403, 'No division here', 'This account does not moderate a division.');

            return false;
        }

        if ($disaster === null) {
            $this->flash('Relief can only be recorded while Disaster Mode is active in your division.', 'error');
            $this->redirect('/moderator/disasters');

            return false;
        }

        return true;
    }

    /**
     * The record when this moderator may still change it; otherwise the
     * refusal has already been sent and null comes back.
     *
     * @return array<string, mixed>|null
     */
    private function editableOrRefuse(int $id): ?array
    {
        try {
            return $this->service()->editable($this->userId(), $id);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/moderator/disasters');
        } catch (AccessDeniedException | RecordNotFoundException $exception) {
            $this->recordNotFound();
        }

        return null;
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $input
     */
    private function renderForm(string $view, array $errors, array $input, ?int $id = null): void
    {
        $this->render($view, [
            'recordId'    => $id,
            'draft'       => array_map(static fn (string $field): string => (string) ($input[$field] ?? ''), array_combine(self::FIELDS, self::FIELDS)),
            'errors'      => $errors,
            'reliefTypes' => DisasterReliefService::RELIEF_TYPES,
            'sponsors'    => (new Sponsor($this->pdo))->activeNames(),
        ]);
    }

    /**
     * A stored record in the form's field names.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function recordAsInput(array $row): array
    {
        return [
            'relief_type'        => (string) $row['relief_type'],
            'description'        => (string) $row['description'],
            'location'           => (string) $row['location'],
            'households_reached' => (string) $row['households_reached'],
            'estimated_value'    => (string) ($row['estimated_value'] ?? ''),
            'distributed_on'     => (string) $row['distributed_on'],
            'sponsor_id'         => (string) ($row['sponsor_id'] ?? ''),
            'notes'              => (string) ($row['notes'] ?? ''),
        ];
    }

    /**
     * What the list shows about one record.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function recordRow(array $row): array
    {
        $meta = [
            DisasterReliefService::RELIEF_TYPES[(string) $row['relief_type']] ?? '',
            (string) $row['location'],
            $row['households_reached'] . ' households',
            date('j M Y', strtotime((string) $row['distributed_on'])),
        ];

        if ($row['sponsor_name'] !== null) {
            $meta[] = 'from ' . $row['sponsor_name'];
        }

        return [
            'id'       => (int) $row['id'],
            'title'    => (string) $row['description'],
            'meta'     => implode('  ·  ', $meta),
            'value'    => $row['estimated_value'] === null ? '' : 'LKR ' . number_format((int) $row['estimated_value']),
            'editable' => (int) $row['disaster_active'] === 1,
        ];
    }

    private function recordNotFound(): void
    {
        $this->notice(404, 'Relief record not found', 'This record is not in your division, or it no longer exists.');
    }

    private function service(): DisasterReliefService
    {
        return new DisasterReliefService(
            new GnDivision($this->pdo),
            new DisasterEvent($this->pdo),
            new DisasterReliefRecord($this->pdo),
            new Sponsor($this->pdo)
        );
    }
}
