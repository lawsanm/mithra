<?php

declare(strict_types=1);

/**
 * Signing up, in and out.
 *
 * HTTP plumbing only (Rules/CONVENTIONS.md §6): validate at the boundary, hand
 * the values to a service, and on success write the member id and role to the
 * session — the two keys the rest of the app reads.
 *
 * These are the sign-in screens a signed-out visitor can reach — sign-in,
 * sign-up and the two ways back into an account (an emailed link, or a code
 * issued in person by the moderator). Public sign-up creates members and
 * nothing else — moderator, liaison, sponsor and admin accounts are appointed,
 * not self-served (Plan §16).
 *
 * Figma: Common → "Login" (93:282), "Forgot Password — Modal" (93:315),
 * "Reset Password — New Password" (646:764), "Register — Step 1" (93:127),
 * "Register — Step 2" (93:187) and "Register — Pending Review" (93:240).
 */
final class AuthController extends Controller
{
    /** The first sign-up step's answers, kept until the documents arrive. */
    private const REGISTER_DRAFT = 'register_draft';

    /** Who just applied, for the pending-review page. */
    private const REGISTERED = 'registered';

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
            $this->redirect($this->homeFor($this->role()));

            return;
        }

        $this->renderLogin();
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
            $this->renderLogin(['errors' => $validator->errors(), 'identifier' => $identifier]);

            return;
        }

        try {
            // The raw value, not the validator's: a password is whatever was
            // typed, trailing spaces included.
            $account = $this->auth->authenticate($identifier, $this->postedPassword('password'), $this->clientIp());
        } catch (ValidationException $exception) {
            $this->renderLogin(['errors' => $exception->errors(), 'identifier' => $identifier]);

            return;
        }

        $this->startSession($account);
        $this->redirect($this->homeFor((string) $account['role_code']));
    }

    // ── Sign-up ─────────────────────────────────────────────────────────────

    /**
     * GET /register — step 1 (personal and division details), or step 2
     * (documents and password) once step 1 has been accepted.
     */
    public function registerForm(): void
    {
        if ($this->signedIn()) {
            $this->redirect($this->homeFor($this->role()));

            return;
        }

        $draft = $this->registerDraft();

        if (($_GET['step'] ?? '') === '2' && $draft !== null) {
            $this->renderRegister(2, [], $draft);

            return;
        }

        $this->renderRegister(1, [], $draft ?? []);
    }

    /**
     * POST /register.
     */
    public function register(): void
    {
        if (($_POST['step'] ?? '') === '2') {
            $this->registerDocuments();

            return;
        }

        $this->registerDetails();
    }

    /**
     * GET /register/pending.
     *
     * A successful application cannot sign in yet (Plan §18.1), so sign-up
     * ends here rather than in a session.
     */
    public function registerPending(): void
    {
        $applied = $_SESSION[self::REGISTERED] ?? null;

        if ($this->signedIn() || !is_array($applied)) {
            $this->redirect($this->signedIn() ? $this->homeFor($this->role()) : '/register');

            return;
        }

        $division = (new GnDivision($this->pdo))->findWithStaff((int) $applied['division_id']);

        $this->render('auth/register-pending', [
            'firstName'     => strtok((string) $applied['name'], ' ') ?: (string) $applied['name'],
            'divisionName'  => (string) ($division['name'] ?? ''),
            'moderatorName' => $division['moderator_name'] ?? null,
        ]);
    }

    /**
     * Step 1: the details are checked in full — formats, the division, and
     * whether the NIC, mobile or email is already registered — before the
     * applicant is asked for documents.
     */
    private function registerDetails(): void
    {
        $validator = new Validator($_POST);
        $validator
            ->required('full_name', 'Full name')
            ->maxLength('full_name', 'Full name', 150)
            ->personName('full_name', 'Full name')
            ->required('nic', 'NIC number')
            ->maxLength('nic', 'NIC number', 20)
            ->required('phone', 'Mobile number')
            ->maxLength('phone', 'Mobile number', 20)
            ->required('email', 'Email address')
            ->maxLength('email', 'Email address', 150)
            ->required('address', 'Address')
            ->maxLength('address', 'Address', 255)
            ->words('address', 'Address')
            ->required('gn_division_id', 'GN division')
            ->integer('gn_division_id', 'GN division', 1);

        $input = $this->detailInput($validator->values());

        // Both sets at once, so every problem shows on the first attempt; a
        // field the Validator refused keeps the Validator's message.
        $errors = $validator->errors() + $this->registrations()->detailErrors($input);

        if ($errors !== []) {
            $this->renderRegister(1, $errors, $input);

            return;
        }

        $_SESSION[self::REGISTER_DRAFT] = $input;
        $this->redirect('/register?step=2');
    }

    /**
     * Step 2: the documents and the password complete the application. The
     * service checks the step-1 details again, since an NIC or mobile number
     * can be registered by someone else in between.
     */
    private function registerDocuments(): void
    {
        $draft = $this->registerDraft();

        if ($draft === null) {
            $this->redirect('/register');

            return;
        }

        try {
            $this->registrations()->register(
                $draft,
                $this->postedPassword('password'),
                $this->postedPassword('password_confirmation'),
                [
                    'nic_photo'     => uploaded_files('nic_photo'),
                    'address_proof' => uploaded_files('address_proof'),
                ]
            );
        } catch (ValidationException $exception) {
            $errors      = $exception->errors();
            $backToStep1 = isset($errors['form'])
                || array_intersect_key($errors, array_flip(RegistrationService::DETAIL_FIELDS)) !== [];

            $this->renderRegister($backToStep1 ? 1 : 2, $errors, $draft);

            return;
        }

        unset($_SESSION[self::REGISTER_DRAFT]);
        $_SESSION[self::REGISTERED] = ['name' => $draft['full_name'], 'division_id' => (int) $draft['gn_division_id']];

        $this->redirect('/register/pending');
    }

    // ── Forgotten password ──────────────────────────────────────────────────

    /**
     * GET /forgot-password — the sign-in page with the reset-link dialog open,
     * so the address also works as a direct link and without JavaScript.
     */
    public function forgotForm(): void
    {
        $this->renderLogin(['dialog' => 'forgot']);
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
            $this->renderLogin([
                'dialog'       => 'forgot',
                'forgotErrors' => $validator->errors(),
                'forgotEmail'  => $validator->value('email'),
            ]);

            return;
        }

        try {
            $request = $this->passwordResets()->requestLink($validator->value('email'));
        } catch (ValidationException $exception) {
            http_response_code(422);
            $this->renderLogin([
                'dialog'       => 'forgot',
                'forgotErrors' => $exception->errors(),
                'forgotEmail'  => $validator->value('email'),
            ]);

            return;
        }

        $devLink = null;
        $url     = $this->resetUrl($request['token']);
        $mailer  = Mailer::fromConfig();
        $sent    = $url !== null && $mailer->send(
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

        $this->renderLogin([
            'dialog'      => 'forgot',
            'forgotSent'  => true,
            'forgotEmail' => $request['email'],
            'devLink'     => $devLink,
        ]);
    }

    /**
     * GET /reset-password?token=… — the sign-in page with the new-password
     * dialog open.
     */
    public function resetForm(): void
    {
        $token = (string) ($_GET['token'] ?? '');

        $this->renderLogin([
            'dialog'     => 'reset',
            'resetToken' => $token,
            'resetValid' => $this->passwordResets()->linkIsValid($token),
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
            $this->renderLogin([
                'dialog'      => 'reset',
                'resetErrors' => $exception->errors(),
                'resetToken'  => $token,
                'resetValid'  => $this->passwordResets()->linkIsValid($token),
            ]);

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
     * @param array<string, mixed>  $values
     */
    private function renderAuthPage(string $view, array $errors, array $values = []): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $this->render('auth/' . $view, ['errors' => $errors] + $values);
    }

    /**
     * The sign-in page, optionally with the forgot-password or new-password
     * dialog open.
     *
     * @param array<string, mixed> $data errors, identifier, dialog and the dialogs' own values
     */
    private function renderLogin(array $data = []): void
    {
        foreach (['errors', 'forgotErrors', 'resetErrors'] as $key) {
            if (($data[$key] ?? []) !== []) {
                http_response_code(422);
            }
        }

        $this->render('auth/login', $data);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $input values to show back
     */
    private function renderRegister(int $step, array $errors, array $input): void
    {
        $division = null;

        if ($step === 2) {
            $division = (new GnDivision($this->pdo))->find((int) ($input['gn_division_id'] ?? 0));
        }

        $this->renderAuthPage('register', $errors, [
            'step'         => $step,
            'input'        => $input,
            'divisions'    => (new GnDivision($this->pdo))->activeNames(),
            'divisionName' => (string) ($division['name'] ?? ''),
        ]);
    }

    /**
     * The step-1 fields from a request, with every key present.
     *
     * @param array<string, string> $values
     *
     * @return array{full_name:string, nic:string, phone:string, email:string, address:string, gn_division_id:string}
     */
    private function detailInput(array $values): array
    {
        $input = [];

        foreach (RegistrationService::DETAIL_FIELDS as $field) {
            $input[$field] = (string) ($values[$field] ?? '');
        }

        return $input;
    }

    /**
     * @return array{full_name:string, nic:string, phone:string, email:string, address:string, gn_division_id:string}|null
     */
    private function registerDraft(): ?array
    {
        $draft = $_SESSION[self::REGISTER_DRAFT] ?? null;

        return is_array($draft) ? $this->detailInput($draft) : null;
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
}
