<?php

declare(strict_types=1);

/**
 * Signing in and out.
 *
 * HTTP plumbing only (Rules/CONVENTIONS.md §6): validate at the boundary, hand
 * the two strings to AuthService, and on success write the member id and role
 * to the session — the two keys the rest of the app already reads.
 *
 * Nothing here enforces access to other screens. Until an Auth middleware is in
 * the chain, a signed-out visitor still reaches the interim demonstration
 * screens as the configured demo member (docs/INTERIM_GUIDE.md).
 */
final class AuthController
{
    private AuthService $auth;

    public function __construct(PDO $pdo)
    {
        $this->auth = new AuthService(new User($pdo));
    }

    /**
     * GET /login.
     */
    public function loginForm(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect($this->homeFor((string) ($_SESSION['role'] ?? 'member')));

            return;
        }

        $this->render([], '');
    }

    /**
     * POST /login.
     */
    public function login(): void
    {
        $validator = new Validator($_POST);
        $validator
            ->required('identifier', 'Email or mobile number')
            ->maxLength('identifier', 'Email or mobile number', 150)
            ->required('password', 'Password');

        $identifier = $validator->value('identifier');

        if (!$validator->passes()) {
            $this->render($validator->errors(), $identifier);

            return;
        }

        try {
            // The raw value, not the validator's: a password is whatever was
            // typed, trailing spaces included.
            $account = $this->auth->authenticate($identifier, $this->postedPassword());
        } catch (ValidationException $exception) {
            $this->render($exception->errors(), $identifier);

            return;
        }

        $this->startSession($account);
        $this->redirect($this->homeFor((string) $account['role_code']));
    }

    /**
     * POST /logout.
     */
    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);

        $this->flash('You have been signed out.');
        $this->redirect('/login');
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    /**
     * A new session id on sign-in, holding the member id and role and nothing
     * else (§7.7).
     *
     * @param array<string, mixed> $account
     */
    private function startSession(array $account): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $account['id'];
        $_SESSION['role']    = (string) $account['role_code'];
    }

    private function postedPassword(): string
    {
        $password = $_POST['password'] ?? '';

        return is_string($password) ? $password : '';
    }

    /**
     * Where each role lands after signing in.
     */
    private function homeFor(string $role): string
    {
        return match ($role) {
            'admin'           => '/admin',
            'moderator'       => '/moderator',
            'sponsor_liaison' => '/sponsor-liaison',
            'sponsor'         => '/sponsor',
            default           => '/dashboard',
        };
    }

    /**
     * @param array<string, string> $errors
     */
    private function render(array $errors, string $identifier): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $flash = $this->takeFlash();

        include dirname(__DIR__, 2) . '/views/auth/login.php';
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
