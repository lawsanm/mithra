<?php

declare(strict_types=1);

/**
 * Helping members with their accounts in person (Plan §16.3, §18.1):
 *
 *   - the moderator reviews address changes in their division, with the proof;
 *   - the moderator (for members of their division) or the Admin (for any
 *     account) issues a one-time password-reset code after checking the
 *     person's NIC face to face.
 *
 * HTTP plumbing only; the rules live in ProfileService and
 * PasswordResetService. RbacMiddleware has already limited each path to its
 * role.
 */
final class AccountSupportController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ── Address changes (moderator) ─────────────────────────────────────────

    /**
     * GET /moderator/address-changes.
     */
    public function addressChanges(): void
    {
        $this->renderAddressChanges([]);
    }

    /**
     * POST /moderator/address-changes/{id}/approve.
     */
    public function approveAddress(int $id): void
    {
        $this->decideAddress($id, true);
    }

    /**
     * POST /moderator/address-changes/{id}/reject.
     */
    public function rejectAddress(int $id): void
    {
        $this->decideAddress($id, false);
    }

    // ── Reset codes (moderator, admin) ──────────────────────────────────────

    /**
     * GET /moderator/reset-codes, GET /admin/reset-codes.
     */
    public function resetCodeForm(): void
    {
        $this->render('moderator/reset-codes/index', ['errors' => [], 'lookup' => '', 'issued' => null]);
    }

    /**
     * POST /moderator/reset-codes, POST /admin/reset-codes.
     *
     * The code is shown on this response only — never stored in the session
     * or a flash, and never retrievable again.
     */
    public function issueResetCode(): void
    {
        $validator = new Validator($_POST);
        $validator->required('lookup', 'NIC or mobile number')->maxLength('lookup', 'NIC or mobile number', 20);
        $lookup = $validator->value('lookup');

        if (!$validator->passes()) {
            $this->render('moderator/reset-codes/index', ['errors' => $validator->errors(), 'lookup' => $lookup, 'issued' => null]);

            return;
        }

        try {
            $issued = $this->resets()->issueCode($this->userId(), $this->role(), $lookup);
        } catch (ValidationException $exception) {
            http_response_code(422);
            $this->render('moderator/reset-codes/index', ['errors' => $exception->errors(), 'lookup' => $lookup, 'issued' => null]);

            return;
        } catch (AccessDeniedException $exception) {
            http_response_code(403);
            $this->render('moderator/reset-codes/index', [
                'errors' => ['lookup' => 'That account is not a member of the division you moderate.'],
                'lookup' => $lookup,
                'issued' => null,
            ]);

            return;
        }

        header('Cache-Control: no-store');
        $this->render('moderator/reset-codes/index', ['errors' => [], 'lookup' => '', 'issued' => $issued]);
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    private function decideAddress(int $id, bool $approve): void
    {
        $reason = (string) ($_POST['reason'] ?? '');

        try {
            $name = $this->profiles()->decideAddressChange($id, $this->userId(), $approve, $reason);
        } catch (ValidationException $exception) {
            http_response_code(422);
            $this->renderAddressChanges($exception->errors() + ['for' => (string) $id]);

            return;
        } catch (AccessDeniedException | RecordNotFoundException $exception) {
            $this->renderNotice(404, 'Request not found', 'This address change is not in your division, or it no longer exists.');

            return;
        }

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => $approve ? "{$name}'s new address is now on file." : "{$name}'s address change was rejected.",
        ];
        header('Location: ' . base_url() . '/moderator/address-changes', true, 303);
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderAddressChanges(array $errors): void
    {
        try {
            $rows = $this->profiles()->addressQueue($this->userId());
        } catch (AccessDeniedException $exception) {
            $this->renderNotice(403, 'No queue here', 'This account does not moderate a division.');

            return;
        }

        $this->render('moderator/address-changes/index', [
            'errors'  => $errors,
            'changes' => array_map(static fn (array $row): array => [
                'id'       => (int) $row['id'],
                'name'     => (string) $row['full_name'],
                'initials' => User::initials((string) $row['full_name']),
                'from'     => (string) $row['current_address'],
                'to'       => (string) $row['new_address'],
                'sent'     => date('j M Y', strtotime((string) $row['created_at'])),
                'proof'    => base_url() . '/photo.php?p=' . rawurlencode((string) $row['proof_file_path']),
            ], $rows),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): void
    {
        $account  = (new User($this->pdo))->findAccount($this->userId()) ?? ['full_name' => ''];
        $initials = User::initials((string) $account['full_name']);

        $data['chrome']           = $this->role() === 'admin' ? 'admin' : 'moderator';
        $data['basePath']         = base_url() . ($this->role() === 'admin' ? '/admin/reset-codes' : '/moderator/reset-codes');
        $data['currentAdmin']     = ['initials' => $initials];
        $data['currentModerator'] = [
            'initials' => $initials,
            'bond'     => 'Bond: ' . number_format((new Wallet($this->pdo))->bondLocked($this->userId())) . ' pts',
        ];
        $data['flash'] = $this->takeFlash();

        extract($data, EXTR_SKIP);

        include dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

    private function renderNotice(int $status, string $title, string $body): void
    {
        http_response_code($status);

        $noticeTitle = $title;
        $noticeBody  = $body;

        include dirname(__DIR__, 2) . '/views/errors/notice.php';
    }

    private function profiles(): ProfileService
    {
        return new ProfileService(
            $this->pdo,
            new User($this->pdo),
            new UserDivision($this->pdo),
            new AddressChange($this->pdo),
            new GnDivision($this->pdo),
            new PhotoStore(dirname(__DIR__, 2) . '/storage/uploads')
        );
    }

    private function resets(): PasswordResetService
    {
        $throttle = new LoginThrottle(new LoginAttempt($this->pdo));

        return new PasswordResetService(
            $this->pdo,
            new User($this->pdo),
            new PasswordReset($this->pdo),
            new GnDivision($this->pdo),
            new AuthService(new User($this->pdo), $throttle),
            $throttle
        );
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
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
}
