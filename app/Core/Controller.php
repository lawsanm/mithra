<?php

declare(strict_types=1);

/**
 * What every controller shares: who is signed in, how a page is rendered, and
 * the flash / redirect / refusal plumbing (Rules/CONVENTIONS.md §6).
 *
 * Controllers stay HTTP-only: business rules live in services, SQL in models.
 */
abstract class Controller
{
    public function __construct(protected PDO $pdo)
    {
    }

    /** The signed-in account. AuthMiddleware guarantees one outside the sign-in pages. */
    protected function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    protected function role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    /**
     * Render views/<view>.php with $data as local variables.
     *
     * Every page also receives $viewer (the signed-in account shown in the
     * navigation bar) and $flash (a one-shot message left by a redirect). A
     * page that several roles share passes 'chrome' => chrome_for($this->role())
     * so each role sees its own navigation.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): void
    {
        $data['viewer'] = $this->viewer();
        $data['flash']  = $this->takeFlash();

        extract($data, EXTR_SKIP);

        require dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

    /** A refusal or missing-record page with the right HTTP status. */
    protected function notice(int $status, string $title, string $body): void
    {
        http_response_code($status);

        $this->render('errors/notice', ['noticeTitle' => $title, 'noticeBody' => $body]);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . base_url() . $path, true, 303);
    }

    /** A message shown once, on the next page rendered. */
    protected function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /** Passwords are taken exactly as typed — never trimmed. */
    protected function postedPassword(string $field): string
    {
        $value = $_POST[$field] ?? '';

        return is_string($value) ? $value : '';
    }

    protected function uploads(): PhotoStore
    {
        return new PhotoStore(dirname(__DIR__, 2) . '/storage/uploads');
    }

    protected function passwordResets(): PasswordResetService
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

    protected function profiles(): ProfileService
    {
        return new ProfileService(
            $this->pdo,
            new User($this->pdo),
            new UserDivision($this->pdo),
            new AddressChange($this->pdo),
            new GnDivision($this->pdo),
            $this->uploads()
        );
    }

    /**
     * The signed-in account as the navigation bar shows it, or null on the
     * signed-out pages.
     *
     * @return array{initials: string, points: string, bond: string, company: string}|null
     */
    private function viewer(): ?array
    {
        $id = $this->userId();

        if ($id <= 0) {
            return null;
        }

        $name    = (string) ((new User($this->pdo))->find($id)['full_name'] ?? '');
        $wallets = new Wallet($this->pdo);

        return [
            'initials' => User::initials($name),
            'points'   => number_format($wallets->balance($id)) . ' pts',
            'bond'     => 'Bond: ' . number_format($wallets->bondLocked($id)) . ' pts',
            'company'  => $this->role() === 'sponsor'
                ? ((new Sponsor($this->pdo))->companyForUser($id) ?? $name)
                : '',
        ];
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
