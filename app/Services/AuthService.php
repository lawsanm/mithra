<?php

declare(strict_types=1);

/**
 * Sign-in rules: who a typed identifier belongs to, whether the password
 * matches, and whether that account is allowed in yet.
 *
 * Everything here is a business decision, so none of it sits in the controller
 * (Rules/CONVENTIONS.md §6). The service reads no superglobals and starts no
 * session — it is handed two strings and returns the account row, or refuses.
 */
final class AuthService
{
    /**
     * A real bcrypt hash of a random string, matched to the cost the seeded
     * accounts use. An unknown identifier is still verified against it so a
     * wrong identifier and a wrong password take the same time to refuse (§7).
     */
    private const ABSENT_ACCOUNT_HASH = '$2y$10$S.UjDRRMER1LfC7iFEErY.DmuxwBXu4TBl.TEwVm9xwqCdaZ6T6PO';

    /** Sri Lankan numbers are matched on their final nine digits. */
    private const PHONE_DIGITS = 9;

    private User $users;

    public function __construct(User $users)
    {
        $this->users = $users;
    }

    /**
     * Verify one login attempt.
     *
     * @param string $identifier email address or mobile number, as typed
     * @param string $password   as typed — never trimmed, never logged
     *
     * @throws ValidationException when the pair does not match an account that
     *                             may sign in. The message is keyed to 'form'
     *                             because it belongs to the attempt, not to one
     *                             field.
     *
     * @return array<string, mixed> id, full_name, status and role_code
     */
    public function authenticate(string $identifier, string $password): array
    {
        $account = $this->lookup($identifier);

        if ($account === null || !password_verify($password, (string) $account['password_hash'])) {
            // Same refusal either way: which half was wrong is not the
            // attacker's business, and neither is whether the account exists.
            password_verify($password, self::ABSENT_ACCOUNT_HASH);

            throw ValidationException::field(
                'form',
                'That email or mobile number and password do not match an account.'
            );
        }

        // The password has now proved this is the account holder, so naming the
        // real obstacle tells them something they are entitled to know.
        $refusal = $this->refusalFor((string) $account['status']);

        if ($refusal !== null) {
            throw ValidationException::field('form', $refusal);
        }

        unset($account['password_hash']);

        return $account;
    }

    /**
     * An identifier holding '@' is an email address; anything else is treated
     * as a mobile number and reduced to digits.
     *
     * @return array<string, mixed>|null
     */
    private function lookup(string $identifier): ?array
    {
        if (str_contains($identifier, '@')) {
            return $this->users->findForLoginByEmail($identifier);
        }

        $digits = (string) preg_replace('/\D/', '', $identifier);

        if (strlen($digits) < self::PHONE_DIGITS) {
            return null;
        }

        return $this->users->findForLoginByPhone(substr($digits, -self::PHONE_DIGITS));
    }

    /**
     * Why this account may not sign in, or null when it may. Fail closed: an
     * unrecognised status is refused rather than waved through (§8).
     */
    private function refusalFor(string $status): ?string
    {
        return match ($status) {
            'active'    => null,
            'pending'   => 'Your division moderator has not verified this account yet. '
                         . 'You can sign in as soon as the review finishes.',
            'suspended' => 'This account is suspended. Your division moderator can tell you what happens next.',
            default     => 'This account has been closed.',
        };
    }
}
