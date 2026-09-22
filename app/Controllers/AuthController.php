<?php

declare(strict_types=1);

/**
 * Signing up, in and out.
 *
 * HTTP plumbing only (Rules/CONVENTIONS.md §6): validate at the boundary, hand
 * the values to a service, and on success write the member id and role to the
 * session — the two keys the rest of the app reads.
 *
 * These are the only screens a signed-out visitor can reach — sign-in,
 * sign-up and the two ways back into an account (an emailed link, or a code
 * issued in person by the moderator); AuthMiddleware sends every other request
 * here. Public sign-up creates members and nothing
 * else — moderator, liaison, sponsor and admin accounts are appointed, not
 * self-served (Plan §16).
 */
final class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->auth = new AuthService(new User($pdo), new LoginThrottle(new LoginAttempt($pdo)));
    }

    /**
     * GET /login.
     */
    public function loginForm(): void
    {
        if ($this->signedIn()) {
            $this->redirect(home_for((string) ($_SESSION['role'] ?? 'member')));

            return;
        }

        $this->renderLogin([], '');
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
            $this->renderLogin($validator->errors(), $identifier);

            return;
        }

        try {
            // The raw value, not the validator's: a password is whatever was
            // typed, trailing spaces included.
            $account = $this->auth->authenticate($identifier, $this->postedPassword('password'), $this->clientIp());
        } catch (ValidationException $exception) {
            $this->renderLogin($exception->errors(), $identifier);

            return;
        }

        $this->startSession($account);
        $this->redirect(home_for((string) $account['role_code']));
    }

    /**
     * GET /register.
     */
    public function registerForm(): void
    {
        if ($this->signedIn()) {
            $this->redirect(home_for((string) ($_SESSION['role'] ?? 'member')));

            return;
        }

        $this->renderRegister([], []);
    }

    /**
     * POST /register.
     *
     * A successful application cannot sign in yet, so it ends on the sign-in
     * screen with the reason rather than in a session (Plan §18.1).
     */
    public function register(): void
    {
        $validator = new Validator($_POST);
        $validator
            ->required('full_name', 'Full name')
            ->maxLength('full_name', 'Full name', 150)
            ->required('nic', 'NIC number')
            ->maxLength('nic', 'NIC number', 20)
            ->required('phone', 'Mobile number')
            ->maxLength('phone', 'Mobile number', 20)
            ->maxLength('email', 'Email address', 150)
            ->required('address', 'Address')
            ->maxLength('address', 'Address', 255)
            ->required('gn_division_id', 'GN division')
            ->integer('gn_division_id', 'GN division', 1);

        if (!$validator->passes()) {
            $this->renderRegister($validator->errors(), $validator->values());

            return;
        }

        $input = $validator->values() + ['email' => '', 'gn_division_id' => ''];

        try {
            $this->registrations()->register(
                [
                    'full_name'      => $input['full_name'],
                    'nic'            => $input['nic'],
                    'phone'          => $input['phone'],
                    'email'          => $input['email'],
                    'address'        => $input['address'],
                    'gn_division_id' => $input['gn_division_id'],
                ],
                $this->postedPassword('password'),
                $this->postedPassword('password_confirmation'),
                [
                    'nic_photo'     => uploaded_files('nic_photo'),
                    'address_proof' => uploaded_files('address_proof'),
                ]
            );
        } catch (ValidationException $exception) {
            $this->renderRegister($exception->errors(), $validator->values());

            return;
        }

        $this->flash(
            'Application received. Your division moderator reviews it within five days, '
            . 'and you can sign in as soon as it is approved.'
        );
        $this->redirect('/login');
    }

    // ── Forgotten password ──────────────────────────────────────────────────

    /**
     * GET /forgot-password.
     */
    public function forgotForm(): void
    {
        $this->renderAuthPage('forgot', [], ['email' => '']);
    }

    /**
     * POST /forgot-password — always the same answer, whether or not the
     * address belongs to an account.
     */
    public function forgot(): void
    {
        $validator = new Validator($_POST);
        $validator->required('email', 'Email address')->maxLength('email', 'Email address', 150);

        if (!$validator->passes()) {
            $this->renderAuthPage('forgot', $validator->errors(), ['email' => $validator->value('email')]);

            return;
        }

        $request = $this->passwordResets()->requestLink($validator->value('email'));
        $devLink = null;

        if ($request !== null) {
            $url = $this->resetUrl($request['token']);
            $mailer = Mailer::fromConfig();
            $sent = $url !== null && $mailer->send(
                $request['email'],
                'Reset your Mithra password',
                "Hello {$request['name']},\n\nSomeone asked to reset the password for your Mithra account. "
                . "If it was you, open this link within " . PasswordResetService::LINK_TTL_MINUTES . " minutes:\n\n{$url}\n\n"
                . "If it was not you, ignore this email — your password has not changed.\n"
            );

            if ($url === null) {
                error_log('Password reset: app.url is not configured, so no link could be sent.');
            }

            // A local install has no mail server; show the link instead so the
            // flow can be tested. Never in production.
            if (!$sent && Config::get('app.env', 'production') === 'local') {
                $devLink = $url;
            }
        }

        $this->renderAuthPage('forgot', [], ['email' => '', 'sent' => true, 'devLink' => $devLink]);
    }

    /**
     * GET /reset-password?token=…
     */
    public function resetForm(): void
    {
        $token = (string) ($_GET['token'] ?? '');

        $this->renderAuthPage('reset', [], [
            'token' => $token,
            'valid' => $this->passwordResets()->linkIsValid($token),
        ]);
    }

    /**
     * POST /reset-password.
     */
    public function reset(): void
    {
        $token = (string) ($_POST['token'] ?? '');

        try {
            $this->passwordResets()->resetWithLink(
                $token,
                $this->postedPassword('password'),
                $this->postedPassword('password_confirmation')
            );
        } catch (ValidationException $exception) {
            $this->renderAuthPage('reset', $exception->errors(), [
                'token' => $token,
                'valid' => $this->passwordResets()->linkIsValid($token),
            ]);

            return;
        }

        $this->flash('Your password has been changed. Sign in with the new one.');
        $this->redirect('/login');
    }

    /**
     * GET /reset-password/code.
     */
    public function codeForm(): void
    {
        $this->renderAuthPage('reset-code', [], ['identifier' => '']);
    }

    /**
     * POST /reset-password/code.
     */
    public function resetWithCode(): void
    {
        $validator = new Validator($_POST);
        $validator
            ->required('identifier', 'Email or mobile number')
            ->maxLength('identifier', 'Email or mobile number', 150)
            ->required('code', 'Reset code')
            ->maxLength('code', 'Reset code', 20);

        $identifier = $validator->value('identifier');

        if (!$validator->passes()) {
            $this->renderAuthPage('reset-code', $validator->errors(), ['identifier' => $identifier]);

            return;
        }

        try {
            $this->passwordResets()->resetWithCode(
                $identifier,
                $validator->value('code'),
                $this->postedPassword('password'),
                $this->postedPassword('password_confirmation'),
                $this->clientIp()
            );
        } catch (ValidationException $exception) {
            $this->renderAuthPage('reset-code', $exception->errors(), ['identifier' => $identifier]);

            return;
        }

        $this->flash('Your password has been changed. Sign in with the new one.');
        $this->redirect('/login');
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

        // What SessionMiddleware compares on every later request (§21.1).
        $_SESSION['password_stamp'] = $account['password_changed_at'] === null ? null : (string) $account['password_changed_at'];
        $_SESSION['last_seen']      = time();
    }

    private function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }

    /**
     * The absolute reset link. The host comes from config, never from the
     * request's Host header, so a forged header cannot point a real member's
     * email at someone else's site. Only a local install may fall back to the
     * request host.
     */
    private function resetUrl(string $token): ?string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');

        if ($base === '' && Config::get('app.env', 'production') === 'local') {
            $base = 'http://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_url();
        }

        return $base === '' ? null : $base . '/reset-password?token=' . rawurlencode($token);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function renderAuthPage(string $view, array $errors, array $values = []): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $this->render('auth/' . $view, ['errors' => $errors] + $values);
    }

    private function signedIn(): bool
    {
        return (int) ($_SESSION['user_id'] ?? 0) > 0;
    }

    private function registrations(): RegistrationService
    {
        return new RegistrationService(
            $this->pdo,
            new User($this->pdo),
            new UserDivision($this->pdo),
            new GnDivision($this->pdo),
            $this->uploads()
        );
    }


    /**
     * @param array<string, string> $errors
     */
    private function renderLogin(array $errors, string $identifier): void
    {
        $this->renderAuthPage('login', $errors, ['identifier' => $identifier]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $input values to show back after a failure
     */
    private function renderRegister(array $errors, array $input): void
    {
        $this->renderAuthPage('register', $errors, [
            'input'     => $input,
            'divisions' => (new GnDivision($this->pdo))->activeNames(),
        ]);
    }
}
