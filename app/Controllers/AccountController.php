<?php

declare(strict_types=1);

/**
 * The signed-in account itself (Plan §20.1 module 1.1): changing the password
 * (every role), and for members the Settings page — the receive-gifts
 * preference (§11.1) and closing the account (§17).
 *
 * HTTP plumbing only (Rules/CONVENTIONS.md §6); the rules live in
 * PasswordResetService, ProfileService and AccountClosureService.
 */
final class AccountController
{
    /** Pages a password change may send the member back to — never a URL from the form. */
    private const RETURN_PATHS = ['/account/password', '/settings', '/admin/settings/security'];

    public function __construct(private PDO $pdo)
    {
    }

    // ── Password (every role) ───────────────────────────────────────────────

    /**
     * GET /account/password.
     */
    public function passwordForm(): void
    {
        $this->render('account/password', ['errors' => [], 'returnTo' => '/account/password']);
    }

    /**
     * POST /account/password.
     */
    public function changePassword(): void
    {
        $returnTo = (string) ($_POST['return_to'] ?? '/account/password');
        $returnTo = in_array($returnTo, self::RETURN_PATHS, true) ? $returnTo : '/account/password';

        try {
            $stamp = $this->resets()->change(
                $this->userId(),
                $this->posted('current_password'),
                $this->posted('password'),
                $this->posted('password_confirmation')
            );
        } catch (ValidationException $exception) {
            http_response_code(422);
            $this->render('account/password', ['errors' => $exception->errors(), 'returnTo' => $returnTo]);

            return;
        }

        // This session proved the old password, so it stays signed in under a
        // fresh id; every other session now fails SessionMiddleware.
        session_regenerate_id(true);
        $_SESSION['password_stamp'] = $stamp;

        $this->flash('Your password has been changed. Other devices have been signed out.');
        $this->redirect($returnTo);
    }

    // ── Member settings ─────────────────────────────────────────────────────

    /**
     * GET /settings, GET /settings/close-account.
     */
    public function settings(): void
    {
        $this->renderSettings([]);
    }

    /**
     * POST /settings/preferences.
     */
    public function savePreferences(): void
    {
        $this->profiles()->setGiftReceive($this->userId(), ($_POST['receive_gifts'] ?? '') === '1');

        $this->flash('Preferences saved.');
        $this->redirect('/settings');
    }

    /**
     * POST /settings/close-account.
     */
    public function closeAccount(): void
    {
        $validator = new Validator($_POST);
        $validator->inList('closure_type', 'Closure type', AccountClosureService::TYPES);

        if (!$validator->passes()) {
            $this->renderSettings($validator->errors());

            return;
        }

        try {
            $moved = $this->closures()->close(
                $this->userId(),
                $validator->value('closure_type'),
                $this->posted('password')
            );
        } catch (ValidationException $exception) {
            $this->renderSettings($exception->errors());

            return;
        }

        $destination = $validator->value('closure_type') === 'parting_gift' ? 'the Aid Pool' : 'the Retired Pool';

        $_SESSION = [];
        session_regenerate_id(true);
        $this->flash(sprintf(
            'Your account is closed. %s pts went to %s. Thank you for being part of your community.',
            number_format($moved),
            $destination
        ));
        $this->redirect('/login');
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $errors
     */
    private function renderSettings(array $errors): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $account  = (new User($this->pdo))->findAccount($this->userId()) ?? [];
        $closures = $this->closures();

        $this->render('settings/index', [
            'account' => [
                'email'        => (string) ($account['email'] ?? '') ?: 'Not set',
                'mobile'       => (string) ($account['phone'] ?? ''),
                'password_age' => $account['password_changed_at'] ?? null
                    ? 'Last changed ' . date('j M Y', strtotime((string) $account['password_changed_at'])) . '.'
                    : 'Not changed since you joined.',
            ],
            'receiveGifts'    => (int) ($account['gift_receive_enabled'] ?? 0) === 1,
            'remainingPoints' => number_format($closures->balance($this->userId())) . ' pts',
            'closureBlockers' => $closures->blockers($this->userId()),
            'errors'          => $errors,
            'openClose'       => isset($errors['close_password']) || isset($errors['closure_type']) || isset($errors['form']),
        ]);
    }

    /**
     * Every role's page chrome, so one password page serves them all.
     *
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): void
    {
        // Named $viewer, not $account: extract() below must not be shadowed by
        // a local of the same name as a view variable.
        $role     = (string) ($_SESSION['role'] ?? 'member');
        $viewer   = (new User($this->pdo))->findAccount($this->userId()) ?? ['full_name' => ''];
        $initials = User::initials((string) $viewer['full_name']);

        $data['chrome'] = match ($role) {
            'admin'           => 'admin',
            'moderator'       => 'moderator',
            'sponsor'         => 'sponsor',
            'sponsor_liaison' => 'sponsor-liaison',
            default           => '',
        };
        $data['currentAdmin']     = ['initials' => $initials];
        $data['currentModerator'] = ['initials' => $initials, 'bond' => 'Bond: ' . number_format((new Wallet($this->pdo))->bondLocked($this->userId())) . ' pts'];
        $data['currentLiaison']   = ['initials' => $initials];
        $data['currentSponsor']   = ['initials' => $initials, 'company_name' => (string) $viewer['full_name']];
        $data['currentMember']    = [
            'initials'       => $initials,
            'points_balance' => number_format((new Wallet($this->pdo))->balance($this->userId())) . ' pts',
        ];
        $data['flash'] = $this->takeFlash();

        extract($data, EXTR_SKIP);

        include dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

    private function resets(): PasswordResetService
    {
        return new PasswordResetService(
            $this->pdo,
            new User($this->pdo),
            new PasswordReset($this->pdo),
            new GnDivision($this->pdo),
            new AuthService(new User($this->pdo), new LoginThrottle(new LoginAttempt($this->pdo))),
            new LoginThrottle(new LoginAttempt($this->pdo))
        );
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

    private function closures(): AccountClosureService
    {
        return new AccountClosureService(
            $this->pdo,
            new User($this->pdo),
            new UserDivision($this->pdo),
            new Item($this->pdo),
            new Booking($this->pdo),
            new AidGrant($this->pdo),
            new Wallet($this->pdo),
            new PasswordReset($this->pdo),
            new LedgerService($this->pdo, new PointLedger($this->pdo), new PointPool($this->pdo), new Wallet($this->pdo))
        );
    }

    /** Passwords are taken exactly as typed — never trimmed. */
    private function posted(string $field): string
    {
        $value = $_POST[$field] ?? '';

        return is_string($value) ? $value : '';
    }

    private function userId(): int
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
