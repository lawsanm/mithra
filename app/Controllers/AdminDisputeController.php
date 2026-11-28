<?php

declare(strict_types=1);

/** Admin queue and review screen for damage disputes (Proposal §10.2). */
final class AdminDisputeController extends Controller
{
    private AdminDisputeService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new AdminDisputeService(
            $pdo,
            new Dispute($pdo),
            new DamageClaim($pdo),
            new Booking($pdo),
            new ReturnRecord($pdo),
            new Item($pdo),
            new User($pdo),
            new AccountSuspension($pdo),
            new Notification($pdo),
            self::ledgerService($pdo),
            new Moderator($pdo),
            new ModeratorCandidate($pdo),
            new ModeratorConductHistory($pdo),
            new GnDivision($pdo),
            new Wallet($pdo),
            BookingController::returns($pdo),
            TrustController::service($pdo)
        );
    }

    public function index(): void
    {
        $page = $this->page();
        $rows = (new Dispute($this->pdo))->openPage($page);

        $this->render('admin/disputes/index', [
            'page'        => $page,
            'hasNextPage' => count($rows) === 20,
            'disputes' => array_map(fn (array $row): array => [
                'title'        => (string) ($row['item_title'] ?? 'Dispute #' . $row['id']),
                'case_number'  => 'DC-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT),
                'division'     => (string) ($row['division_name'] ?? ''),
                'parties'      => (string) ($row['lender_name'] ?? 'Unknown lender') . ' vs '
                    . (string) ($row['borrower_name'] ?? 'Unknown borrower'),
                'escalated_at' => date('d M Y', strtotime((string) $row['created_at'])),
                'reason'       => (int) $row['days_open'] . ' days open · ' . (string) $row['reason'],
                'status'       => (int) $row['days_open'] > 7 ? 'error' : 'warning',
                'status_label' => (int) $row['days_open'] > 7 ? 'Past timer' : 'Open',
                'href'         => base_url() . '/admin/disputes/' . $row['id'],
            ], $rows),
        ]);
    }

    public function show(int $id): void
    {
        $this->renderReview($id, [], []);
    }

    public function decide(int $id): void
    {
        $input = new Validator($_POST);
        $input
            ->required('award_pts', 'Award to lender')->integer('award_pts', 'Award to lender', 0, ItemService::MAX_DECLARED_VALUE)
            ->required('rationale', 'Ruling rationale')->maxLength('rationale', 'Ruling rationale', 1000)->words('rationale', 'Ruling rationale');

        if (!$input->passes()) {
            $this->renderReview($id, $input->errors(), $input->values());

            return;
        }

        try {
            $this->service->rule($id, $this->userId(), (int) $input->value('award_pts'), $input->value('rationale'));
        } catch (ValidationException $exception) {
            $this->renderReview($id, $exception->errors(), $input->values());

            return;
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->notice(404, 'Dispute not found', 'Return to the dispute queue and choose an available case.');

            return;
        }

        $this->flash('The final dispute ruling was recorded and the affected accounts were notified.');
        $this->redirect('/admin/disputes');
    }

    /** @param array<string, string> $errors @param array<string, string> $draft */
    private function renderReview(int $id, array $errors, array $draft): void
    {
        $row = (new Dispute($this->pdo))->reviewDetails($id);

        if ($row === null) {
            $this->notice(404, 'Dispute not found', 'Return to the dispute queue and choose an available case.');

            return;
        }

        if ($errors !== []) {
            http_response_code(422);
        }

        $data = $this->viewData($row, $id);
        $data['errors'] = $errors;
        $data['draft'] = $draft + ['award_pts' => (string) $data['proposed_pts'], 'rationale' => ''];
        $this->render('admin/disputes/show', $data);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function viewData(array $row, int $id): array
    {
        $dispute = [
            'id'                => $id,
            'title'             => (string) ($row['item_title'] ?? 'Dispute'),
            'case_number'       => 'DC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'status'            => $row['status'] === 'open' ? 'warning' : 'success',
            'status_label'      => ucfirst((string) $row['status']),
            'can_rule'          => $row['status'] === 'open'
                && ($row['claim_status'] === 'escalated'
                    || ($row['claim_status'] === 'closed' && $row['borrower_response'] === 'accepted')),
            'division'          => (string) ($row['division_name'] ?? 'Unknown division'),
            'lender'            => (string) ($row['lender_name'] ?? 'Unknown lender'),
            'borrower'          => (string) ($row['borrower_name'] ?? 'Unknown borrower'),
            'severity'          => ucfirst((string) ($row['severity'] ?? 'Not recorded')),
            'claim_status'      => ucfirst(str_replace('_', ' ', (string) ($row['claim_status'] ?? ''))),
            'claim_reason'      => (string) ($row['claim_description'] ?? ''),
            'moderator_name'    => (string) ($row['moderator_name'] ?? ''),
            'moderator_outcome' => (string) ($row['outcome_category'] ?? ''),
            'moderator_penalty' => $row['moderator_penalty'] === null ? null : (int) $row['moderator_penalty'],
            'moderator_notes'   => (string) ($row['moderator_notes'] ?? ''),
            'ruling_at'         => $row['ruling_at'] === null ? null : date('j M Y, H:i', strtotime((string) $row['ruling_at'])),
            'ruling_points'     => $row['ruling_points'] === null ? null : (int) $row['ruling_points'],
            'resolution'        => (string) ($row['resolution'] ?? ''),
        ];

        $history = [[
            'text' => 'Dispute raised: ' . (string) $row['reason'],
            'date' => date('j M Y, H:i', strtotime((string) $row['created_at'])),
        ]];

        if (!empty($row['met_at'])) {
            $history[] = [
                'text' => 'Moderator meeting recorded: ' . (string) ($row['moderator_notes'] ?? 'No notes recorded.'),
                'date' => date('j M Y, H:i', strtotime((string) $row['met_at'])),
            ];
        }

        if ($row['ruling_at'] !== null) {
            $history[] = [
                'text' => sprintf(
                    'Admin ruling recorded: %s pts to lender. %s',
                    number_format((int) ($row['ruling_points'] ?? 0)),
                    (string) ($row['resolution'] ?? '')
                ),
                'date' => date('j M Y, H:i', strtotime((string) $row['ruling_at'])),
            ];
        }

        return [
            'dispute' => $dispute,
            'history' => $history,
            'evidence' => [
                $this->evidenceGroup('Handover — lender', $row['handover_lender_photos'] ?? null, $row['handover_lender_notes'] ?? null),
                $this->evidenceGroup('Handover — borrower', $row['handover_borrower_photos'] ?? null, $row['handover_borrower_notes'] ?? null),
                $this->evidenceGroup('Return — lender', $row['return_lender_photos'] ?? null, $row['return_lender_notes'] ?? null),
                $this->evidenceGroup('Return — borrower', $row['return_borrower_photos'] ?? null, $row['return_borrower_notes'] ?? null),
                $this->evidenceGroup('Damage claim', $row['evidence_photos'] ?? null, $row['claim_description'] ?? null),
            ],
            'proposed_pts' => $row['moderator_penalty'] !== null
                ? (int) $row['moderator_penalty']
                : (int) ($row['proposed_penalty'] ?? 0),
        ];
    }

    /** @return array{label: string, notes: string, photos: list<array{url: string, alt: string}>} */
    private function evidenceGroup(string $label, mixed $encodedPhotos, mixed $notes): array
    {
        $paths = PhotoStore::paths($encodedPhotos);

        return [
            'label'  => $label,
            'notes'  => (string) ($notes ?? ''),
            'photos' => array_map(
                fn (string $path, int $index): array => [
                    'url' => photo_url($path),
                    'alt' => $label . ' photo ' . ($index + 1),
                ],
                $paths,
                array_keys($paths)
            ),
        ];
    }
}
