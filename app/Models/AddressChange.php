<?php

declare(strict_types=1);

/**
 * address_changes — a member's new home address, waiting with its proof for
 * the division moderator (Plan §6.5, §18.1).
 */
final class AddressChange extends BaseModel
{
    protected string $table = 'address_changes';
    protected string $columns = 'id, user_id, gn_division_id, new_address, status, decided_by, decided_at, reason, created_at';

    public function create(int $userId, int $divisionId, string $newAddress, string $proofPath): int
    {
        return $this->insert(
            'INSERT INTO address_changes (user_id, gn_division_id, new_address, proof_file_path)
             VALUES (:user, :division, :address, :proof)',
            ['user' => $userId, 'division' => $divisionId, 'address' => $newAddress, 'proof' => $proofPath]
        );
    }

    /**
     * The member's latest request, whatever its state.
     *
     * @return array<string, mixed>|null
     */
    public function latestFor(int $userId): ?array
    {
        return $this->selectOne(
            'SELECT id, new_address, proof_file_path, status, reason, created_at, decided_at
               FROM address_changes
              WHERE user_id = :user
              ORDER BY id DESC
              LIMIT 1',
            ['user' => $userId]
        );
    }

    /** Close any request still open for this member (a newer one replaces it). */
    public function withdrawPendingFor(int $userId): void
    {
        $this->execute(
            "UPDATE address_changes SET status = 'withdrawn', decided_at = NOW() WHERE user_id = :user AND status = 'pending'",
            ['user' => $userId]
        );
    }

    /**
     * Pending requests in one division, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingForDivision(int $divisionId): array
    {
        return $this->select(
            "SELECT ac.id, ac.user_id, ac.new_address, ac.proof_file_path, ac.created_at,
                    u.full_name, u.address AS current_address
               FROM address_changes ac
               JOIN users u ON u.id = ac.user_id
              WHERE ac.gn_division_id = :division AND ac.status = 'pending'
              ORDER BY ac.created_at ASC
              LIMIT 50",
            ['division' => $divisionId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDivision(int $id): ?array
    {
        return $this->selectOne(
            'SELECT ac.id, ac.user_id, ac.gn_division_id, ac.new_address, ac.proof_file_path, ac.status,
                    u.full_name, d.moderator_id
               FROM address_changes ac
               JOIN users u        ON u.id = ac.user_id
               JOIN gn_divisions d ON d.id = ac.gn_division_id
              WHERE ac.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return bool false when it was no longer pending
     */
    public function decide(int $id, string $status, int $moderatorId, ?string $reason): bool
    {
        return $this->execute(
            "UPDATE address_changes
                SET status = :status, decided_by = :moderator, decided_at = NOW(), reason = :reason
              WHERE id = :id AND status = 'pending'",
            ['status' => $status, 'moderator' => $moderatorId, 'reason' => $reason, 'id' => $id]
        ) === 1;
    }

    /**
     * Who may see a proof document: the moderator of the division reviewing it.
     *
     * @return list<int>
     */
    public function proofReviewers(string $path): array
    {
        return $this->selectIds(
            'SELECT DISTINCT d.moderator_id
               FROM address_changes ac
               JOIN gn_divisions d ON d.id = ac.gn_division_id
              WHERE ac.proof_file_path = :path AND d.moderator_id IS NOT NULL',
            ['path' => $path]
        );
    }

    public function isProof(string $path): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM address_changes WHERE proof_file_path = :path',
            ['path' => $path]
        ) > 0;
    }
}
