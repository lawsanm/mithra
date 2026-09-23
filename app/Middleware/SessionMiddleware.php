<?php

declare(strict_types=1);

/**
 * Is the signed-in session still one the account would accept? (Plan §21.1,
 * Month 11 "session audit").
 *
 * A session ends when:
 *   - the account is no longer active (suspended, closed) or no longer exists;
 *   - its role changed since sign-in, so RBAC would be judging a stale role;
 *   - its password changed since this session signed in — a reset, or a
 *     change made from another device, signs everyone else out;
 *   - it has been idle for longer than IDLE_SECONDS.
 *
 * The password check compares the stamp the session saw at sign-in with the
 * stamp stored now, so PHP and MySQL clocks never need to agree. Like the other
 * middleware it reads no superglobals: the Router passes the values in.
 */
final class SessionMiddleware
{
    public const IDLE_SECONDS = 2 * 60 * 60;

    /**
     * @param array{status:string, password_changed_at:?string, role_code:string}|null $account
     *        the account as stored now, or null when it no longer exists
     * @param string|null $sessionRole     the role the session was granted
     * @param string|null $sessionStamp    password_changed_at as seen at sign-in
     * @param int|null    $lastSeen        unix time of the session's previous request
     *
     * @return string|null why the session must end, or null to carry on
     */
    public function handle(?array $account, ?string $sessionRole, ?string $sessionStamp, ?int $lastSeen, int $now): ?string
    {
        if ($account === null || $account['status'] !== 'active') {
            return 'This account is no longer active, so you have been signed out.';
        }

        if ($sessionRole !== $account['role_code']) {
            return 'Your account’s role has changed. Sign in again to continue.';
        }

        if (($account['password_changed_at'] ?? null) !== $sessionStamp) {
            return 'Your password was changed, so you have been signed out. Sign in with the new password.';
        }

        if ($lastSeen !== null && $now - $lastSeen > self::IDLE_SECONDS) {
            return 'You were signed out after two hours without activity.';
        }

        return null;
    }
}
