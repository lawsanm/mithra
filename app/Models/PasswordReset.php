<?php

declare(strict_types=1);

/**
 * password_resets — one-time secrets for choosing a new password.
 *
 * Only the SHA-256 hash of a token or code is ever stored or compared, so the
 * table is useless to someone who reads it.
 */
final class PasswordReset extends BaseModel
{
    protected string $table = 'password_resets';
    protected string $columns = 'id, user_id, channel, expires_at, used_at, created_at';

    /**
     * @param array{user_id:int, channel:string, secret_hash:string, ttl_minutes:int} $data
     */
    public function create(array $data): int
    {
        return $this->insert(
            'INSERT INTO password_resets (user_id, channel, secret_hash, expires_at)
             VALUES (:user_id, :channel, :secret_hash, NOW() + INTERVAL :ttl_minutes MINUTE)',
            $data
        );
    }

    /**
     * An unused, unexpired secret of this kind, with the account it opens.
     *
     * @return array<string, mixed>|null
     */
    public function findUsable(string $secretHash, string $channel): ?array
    {
        return $this->selectOne(
            'SELECT pr.id, pr.user_id, u.full_name, u.status
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id
              WHERE pr.secret_hash = :hash
                AND pr.channel = :channel
                AND pr.used_at IS NULL
                AND pr.expires_at > NOW()',
            ['hash' => $secretHash, 'channel' => $channel]
        );
    }

    /**
     * Spend one secret. The guard makes a second, concurrent use a no-op.
     *
     * @return bool false when it was already used
     */
    public function markUsed(int $id): bool
    {
        return $this->execute('UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL', ['id' => $id]) === 1;
    }

    /** Retire every outstanding secret for this account — a newer one, or a reset, supersedes them. */
    public function revokeFor(int $userId): void
    {
        $this->execute('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user AND used_at IS NULL', ['user' => $userId]);
    }

    /** How many secrets this account was sent in the last $minutes, to stop a mailbox being flooded. */
    public function countRecent(int $userId, string $channel, int $minutes): int
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM password_resets
              WHERE user_id = :user AND channel = :channel AND created_at >= NOW() - INTERVAL :minutes MINUTE',
            ['user' => $userId, 'channel' => $channel, 'minutes' => $minutes]
        );
    }
}
