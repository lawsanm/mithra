<?php

declare(strict_types=1);

/**
 * Getting back into an account, and changing a password from inside one
 * (Plan §20.1 module 1.1).
 *
 * The way back in is an emailed link, valid for LINK_TTL_MINUTES — every
 * account has an email address.
 *
 * Link tokens are random, single-use, stored only as SHA-256 hashes, and a new
 * one or a successful reset retires every older one. Every reset ends the
 * account's other sessions (the password_changed_at stamp moves).
 */
final class PasswordResetService
{
    public const LINK_TTL_MINUTES = 60;

    /** At most this many reset emails per account per hour. */
    private const MAX_LINKS_PER_HOUR = 3;

    public function __construct(
        private PDO $pdo,
        private User $users,
        private PasswordReset $resets
    ) {
    }

    // ── Emailed link ────────────────────────────────────────────────────────

    /**
     * Create a link token for the active account using this email address.
     *
     * An address with no active account is refused with a message, so the
     * member knows to check the spelling instead of waiting for an email.
     *
     * @throws ValidationException
     *
     * @return array{token: string, name: string, email: string}
     */
    public function requestLink(string $email): array
    {
        $email   = trim($email);
        $account = $email === '' ? null : $this->users->findActiveByEmail($email);

        if ($account === null) {
            throw ValidationException::field('email', 'No active Mithra account uses this email address. Check the spelling.');
        }

        $userId = (int) $account['id'];

        if ($this->resets->countRecent($userId, 'email', 60) >= self::MAX_LINKS_PER_HOUR) {
            throw ValidationException::field('email', sprintf(
                'A reset link was already sent %d times in the last hour. Use the newest one, or try again later.',
                self::MAX_LINKS_PER_HOUR
            ));
        }

        $token = bin2hex(random_bytes(32));

        $this->issue($userId, $token);

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

        Database::transaction($this->pdo, function () use ($userId, $password): void {
            $this->users->updatePassword($userId, PasswordPolicy::hash($password));
            $this->resets->revokeFor($userId);
        });

        return $this->users->sessionState($userId)['password_changed_at'] ?? null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public static function looksLikeToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private function issue(int $userId, string $secret): void
    {
        Database::transaction($this->pdo, function () use ($userId, $secret): void {
            // Only the newest link works: an older one is retired.
            $this->resets->revokeFor($userId);
            $this->resets->create([
                'user_id'     => $userId,
                'channel'     => 'email',
                'secret_hash' => self::hash($secret),
                'ttl_minutes' => self::LINK_TTL_MINUTES,
            ]);
        });
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

        Database::transaction($this->pdo, function () use ($reset, $password): void {
            if (!$this->resets->markUsed((int) $reset['id'])) {
                throw ValidationException::field('form', 'This reset was already used. Ask for a new one.');
            }

            $this->users->updatePassword((int) $reset['user_id'], PasswordPolicy::hash($password));
            $this->resets->revokeFor((int) $reset['user_id']);
        });
    }
}
