<?php

declare(strict_types=1);

/**
 * The moderator's verification queue — the decision that turns a pending
 * registration into an account that can sign in (Plan §18.1).
 *
 * Each action reads validated input, calls one service method, then renders a
 * view or redirects. Which applications this moderator may see and decide is a
 * business rule, so it lives in VerificationService, not here (§6).
 *
 * Home applications, temporary-community applications and their extension
 * requests share the queue (Plan §6.5). "Request more info" is in Figma but
 * has no state to record, so it stays visibly unavailable.
 */
final class ModerationController extends Controller
{
    private VerificationService $verifications;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->verifications = new VerificationService(
            $pdo,
            new User($pdo),
            new UserDivision($pdo),
            new GnDivision($pdo),
            new Wallet($pdo),
            new LedgerService($pdo, new PointLedger($pdo), new PointPool($pdo), new Wallet($pdo)),
            new Notification($pdo)
        );
    }

    /**
     * GET /moderator/verifications.
     */
    public function index(): void
    {
        $filter = $this->filter((string) ($_GET['status'] ?? ''));

        try {
            $rows    = $this->verifications->queue($this->userId(), $filter);
            $pending = $this->verifications->pendingCount($this->userId());
        } catch (AccessDeniedException $exception) {
            $this->notice(403, 'No queue here', 'This account does not moderate a division.');

            return;
        }

        $this->render('moderator/verifications/index', [
            'filters'       => $this->filterPills($filter),
            'filterSummary' => $pending . ' pending',
            'verifications' => array_map(fn (array $row): array => $this->queueRow($row), $rows),
        ]);
    }

    /**
     * GET /moderator/verifications/{id}.
     */
    public function show(int $id): void
    {
        try {
            $application = $this->verifications->review($id, $this->userId());
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->render('moderator/verifications/show', [
            'kind'      => $this->kindOf($application),
            'applicant' => [
                'initials'     => User::initials((string) $application['full_name']),
                'name'         => (string) $application['full_name'],
                'status'       => $this->badgeFor($this->stateOf($application)),
                'status_label' => $this->labelFor($this->stateOf($application)),
                'submitted'    => $this->submittedLine($application),
            ],
            'facts'     => $this->facts($application),
            'documents' => $this->documents($application),
            'decided'  => $this->stateOf($application) !== 'pending',
            'recordId' => (int) $application['id'],
        ]);
    }

    /**
     * POST /moderator/verifications/{id}/approve.
     */
    public function approve(int $id): void
    {
        try {
            $kind = $this->kindOf($this->verifications->review($id, $this->userId()));
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->decide(
            $id,
            fn (int $moderatorId): string => $this->verifications->approve($id, $moderatorId),
            match ($kind) {
                'Temporary community' => '%s can now take part here as a temporary member.',
                'Temporary extension' => "%s's temporary membership was extended.",
                default               => '%s is now a verified member and can sign in.',
            }
        );
    }

    /**
     * POST /moderator/verifications/{id}/reject.
     */
    public function reject(int $id): void
    {
        $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : null;

        $this->decide(
            $id,
            fn (int $moderatorId): string => $this->verifications->reject($id, $moderatorId, $reason),
            "%s's request was rejected."
        );
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    /**
     * Both decisions differ only in the service call and the sentence they
     * leave behind; everything else — refusal handling, flash, redirect — is
     * identical.
     *
     * @param callable(int): string $decision
     */
    private function decide(int $id, callable $decision, string $message): void
    {
        try {
            $this->flash(sprintf($message, $decision($this->userId())));
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/moderator/verifications/' . $id);

            return;
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->redirect('/moderator/verifications');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function queueRow(array $row): array
    {
        return [
            'name'         => (string) $row['full_name'],
            'meta'         => sprintf(
                '%s  ·  NIC %s  ·  %s  ·  applied %s',
                $this->kindOf($row),
                (string) $row['nic'],
                (string) $row['division_name'],
                date('j M Y', strtotime((string) $row['created_at']))
            ),
            'status'       => $this->badgeFor($this->stateOf($row)),
            'status_label' => $this->labelFor($this->stateOf($row)),
            'href'         => base_url() . '/moderator/verifications/' . (string) $row['id'],
        ];
    }

    /**
     * @param array<string, mixed> $application
     *
     * @return list<array{label: string, value: string}>
     */
    private function facts(array $application): array
    {
        return [
            ['label' => 'GN division',     'value' => (string) $application['division_name']],
            ['label' => 'Membership type', 'value' => $this->kindOf($application)],
            ['label' => 'Proof of stay',   'value' => (string) (CommunityService::PROOF_TYPES[$application['proof_type'] ?? ''] ?? ($application['membership_type'] === 'home' ? 'Proof of address' : '—'))],
            ['label' => 'Expires',         'value' => empty($application['expires_at']) ? '—' : date('j M Y', strtotime((string) $application['expires_at']))],
            ['label' => 'NIC',             'value' => (string) $application['nic']],
            ['label' => 'Address',         'value' => (string) $application['address']],
            ['label' => 'Mobile',          'value' => (string) $application['phone']],
            ['label' => 'Email',           'value' => (string) ($application['email'] ?? '') ?: '—'],
        ];
    }

    /**
     * The applicant's uploads, as proxy URLs — the files themselves live
     * outside the web root (§7.5).
     *
     * @param array<string, mixed> $application
     *
     * @return list<array{label: string, url: string}>
     */
    private function documents(array $application): array
    {
        $documents = [];

        $proofLabel = $application['membership_type'] === 'home' ? 'Proof of address' : 'Proof of stay';

        foreach (['nic_photo_path' => 'NIC photograph', 'proof_file_path' => $proofLabel, 'renewal_proof_path' => 'Fresh proof for the extension'] as $column => $label) {
            if (($application[$column] ?? null) !== null) {
                $documents[] = [
                    'label' => $label,
                    'url'   => photo_url((string) $application[$column]),
                ];
            }
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $application
     */
    private function submittedLine(array $application): string
    {
        $applied = $this->kindOf($application) . ' application  ·  applied '
            . date('j M Y', strtotime((string) $application['created_at']));

        if ($this->stateOf($application) === 'pending' || $application['verified_at'] === null) {
            return $applied;
        }

        return $applied . '  ·  decided ' . date('j M Y', strtotime((string) $application['verified_at']))
            . ($application['decided_by_name'] === null ? '' : ' by ' . (string) $application['decided_by_name']);
    }

    /**
     * @return list<array{label: string, state: string, active: bool}>
     */
    private function filterPills(string $active): array
    {
        $labels = ['' => 'All', 'pending' => 'Pending', 'temporary' => 'Temporary', 'active' => 'Approved', 'rejected' => 'Rejected'];
        $pills  = [];

        foreach ($labels as $state => $label) {
            $pills[] = ['label' => $label, 'state' => $state, 'active' => $state === $active];
        }

        return $pills;
    }

    private function filter(string $requested): string
    {
        return in_array($requested, VerificationService::FILTERS, true) ? $requested : '';
    }

    /**
     * What the moderator is deciding: a home application, a temporary one, or
     * an extension of a temporary one.
     *
     * @param array<string, mixed> $row
     */
    private function kindOf(array $row): string
    {
        if (($row['membership_type'] ?? 'home') === 'home') {
            return 'Home community';
        }

        return $row['status'] !== 'pending' && ($row['renewal_requested_at'] ?? null) !== null
            ? 'Temporary extension'
            : 'Temporary community';
    }

    /**
     * An extension request waits on a live row, so it reads as pending.
     *
     * @param array<string, mixed> $row
     */
    private function stateOf(array $row): string
    {
        return ($row['renewal_requested_at'] ?? null) !== null ? 'pending' : (string) $row['status'];
    }

    private function badgeFor(string $status): string
    {
        return match ($status) {
            'pending'  => 'warning',
            'active'   => 'success',
            'rejected' => 'error',
            default    => 'info',
        };
    }

    private function labelFor(string $status): string
    {
        return match ($status) {
            'pending'  => 'Awaiting review',
            'active'   => 'Approved',
            'rejected' => 'Rejected',
            default    => ucfirst($status),
        };
    }

    private function renderException(RecordNotFoundException|AccessDeniedException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->notice(403, 'Not your division', 'You can only review applications from the division you moderate.');

            return;
        }

        $this->notice(404, 'Record not found', 'This review is no longer available. Return to the queue to choose a record.');
    }
}
