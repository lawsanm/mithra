<?php

declare(strict_types=1);

/**
 * Temporary community — apply, see the application, extend, promote to home,
 * leave (Plan 1.2, §6.5). The rules live in CommunityService; the moderator's
 * decision is in ModerationController's queue.
 */
final class CommunityController extends Controller
{
    private CommunityService $service;
    private UserDivision $memberships;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->memberships = new UserDivision($pdo);
        $this->service     = self::service($pdo);
    }

    /** Shared with ItemController, which asks it where a new listing goes. */
    public static function service(PDO $pdo): CommunityService
    {
        return new CommunityService(
            $pdo,
            new User($pdo),
            new UserDivision($pdo),
            new GnDivision($pdo),
            new Item($pdo),
            new Booking($pdo),
            new Notification($pdo),
            PhotoStore::uploads()
        );
    }

    /**
     * GET /community/temporary.
     */
    public function createForm(): void
    {
        $this->renderPage([], []);
    }

    /**
     * POST /community/temporary — apply, or apply again after a rejection.
     */
    public function store(): void
    {
        $validator = new Validator($_POST);
        $validator
            ->required('division', 'Temporary community')
            ->integer('division', 'Temporary community', 1)
            ->required('proof_type', 'Kind of proof')
            ->inList('proof_type', 'Kind of proof', array_keys(CommunityService::PROOF_TYPES));

        if (!$validator->passes()) {
            $this->renderPage($validator->errors(), $validator->values());

            return;
        }

        try {
            $name = $this->service->apply(
                $this->userId(),
                (int) $validator->value('division'),
                $validator->value('proof_type'),
                uploaded_files('proof')
            );
        } catch (ValidationException $exception) {
            $this->renderPage($exception->errors(), $validator->values());

            return;
        }

        $this->flash('Request sent. The ' . $name . ' moderator checks your proof before you can take part there.');
        $this->redirect('/community/temporary');
    }

    /**
     * POST /community/temporary/extend — fresh proof while active or in grace.
     */
    public function extend(): void
    {
        try {
            $this->service->requestExtension($this->userId(), uploaded_files('renewal_proof'));
        } catch (ValidationException $exception) {
            $this->renderPage($exception->errors(), []);

            return;
        }

        $this->flash('Extension requested. Your moderator checks the fresh proof.');
        $this->redirect('/community/temporary');
    }

    /**
     * POST /community/temporary/leave — withdraw a pending request, or leave.
     */
    public function leave(): void
    {
        try {
            $outcome = $this->service->leave($this->userId());
            $this->flash($outcome === 'withdrawn'
                ? 'Request withdrawn.'
                : 'You left your temporary community. Your listings there are paused.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        }

        $this->redirect('/community/temporary');
    }

    /**
     * POST /community/promote — the temporary community becomes home.
     */
    public function promote(): void
    {
        try {
            $name = $this->service->promote($this->userId());
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/community/temporary');

            return;
        }

        $this->flash($name . ' is now your home community. Enter your new address so your moderator can confirm it.');
        $this->redirect('/profile#address');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $input
     */
    private function renderPage(array $errors, array $input): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $me      = $this->userId();
        $home    = (new User($this->pdo))->findWithDivision($me) ?? [];
        $current = $this->memberships->latestTemporary($me);
        $homeId  = (int) ($home['division_id'] ?? 0);

        $divisions = array_values(array_filter(
            (new GnDivision($this->pdo))->activeNames(),
            static fn (array $row): bool => (int) $row['id'] !== $homeId
        ));

        $state = $current === null ? 'none' : (string) $current['status'];

        // A rejection stays on screen for a week with its reason, and the form
        // comes back pre-filled so the member can resubmit (§19).
        $recentRejection = $state === 'rejected' && $current['verified_at'] !== null
            && strtotime((string) $current['verified_at']) >= strtotime('-' . CommunityService::RESUBMIT_DAYS . ' days');

        $this->render('community/create', [
            'homeCommunity' => (string) ($home['division_name'] ?? 'No home division'),
            'current'       => $current === null ? null : $this->currentView($current),
            'state'         => $state,
            'canApply'      => !in_array($state, ['pending', 'active', 'paused'], true),
            'showRejection' => $recentRejection,
            'divisions'     => array_map(static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'name' => $row['name'] . ' · ' . $row['district'],
            ], $divisions),
            'proofTypes'    => CommunityService::PROOF_TYPES,
            'draft'         => $input + [
                'division'   => $recentRejection ? (string) $current['gn_division_id'] : '',
                'proof_type' => '',
            ],
            'errors'        => $errors,
            'promotion'     => [
                'temporary_name' => (string) ($current['division_name'] ?? ''),
                'home_line'      => (string) ($home['division_name'] ?? ''),
                'temporary_line' => $current === null ? '' : $this->currentView($current)['line'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $current
     *
     * @return array<string, string|bool>
     */
    private function currentView(array $current): array
    {
        [$label, $tone] = match ((string) $current['status']) {
            'pending'     => ['Waiting for the moderator', 'warning'],
            'active'      => ['Active', 'success'],
            'paused'      => ['Expired — in grace period', 'warning'],
            'rejected'    => ['Not approved', 'error'],
            'expired'     => ['Ended', 'neutral'],
            default       => ['Ended', 'neutral'],
        };

        $line = $current['division_name'] . ' · ' . $label;

        if ($current['expires_at'] !== null && in_array($current['status'], ['active', 'paused'], true)) {
            $expiry = new DateTimeImmutable((string) $current['expires_at']);
            $line  .= $current['status'] === 'active'
                ? ' · Expires ' . $expiry->format('j M Y')
                : ' · Extend by ' . CommunityService::graceEnds($expiry)->format('j M Y');
        }

        return [
            'name'      => (string) $current['division_name'],
            'label'     => $label,
            'tone'      => $tone,
            'line'      => $line,
            'reason'    => (string) ($current['decision_reason'] ?? ''),
            'extending' => $current['renewal_requested_at'] !== null,
        ];
    }
}
