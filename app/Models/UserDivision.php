<?php

declare(strict_types=1);

/**
 * user_divisions — which community a member belongs to, and whether their
 * moderator has verified it yet.
 *
 * A registration writes the 'home' row here in the same transaction as the
 * `users` row; the moderator's verification queue reads and decides it. A
 * 'temporary' row is written by CommunityService (Plan §6.5) and decided in
 * the same queue.
 */
final class UserDivision extends BaseModel
{
    protected string $table = 'user_divisions';
    protected string $columns = 'id, user_id, gn_division_id, membership_type, verified_by, verified_at, status, created_at';

    /**
     * The home membership a new registration applies for, carrying its proof
     * of address. Pending until a moderator decides it (Plan §18.1).
     */
    public function createHome(int $userId, int $divisionId, string $proofType, string $proofPath): int
    {
        return $this->insert(
            "INSERT INTO user_divisions (user_id, gn_division_id, membership_type, proof_type, proof_file_path, status)
             VALUES (:user_id, :division_id, 'home', :proof_type, :proof_path, 'pending')",
            ['user_id' => $userId, 'division_id' => $divisionId, 'proof_type' => $proofType, 'proof_path' => $proofPath]
        );
    }

    /**
     * One division's verification queue, oldest first so the longest wait is
     * reviewed first: home applications, temporary-community applications and
     * extension requests (Plan §6.5).
     *
     * @param string $status '' for every state, 'temporary' for temporary
     *                       applications and extensions still waiting,
     *                       'pending' for anything waiting, or one status
     *
     * @return list<array<string, mixed>>
     */
    public function queueForDivision(int $divisionId, string $status): array
    {
        $params = ['division' => $divisionId];
        $filter = '';

        if ($status === 'temporary') {
            $filter = " AND ud.membership_type = 'temporary'
                        AND (ud.status = 'pending' OR ud.renewal_requested_at IS NOT NULL)";
        } elseif ($status === 'pending') {
            $filter = " AND (ud.status = 'pending' OR ud.renewal_requested_at IS NOT NULL)";
        } elseif ($status !== '') {
            $filter = ' AND ud.status = :status';
            $params['status'] = $status;
        }

        return $this->select(
            'SELECT ud.id, ud.status, ud.membership_type, ud.renewal_requested_at, ud.created_at, ud.verified_at,
                    u.full_name, u.nic, u.email, u.phone, u.address,
                    d.name AS division_name
               FROM user_divisions ud
               JOIN users u        ON u.id = ud.user_id
               JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.gn_division_id = :division' . $filter . '
              ORDER BY ud.created_at ASC
              LIMIT 100',
            $params
        );
    }

    /** Applications and extension requests waiting for this division's moderator. */
    public function countPendingForDivision(int $divisionId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM user_divisions
              WHERE gn_division_id = :division
                AND (status = 'pending' OR renewal_requested_at IS NOT NULL)",
            ['division' => $divisionId]
        );
    }

    /**
     * One application, with everything the reviewer and the ownership check
     * need: who applied, which division, and who moderates it.
     *
     * @return array<string, mixed>|null
     */
    public function findForReview(int $id): ?array
    {
        return $this->selectOne(
            'SELECT ud.id, ud.user_id, ud.gn_division_id, ud.status, ud.created_at, ud.verified_at,
                    ud.membership_type, ud.expires_at, ud.decision_reason,
                    ud.renewal_proof_path, ud.renewal_requested_at,
                    ud.proof_type, ud.proof_file_path,
                    u.full_name, u.nic, u.nic_photo_path, u.phone, u.email, u.address, u.status AS user_status,
                    d.name AS division_name, d.moderator_id,
                    v.full_name AS decided_by_name
               FROM user_divisions ud
               JOIN users u        ON u.id = ud.user_id
               JOIN gn_divisions d ON d.id = ud.gn_division_id
          LEFT JOIN users v        ON v.id = ud.verified_by
              WHERE ud.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Record a moderator's decision. The current status is part of the WHERE
     * clause, so two moderators racing on one application cannot both win and
     * a decided application can never be decided again.
     *
     * @return bool whether this call was the one that changed the row
     */
    public function decide(int $id, int $moderatorId, string $status, ?string $expiresAt = null, ?string $reason = null): bool
    {
        return $this->execute(
            "UPDATE user_divisions
                SET status = :status, verified_by = :moderator, verified_at = NOW(),
                    expires_at = :expires, decision_reason = :reason
              WHERE id = :id AND status = 'pending'",
            ['status' => $status, 'moderator' => $moderatorId, 'expires' => $expiresAt, 'reason' => $reason, 'id' => $id]
        ) === 1;
    }

    /**
     * Who may look at an identity document: the moderators of the divisions it
     * was submitted to (Plan §25.3). Empty when the path is no one's document.
     *
     * @return list<int> moderator user ids
     */
    public function documentReviewers(string $path): array
    {
        return $this->selectIds(
            'SELECT DISTINCT d.moderator_id
               FROM user_divisions ud
               JOIN users u        ON u.id = ud.user_id
               JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE (ud.proof_file_path = :proof OR ud.renewal_proof_path = :renewal OR u.nic_photo_path = :nic)
                AND d.moderator_id IS NOT NULL',
            ['proof' => $path, 'renewal' => $path, 'nic' => $path]
        );
    }

    /** Whether this path is an identity document at all. */
    public function isDocument(string $path): bool
    {
        return (int) $this->selectValue(
            'SELECT (SELECT COUNT(*) FROM user_divisions WHERE proof_file_path = :proof OR renewal_proof_path = :renewal)
                  + (SELECT COUNT(*) FROM users WHERE nic_photo_path = :nic)',
            ['proof' => $path, 'renewal' => $path, 'nic' => $path]
        ) > 0;
    }

    /** End every membership of a closing account (Plan §17). */
    public function deactivateAllFor(int $userId): void
    {
        $this->execute(
            "UPDATE user_divisions SET status = 'deactivated'
              WHERE user_id = :user AND status IN ('pending','active','paused')",
            ['user' => $userId]
        );
    }

    /** Replace the verified address proof after an approved address change. */
    public function replaceHomeProof(int $userId, string $proofPath): void
    {
        $this->execute(
            "UPDATE user_divisions SET proof_type = 'address', proof_file_path = :proof
              WHERE user_id = :user AND membership_type = 'home'",
            ['proof' => $proofPath, 'user' => $userId]
        );
    }

    /**
     * The divisions a member may act in right now: the home division and any
     * active temporary one. Browse, borrowing, donation requests and gifts all
     * check it — "a borrower must be an active member of that division" (§6.5).
     *
     * @return list<int>
     */
    public function activeDivisionIds(int $userId): array
    {
        return $this->selectIds(
            "SELECT ud.gn_division_id
               FROM user_divisions ud
               JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.user_id = :user AND ud.status = 'active' AND d.status = 'active'
              ORDER BY ud.membership_type = 'home' DESC, ud.id",
            ['user' => $userId]
        );
    }

    // ── Temporary community (Plan §6.5) ─────────────────────────────────────

    /**
     * The member's open temporary community, for the profile's communities panel.
     *
     * @return array<string, mixed>|null
     */
    public function temporaryForUser(int $userId): ?array
    {
        return $this->selectOne(
            "SELECT d.name, ud.created_at, ud.expires_at, ud.status
               FROM user_divisions ud JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.user_id = :user AND ud.membership_type = 'temporary'
                AND ud.status IN ('active','pending','paused')
              ORDER BY ud.created_at DESC LIMIT 1",
            ['user' => $userId]
        );
    }

    /**
     * The member's latest temporary-community row in any state, with what the
     * community page shows about it.
     *
     * @return array<string, mixed>|null
     */
    public function latestTemporary(int $userId): ?array
    {
        return $this->selectOne(
            "SELECT ud.id, ud.gn_division_id, ud.status, ud.created_at, ud.verified_at, ud.expires_at,
                    ud.decision_reason, ud.renewal_requested_at, ud.proof_type,
                    d.name AS division_name
               FROM user_divisions ud
               JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.user_id = :user AND ud.membership_type = 'temporary'
              ORDER BY COALESCE(ud.verified_at, ud.created_at) DESC, ud.id DESC
              LIMIT 1",
            ['user' => $userId]
        );
    }

    /** Temporary memberships still open (pending, active or in grace) — at most one is allowed (§19). */
    public function countOpenTemporary(int $userId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM user_divisions
              WHERE user_id = :user AND membership_type = 'temporary'
                AND status IN ('pending','active','paused')",
            ['user' => $userId]
        );
    }

    /**
     * The row tying this member to this division, if any. uk_ud_user_division
     * allows only one, so a new application reuses it.
     *
     * @return array<string, mixed>|null
     */
    public function findFor(int $userId, int $divisionId): ?array
    {
        return $this->selectOne(
            'SELECT id, membership_type, status FROM user_divisions
              WHERE user_id = :user AND gn_division_id = :division',
            ['user' => $userId, 'division' => $divisionId]
        );
    }

    public function createTemporary(int $userId, int $divisionId, string $proofType, string $proofPath): int
    {
        return $this->insert(
            "INSERT INTO user_divisions (user_id, gn_division_id, membership_type, proof_type, proof_file_path, status)
             VALUES (:user, :division, 'temporary', :type, :path, 'pending')",
            ['user' => $userId, 'division' => $divisionId, 'type' => $proofType, 'path' => $proofPath]
        );
    }

    /**
     * Apply again on an old row that was rejected, left or lapsed. The status
     * guard means a live membership is never overwritten.
     */
    public function reapplyTemporary(int $id, string $proofType, string $proofPath): bool
    {
        return $this->execute(
            "UPDATE user_divisions
                SET membership_type = 'temporary', status = 'pending', proof_type = :type,
                    proof_file_path = :path, verified_by = NULL, verified_at = NULL, expires_at = NULL,
                    decision_reason = NULL, renewal_proof_path = NULL, renewal_requested_at = NULL,
                    expiry_reminded_at = NULL, created_at = NOW()
              WHERE id = :id AND status IN ('rejected','expired','deactivated')",
            ['type' => $proofType, 'path' => $proofPath, 'id' => $id]
        ) === 1;
    }

    /**
     * Move one temporary row to another state, guarded by the states it may
     * leave from, so a stale request changes nothing.
     *
     * @param list<string> $from
     */
    public function moveTemporary(int $id, array $from, string $to): bool
    {
        $params = ['to' => $to, 'id' => $id];

        return $this->execute(
            "UPDATE user_divisions SET status = :to, renewal_requested_at = NULL
              WHERE id = :id AND membership_type = 'temporary' AND status IN " . self::inList('from', $from, $params),
            $params
        ) === 1;
    }

    public function requestRenewal(int $id, string $proofPath): bool
    {
        return $this->execute(
            "UPDATE user_divisions SET renewal_proof_path = :path, renewal_requested_at = NOW(), decision_reason = NULL
              WHERE id = :id AND membership_type = 'temporary' AND status IN ('active','paused')",
            ['path' => $proofPath, 'id' => $id]
        ) === 1;
    }

    /** The moderator accepts fresh proof: a new expiry, active again. */
    public function approveRenewal(int $id, int $moderatorId, string $expiresAt): bool
    {
        return $this->execute(
            "UPDATE user_divisions
                SET status = 'active', expires_at = :expires, verified_by = :moderator, verified_at = NOW(),
                    renewal_requested_at = NULL, expiry_reminded_at = NULL, decision_reason = NULL
              WHERE id = :id AND renewal_requested_at IS NOT NULL AND status IN ('active','paused')",
            ['expires' => $expiresAt, 'moderator' => $moderatorId, 'id' => $id]
        ) === 1;
    }

    public function rejectRenewal(int $id, int $moderatorId, string $reason): bool
    {
        return $this->execute(
            'UPDATE user_divisions
                SET renewal_requested_at = NULL, decision_reason = :reason, verified_by = :moderator
              WHERE id = :id AND renewal_requested_at IS NOT NULL',
            ['reason' => $reason, 'moderator' => $moderatorId, 'id' => $id]
        ) === 1;
    }

    /**
     * Promotion swaps the two rows (Plan §6.5): the temporary one becomes the
     * home with no expiry, and the old home is kept as a deactivated temporary
     * row, so exactly one home row exists and the member can rejoin later.
     */
    public function swapHome(int $userId, int $temporaryId): bool
    {
        $demoted = $this->execute(
            "UPDATE user_divisions SET membership_type = 'temporary', status = 'deactivated', expires_at = NULL
              WHERE user_id = :user AND membership_type = 'home' AND status = 'active'",
            ['user' => $userId]
        );

        return $demoted === 1 && $this->execute(
            "UPDATE user_divisions SET membership_type = 'home', expires_at = NULL, renewal_requested_at = NULL
              WHERE id = :id AND user_id = :user AND membership_type = 'temporary' AND status = 'active'",
            ['id' => $temporaryId, 'user' => $userId]
        ) === 1;
    }

    /**
     * The active temporary community, if any — the one Browse can switch to.
     *
     * @return array<string, mixed>|null
     */
    public function activeTemporary(int $userId): ?array
    {
        return $this->selectOne(
            "SELECT ud.id, ud.gn_division_id, d.name AS division_name
               FROM user_divisions ud JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.user_id = :user AND ud.membership_type = 'temporary' AND ud.status = 'active'
                AND d.status = 'active'
              LIMIT 1",
            ['user' => $userId]
        );
    }

    // ── Expiry job (scripts/expire_temporary_memberships.php) ───────────────

    /**
     * Active memberships expiring within the reminder window that have not
     * been reminded yet.
     *
     * @return list<array<string, mixed>>
     */
    public function dueForReminder(int $days): array
    {
        return $this->select(
            "SELECT ud.id, ud.user_id, ud.expires_at, d.name AS division_name
               FROM user_divisions ud JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.membership_type = 'temporary' AND ud.status = 'active'
                AND ud.expiry_reminded_at IS NULL
                AND ud.expires_at <= NOW() + INTERVAL :days DAY",
            ['days' => $days]
        );
    }

    public function markReminded(int $id): void
    {
        $this->execute('UPDATE user_divisions SET expiry_reminded_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    /**
     * Temporary memberships in one state whose expiry passed more than
     * $graceDays ago (0 for "has passed").
     *
     * @return list<array<string, mixed>>
     */
    public function lapsed(string $status, int $graceDays): array
    {
        return $this->select(
            "SELECT ud.id, ud.user_id, ud.gn_division_id, d.name AS division_name
               FROM user_divisions ud JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.membership_type = 'temporary' AND ud.status = :status
                AND ud.expires_at < NOW() - INTERVAL :grace DAY",
            ['status' => $status, 'grace' => $graceDays]
        );
    }
}
