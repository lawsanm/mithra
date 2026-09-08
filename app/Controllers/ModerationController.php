<?php

declare(strict_types=1);

/**
 * The moderator's verification queue — the decision that turns a pending
 * registration into an account that can sign in (Proposal §19.1).
 *
 * Each action reads validated input, calls one service method, then renders a
 * view or redirects. Which applications this moderator may see and decide is a
 * business rule, so it lives in VerificationService, not here (§6).
 *
 * Only member verification is wired. Temporary-community proofs, the review
 * checklist and "request more info" share this screen in Figma but have no
 * backend yet, and stay visibly unavailable rather than pretending to save.
 */
final class ModerationController
{
    private PDO $pdo;
    private VerificationService $verifications;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->verifications = new VerificationService(
            $pdo,
            new User($pdo),
            new UserDivision($pdo),
            new GnDivision($pdo),
            new Wallet($pdo)
        );
    }

    /**
     * GET /moderator/verifications.
     */
    public function index(): void
    {
        $filter = $this->filter((string) ($_GET['status'] ?? ''));

        try {
            $rows    = $this->verifications->queue($this->moderatorId(), $filter);
            $pending = $this->verifications->pendingCount($this->moderatorId());
        } catch (AccessDeniedException $exception) {
            $this->renderNotice(403, 'No queue here', 'This account does not moderate a division.');

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
            $application = $this->verifications->review($id, $this->moderatorId());
        } catch (RuntimeException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->render('moderator/verifications/show', [
            'applicant' => [
                'initials'     => User::initials((string) $application['full_name']),
                'name'         => (string) $application['full_name'],
                'status'       => $this->badgeFor((string) $application['status']),
                'status_label' => $this->labelFor((string) $application['status']),
                'submitted'    => $this->submittedLine($application),
            ],
            'facts'    => $this->facts($application),
            'decided'  => $application['status'] !== 'pending',
            'recordId' => (int) $application['id'],
        ]);
    }

    /**
     * POST /moderator/verifications/{id}/approve.
     */
    public function approve(int $id): void
    {
        $this->decide(
            $id,
            fn (int $moderatorId): string => $this->verifications->approve($id, $moderatorId),
            '%s is now a verified member and can sign in.'
        );
    }

    /**
     * POST /moderator/verifications/{id}/reject.
     */
    public function reject(int $id): void
    {
        $this->decide(
            $id,
            fn (int $moderatorId): string => $this->verifications->reject($id, $moderatorId),
            "%s's application was rejected."
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
            $this->flash(sprintf($message, $decision($this->moderatorId())));
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RuntimeException $exception) {
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
                'NIC %s  ·  %s  ·  applied %s',
                (string) $row['nic'],
                (string) $row['division_name'],
                date('j M Y', strtotime((string) $row['created_at']))
            ),
            'status'       => $this->badgeFor((string) $row['status']),
            'status_label' => $this->labelFor((string) $row['status']),
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
            ['label' => 'Membership type', 'value' => 'Home community'],
            ['label' => 'NIC',             'value' => (string) $application['nic']],
            ['label' => 'Address',         'value' => (string) $application['address']],
            ['label' => 'Mobile',          'value' => (string) $application['phone']],
            ['label' => 'Email',           'value' => (string) ($application['email'] ?? '') ?: '—'],
        ];
    }

    /**
     * @param array<string, mixed> $application
     */
    private function submittedLine(array $application): string
    {
        $applied = 'Home membership application  ·  applied '
            . date('j M Y', strtotime((string) $application['created_at']));

        if ($application['status'] === 'pending' || $application['verified_at'] === null) {
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
        $labels = ['' => 'All', 'pending' => 'Pending', 'active' => 'Approved', 'rejected' => 'Rejected'];
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

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): void
    {
        $appointment = (new Moderator($this->pdo))->findByUserId($this->moderatorId()) ?? [];

        $data['currentModerator'] = [
            'initials' => User::initials((string) ($appointment['full_name'] ?? '')),
            'bond'     => 'Bond: ' . number_format((int) ($appointment['bond_points'] ?? 0)) . ' pts',
        ];
        $data['flash'] = $this->takeFlash();

        extract($data, EXTR_SKIP);

        include dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

    private function renderException(RuntimeException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->renderNotice(403, 'Not your division', 'You can only review applications from the division you moderate.');

            return;
        }

        $this->renderNotice(404, 'Record not found', 'This review is no longer available. Return to the queue to choose a record.');
    }

    private function renderNotice(int $status, string $title, string $body): void
    {
        http_response_code($status);

        $noticeTitle = $title;
        $noticeBody  = $body;

        include dirname(__DIR__, 2) . '/views/errors/notice.php';
    }

    private function moderatorId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? ['type' => (string) $flash['type'], 'message' => (string) $flash['message']] : null;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . base_url() . $path, true, 303);
    }
}
