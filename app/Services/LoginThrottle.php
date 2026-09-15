<?php

declare(strict_types=1);

/**
 * Slows down password and reset-code guessing (Plan §21.1, §26 "hand-rolled
 * security holes").
 *
 * Two limits, both over the last WINDOW_MINUTES:
 *   - one identifier (an email, a mobile number): MAX_PER_IDENTIFIER failures
 *     since its last success, so one account cannot be brute-forced;
 *   - one IP address: MAX_PER_IP failures across every identifier, so one
 *     machine cannot walk through the member list.
 *
 * Identifiers are normalised before hashing, so "077 123 4567" and
 * "+94771234567" count as the same account.
 */
final class LoginThrottle
{
    public const WINDOW_MINUTES = 15;

    public const MAX_PER_IDENTIFIER = 5;

    public const MAX_PER_IP = 30;

    public function __construct(private LoginAttempt $attempts)
    {
    }

    /** Whether this attempt must be refused without checking the password. */
    public function isBlocked(string $scope, string $identifier, string $ip): bool
    {
        $hash = self::identifierHash($scope, $identifier);

        return $this->attempts->failuresForIdentifier($hash, self::WINDOW_MINUTES) >= self::MAX_PER_IDENTIFIER
            || $this->attempts->failuresForIp($ip, self::WINDOW_MINUTES) >= self::MAX_PER_IP;
    }

    public function record(string $scope, string $identifier, string $ip, bool $succeeded): void
    {
        $this->attempts->record(self::identifierHash($scope, $identifier), $ip, $succeeded);
    }

    public static function refusal(): string
    {
        return sprintf(
            'Too many unsuccessful attempts. Wait %d minutes, then try again.',
            self::WINDOW_MINUTES
        );
    }

    /**
     * A stable hash of what was typed: email lower-cased, mobile reduced to
     * its last nine digits. The scope keeps sign-in and reset-code attempts
     * apart.
     */
    public static function identifierHash(string $scope, string $identifier): string
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            $key = mb_strtolower($identifier);
        } else {
            $digits = (string) preg_replace('/\D/', '', $identifier);
            $key    = strlen($digits) > 9 ? substr($digits, -9) : $digits;
        }

        return hash('sha256', $scope . '|' . $key);
    }
}
