<?php

declare(strict_types=1);

/**
 * Getting back into an account, and changing a password from inside one
 * (Plan §20.1 module 1.1).
 *
 * Two ways back in, because email is optional at sign-up:
 *   - an emailed link, valid for LINK_TTL_MINUTES, for members with an email;
 *   - a one-time code the division moderator (or the Admin) issues after
 *     checking the person in front of them, valid for CODE_TTL_MINUTES — the
 *     same in-person trust the plan uses for verification (§18.1).
 *
 * Secrets are random, single-use, stored only as SHA-256 hashes, and a new one
 * or a successful reset retires every older one. Redeeming a code is throttled
 * like sign-in, and every reset ends the account's other sessions (the
 * password_changed_at stamp moves).
 */
final class PasswordResetService
{
    public const LINK_TTL_MINUTES = 60;

    public const CODE_TTL_MINUTES = 24 * 60;

    /** At most this many reset emails per account per hour. */
    private const MAX_LINKS_PER_HOUR = 3;

    /** Unambiguous characters: no 0/O or 1/I/L to misread aloud. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 10;

    /** The throttle scope for code redemption. */
    private const SCOPE = 'reset-code';

    public function __construct(
        private PDO $pdo,
        private User $users,
        private PasswordReset $resets,
        private GnDivision $divisions,
        private AuthService $auth,
        private LoginThrottle $throttle
    ) {
    }

    // ── Emailed link ────────────────────────────────────────────────────────

    /**
     * Create a link token for the active account using this email address.
     *
     * Returns null — silently — when there is no such account or it has asked
     * too often: the caller shows the same message either way, so the form
     * cannot be used to find out who has an account.
     *
     * @return array{token: string, name: string, email: string}|null
     */
    public function requestLink(string $email): ?array
    {
        $email   = trim($email);
        $account = $email === '' ? null : $this->users->findActiveByEmail($email);

        if ($account === null) {
            return null;
        }

        $userId = (int) $account['id'];

        if ($this->resets->countRecent($userId, 'email', 60) >= self::MAX_LINKS_PER_HOUR) {
            return null;
        }

        $token = bin2hex(random_bytes(32));

        $this->issue($userId, 'email', $token, null, self::LINK_TTL_MINUTES);

        return ['token' => $token, 'name' => (string) $account['full_name'], 'email' => (string) $account['email']];
    }

    /** Whether a link token is still usable — the form is only shown for one that is. */
    public function linkIsValid(string $token): bool
    {
        return self::looksLikeToken($token) && $this->resets->findUsable(self::hash($token), 'email') !== null;
    }

    /**
     * @throws ValidationException
     */
    public function resetWithLink(string $token, string $password, string $confirmation): void
    {
        $reset = self::looksLikeToken($token) ? $this->resets->findUsable(self::hash($token), 'email') : null;

        if ($reset === null) {
            throw ValidationException::field('form', 'This reset link has expired or was already used. Ask for a new one.');
        }

        $this->complete($reset, $password, $confirmation);
    }

    // ── Code issued in person ───────────────────────────────────────────────

    /**
     * Issue a one-time code for the account a moderator or the Admin has just
     * identified in person, by NIC or mobile number.
     *
     * A moderator may only issue codes for members of their own division, and
     * never for themselves; the Admin may issue one for any account but their
     * own (their own password changes from inside the account).
     *
     * @throws ValidationException|AccessDeniedException
     *
     * @return array{code: string, name: string, expires_hours: int}
     */
    public function issueCode(int $issuerId, string $issuerRole, string $nicOrMobile): array
    {
        $typed = trim($nicOrMobile);
        $nic   = RegistrationService::normaliseNic($typed) ?? '';
        $phone = RegistrationService::normalisePhone($typed);
        $digits = $phone === null ? '' : RegistrationService::phoneDigits($phone);

        if ($nic === '' && $digits === '') {
            throw ValidationException::field('lookup', 'Enter the member’s NIC number or mobile number.');
        }

        $account = $this->users->findForCodeIssue($nic === '' ? '-' : $nic, $digits === '' ? '-' : $digits);

        if ($account === null) {
            throw ValidationException::field('lookup', 'No account matches that NIC or mobile number.');
        }

        $targetId = (int) $account['id'];

        if ($targetId === $issuerId) {
            throw ValidationException::field('lookup', 'Change your own password from your account settings instead.');
        }

        if ($issuerRole === 'moderator') {
            $division = $this->divisions->moderatedBy($issuerId);

            if ($division === null
                || $account['role_code'] !== 'member'
                || (int) ($account['home_division_id'] ?? 0) !== $division) {
                throw new AccessDeniedException('Moderators issue codes only for members of their own division.');
            }
        } elseif ($issuerRole !== 'admin') {
            throw new AccessDeniedException('Only a moderator or the Admin can issue reset codes.');
        }

        if ($account['status'] !== 'active') {
            throw ValidationException::field('lookup', 'That account is not active, so there is nothing to sign in to.');
        }

        $code = self::newCode();

        $this->issue($targetId, 'issued_code', self::normaliseCode($code), $issuerId, self::CODE_TTL_MINUTES);

        return ['code' => $code, 'name' => (string) $account['full_name'], 'expires_hours' => intdiv(self::CODE_TTL_MINUTES, 60)];
    }

    /**
     * Redeem a code against the email or mobile number the member signs in
     * with. Throttled like sign-in, and refused with one message whatever was
     * wrong, so the form reveals nothing.
     *
     * @throws ValidationException
     */
    public function resetWithCode(string $identifier, string $code, string $password, string $confirmation, string $ip): void
    {
        if ($this->throttle->isBlocked(self::SCOPE, $identifier, $ip)) {
            throw ValidationException::field('form', LoginThrottle::refusal());
        }

        $policy = PasswordPolicy::errors($password, $confirmation);
        if ($policy !== []) {
            throw new ValidationException($policy);
        }

        $account = $this->auth->lookup($identifier);
        $reset   = $this->resets->findUsable(self::hash(self::normaliseCode($code)), 'issued_code');

        if ($account === null || $reset === null || (int) $reset['user_id'] !== (int) $account['id']) {
            $this->throttle->record(self::SCOPE, $identifier, $ip, false);

            throw ValidationException::field(
                'form',
                'That code does not match this account, or it has expired. Ask your moderator for a new one.'
            );
        }

        $this->complete($reset, $password, $confirmation);
        $this->throttle->record(self::SCOPE, $identifier, $ip, true);
    }

    // ── Signed in ───────────────────────────────────────────────────────────

    /**
     * Change the password of the signed-in account, which must prove it knows
     * the current one.
     *
     * @throws ValidationException
     *
     * @return string|null the new password_changed_at stamp, so the caller can
     *                     keep the session that made the change
     */
    public function change(int $userId, string $current, string $password, string $confirmation): ?string
    {
        $account = $this->users->findAccount($userId);

        if ($account === null || !password_verify($current, (string) $account['password_hash'])) {
            throw ValidationException::field('current_password', 'That is not your current password.');
        }

        $errors = PasswordPolicy::errors($password, $confirmation);
        if ($errors === [] && password_verify($password, (string) $account['password_hash'])) {
            $errors = ['password' => 'Choose a password different from your current one.'];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->pdo->beginTransaction();

        try {
            $this->users->updatePassword($userId, PasswordPolicy::hash($password));
            $this->resets->revokeFor($userId);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return $this->users->sessionState($userId)['password_changed_at'] ?? null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** A code in the form it is read aloud: ABCDE-FGHJK. */
    public static function newCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $code     = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($code, 0, 5) . '-' . substr($code, 5);
    }

    /** Case, spaces and dashes do not matter when a code is typed back. */
    public static function normaliseCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function looksLikeToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private function issue(int $userId, string $channel, string $secret, ?int $issuerId, int $ttlMinutes): void
    {
        $this->pdo->beginTransaction();

        try {
            // Only the newest secret works: an older link or code is retired.
            $this->resets->revokeFor($userId);
            $this->resets->create([
                'user_id'     => $userId,
                'channel'     => $channel,
                'secret_hash' => self::hash($secret),
                'issued_by'   => $issuerId,
                'ttl_minutes' => $ttlMinutes,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $reset a usable password_resets row
     *
     * @throws ValidationException
     */
    private function complete(array $reset, string $password, string $confirmation): void
    {
        $errors = PasswordPolicy::errors($password, $confirmation);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($reset['status'] !== 'active') {
            throw ValidationException::field('form', 'This account is not active, so its password cannot be reset.');
        }

        $this->pdo->beginTransaction();

        try {
            if (!$this->resets->markUsed((int) $reset['id'])) {
                throw ValidationException::field('form', 'This reset was already used. Ask for a new one.');
            }

            $this->users->updatePassword((int) $reset['user_id'], PasswordPolicy::hash($password));
            $this->resets->revokeFor((int) $reset['user_id']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}
