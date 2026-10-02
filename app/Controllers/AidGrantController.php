<?php

declare(strict_types=1);

/**
 * Aid grants, the member's side (Plan 4.4) — the status page, and asking,
 * changing, answering and withdrawing. The rules live in AidGrantService. A
 * moderator may also open a request from their division by its id, read-only.
 */
final class AidGrantController extends Controller
{
    private AidGrantService $service;
    private AidGrant $grants;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->grants  = new AidGrant($pdo);
        $this->service = new AidGrantService($pdo, $this->grants, new User($pdo), new GnDivision($pdo), $this->uploads(), new Notification($pdo));
    }

    /**
     * GET /aid-grants (my live grant) and /aid-grants/{id}.
     */
    public function show(int $id = 0): void
    {
        $me      = $this->userId();
        $records = $this->grants->records($me);

        if ($id > 0 && $this->role() === 'moderator') {
            $division = (new GnDivision($this->pdo))->moderatedBy($me);
            $records  = $division === null ? $records : array_merge($records, $this->grants->records(null, $division));
        }

        $row = null;
        foreach ($records as $record) {
            if (($id > 0 && (int) $record['id'] === $id)
                || ($id === 0 && in_array($record['status'], AidGrant::LIVE_STATES, true))) {
                $row = $record;
                break;
            }
        }

        if ($id > 0 && $row === null) {
            $this->notice(404, 'Record not found', 'This record is not available to your account.');

            return;
        }

        $own = $row === null ? null : $this->grants->findOwned((int) $row['id'], $me);

        $this->render('aid-grants/show', [
            'grant'   => $row === null ? null : $this->grantView($row, $own),
            'vouch'   => $row === null ? null : [
                'initials' => User::initials((string) ($row['moderator_name'] ?? '')),
                'line'     => empty($row['vouched_at']) ? 'Awaiting moderator vouch' : 'Vouched by ' . $row['moderator_name'] . ' · ' . date('j M Y', strtotime((string) $row['vouched_at'])),
                'quote'    => (string) ($row['moderator_vouch'] ?? ''),
                'badge'    => empty($row['vouched_at']) ? 'Pending' : 'Vouch recorded',
            ],
            'cooling'   => $row === null ? $this->service->eligibility($me) : '',
            'canAsk'    => $row === null && $this->service->eligibility($me) === '',
        ]);
    }

    /**
     * GET /aid-grants/create.
     */
    public function createForm(): void
    {
        $this->renderForm(null, ['purpose' => '', 'amount' => '', 'details' => ''], []);
    }

    /**
     * GET /aid-grants/{id}/edit — change a request before the vouch.
     */
    public function editForm(int $id): void
    {
        try {
            $grant = $this->service->ownOrFail($id, $this->userId());
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Record not found', 'This record is not available to your account.');

            return;
        }

        $this->renderForm($grant, [
            'purpose' => (string) $grant['purpose'],
            'amount'  => (string) $grant['requested_amount'],
            'details' => (string) ($grant['details'] ?? ''),
        ], []);
    }

    /**
     * POST /aid-grants.
     */
    public function store(): void
    {
        $input = $this->input();

        try {
            if (!$input->passes()) {
                throw new ValidationException($input->errors());
            }

            $id = $this->service->request(
                $this->userId(),
                (int) $input->value('amount'),
                $input->value('purpose'),
                $input->value('details'),
                uploaded_files('evidence')
            );
        } catch (ValidationException $exception) {
            $this->renderForm(null, $input->values(), $exception->errors());

            return;
        }

        $this->flash('Request sent. Your moderator will look at it and vouch for it.');
        $this->redirect('/aid-grants/' . $id);
    }

    /**
     * POST /aid-grants/{id}.
     */
    public function update(int $id): void
    {
        $input = $this->input();

        try {
            if (!$input->passes()) {
                throw new ValidationException($input->errors());
            }

            $this->service->update($id, $this->userId(), (int) $input->value('amount'), $input->value('purpose'), $input->value('details'));
        } catch (ValidationException $exception) {
            $grant = $this->grants->findOwned($id, $this->userId());
            $this->renderForm($grant, $input->values(), $exception->errors());

            return;
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Record not found', 'This record is not available to your account.');

            return;
        }

        $this->flash('Request updated.');
        $this->redirect('/aid-grants/' . $id);
    }

    /**
     * POST /aid-grants/{id}/reply — answer the Liaison's question.
     */
    public function reply(int $id): void
    {
        $this->act($id, fn (): mixed => $this->service->reply($id, $this->userId(), (string) ($_POST['reply'] ?? '')), 'Answer sent.');
    }

    /**
     * POST /aid-grants/{id}/withdraw.
     */
    public function withdraw(int $id): void
    {
        $this->act($id, fn (): mixed => $this->service->withdraw($id, $this->userId()), 'Request withdrawn.');
    }

    private function act(int $id, callable $action, string $message): void
    {
        try {
            $action();
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Record not found', 'This record is not available to your account.');

            return;
        }

        $this->redirect('/aid-grants/' . $id);
    }

    private function input(): Validator
    {
        $input = new Validator($_POST);

        return $input
            ->required('purpose', 'Purpose')->inList('purpose', 'Purpose', AidGrantService::PURPOSES)
            ->required('amount', 'Amount')->integer('amount', 'Amount', 1, AidGrantService::YEARLY_CAP)
            ->required('details', 'Details')->maxLength('details', 'Details', AidGrantService::DETAILS_MAX);
    }

    /**
     * @param array<string, mixed>|null $grant the request being edited, or null for a new one
     * @param array<string, string>     $draft
     * @param array<string, string>     $errors
     */
    private function renderForm(?array $grant, array $draft, array $errors): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $me = $this->userId();

        $this->render('aid-grants/create', [
            'purposes'  => AidGrantService::PURPOSES,
            'draft'     => $draft + ['purpose' => '', 'amount' => '', 'details' => ''],
            'errors'    => $errors,
            'editing'   => $grant,
            'blocked'   => $grant === null ? $this->service->eligibility($me) : '',
            'remaining' => $this->service->remainingThisYear($me) + ($grant === null ? 0 : (int) $grant['requested_amount']),
        ]);
    }

    /**
     * @param array<string, mixed>      $row a records() row
     * @param array<string, mixed>|null $own the same grant when it is the viewer's own
     *
     * @return array<string, mixed>
     */
    private function grantView(array $row, ?array $own): array
    {
        $status = (string) $row['status'];
        $paths  = $own === null ? [] : HandoverService::decode($own['evidence_photos'] ?? null);

        return [
            'id'        => (int) $row['id'],
            'reference' => 'Aid grant #A-' . $row['id'],
            'stage'     => AidGrant::stage($status),
            'badge'     => match ($status) {
                'rejected_moderator', 'rejected_liaison' => ['error', '✕', 'Not approved'],
                'approved', 'disbursed'                  => ['success', '✓', ucfirst($status)],
                'info_requested'                         => ['warning', '!', 'More information needed'],
                default                                  => ['info', 'i', ucfirst(str_replace('_', ' ', $status))],
            },
            'facts' => [
                ['label' => 'Member', 'value' => $row['member_name']],
                ['label' => 'Purpose', 'value' => $row['purpose']],
                ['label' => 'Amount requested', 'value' => number_format((int) $row['requested_amount']) . ' pts'],
                ['label' => 'Amount approved', 'value' => $row['approved_amount'] === null ? 'Not approved' : $row['approved_amount'] . ' pts'],
                ['label' => 'Requested', 'value' => date('j M Y', strtotime((string) $row['created_at']))],
                ['label' => 'Division', 'value' => $row['division_name']],
            ],
            'notice'    => 'Status: ' . ucfirst(str_replace('_', ' ', $status)),
            'details'   => (string) ($own['details'] ?? ''),
            'photos'    => array_map(static fn (string $path, int $index): array => ['url' => photo_url($path), 'label' => 'Evidence ' . ($index + 1)], $paths, array_keys($paths)),
            'question'  => (string) ($own['info_request'] ?? ''),
            'answer'    => (string) ($own['member_reply'] ?? ''),
            'reason'    => (string) ($own['decision_reason'] ?? ''),
            'can_edit'     => $own !== null && $status === 'requested',
            'can_reply'    => $own !== null && $status === 'info_requested',
            'can_withdraw' => $own !== null && in_array($status, ['requested', 'info_requested'], true),
        ];
    }
}
