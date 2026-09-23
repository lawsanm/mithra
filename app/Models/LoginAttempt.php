<?php

declare(strict_types=1);

/**
 * login_attempts — recent sign-in and reset attempts, for throttling.
 *
 * The identifier is stored only as a hash: the table must not become a list of
 * who has accounts.
 */
final class LoginAttempt extends BaseModel
{
    protected string $table = 'login_attempts';
    protected string $columns = 'id, identifier_hash, ip_address, succeeded, attempted_at';

    public function record(string $identifierHash, string $ip, bool $succeeded): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (identifier_hash, ip_address, succeeded)
             VALUES (:hash, :ip, :succeeded)'
        );

        $statement->execute(['hash' => $identifierHash, 'ip' => $ip, 'succeeded' => $succeeded ? 1 : 0]);
    }

    /**
     * Failures for this identifier since its last success, within the last
     * $minutes. Times are the database's own, so PHP and MySQL time zones can
     * never disagree about the window.
     */
    public function failuresForIdentifier(string $identifierHash, int $minutes): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*)
               FROM login_attempts
              WHERE identifier_hash = :hash
                AND succeeded = 0
                AND attempted_at >= NOW() - INTERVAL :minutes MINUTE
                AND attempted_at > COALESCE(
                      (SELECT MAX(s.attempted_at) FROM login_attempts s
                        WHERE s.identifier_hash = :hash2 AND s.succeeded = 1), \'1970-01-01\')',
            ['hash' => $identifierHash, 'minutes' => $minutes, 'hash2' => $identifierHash]
        );
    }

    /** Failures from one address across every identifier, within the last $minutes. */
    public function failuresForIp(string $ip, int $minutes): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = :ip AND succeeded = 0 AND attempted_at >= NOW() - INTERVAL :minutes MINUTE',
            ['ip' => $ip, 'minutes' => $minutes]
        );
    }
}
