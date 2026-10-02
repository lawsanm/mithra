<?php

declare(strict_types=1);

/**
 * What counts as an acceptable password — one rule set for sign-up, password
 * change and password reset, so the three can never drift apart.
 *
 * Length beats composition rules, so length is all we ask for. bcrypt reads 72
 * bytes and silently ignores the rest, so a longer password is refused rather
 * than accepted and quietly truncated.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public const MAX_BYTES = 72;

    /**
     * @param string $confirmField the form field the second box is posted as
     *
     * @return array<string, string> field => message; empty when acceptable
     */
    public static function errors(
        string $password,
        string $confirmation,
        string $field = 'password',
        string $confirmField = 'password_confirmation'
    ): array {
        if ($password === '') {
            return [$field => 'Password is required.'];
        }

        if (str_contains($password, "\0")) {
            return [$field => 'Password must not contain null characters.'];
        }

        if (mb_strlen($password) < self::MIN_LENGTH) {
            return [$field => sprintf('Choose a password of at least %d characters.', self::MIN_LENGTH)];
        }

        if (strlen($password) > self::MAX_BYTES) {
            return [$field => sprintf('Choose a password of %d characters or fewer.', self::MAX_BYTES)];
        }

        if ($password !== $confirmation) {
            return [$confirmField => 'The two passwords do not match.'];
        }

        return [];
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
