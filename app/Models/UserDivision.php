<?php

declare(strict_types=1);

/**
 * user_divisions — which community a member belongs to, and whether their
 * moderator has verified it yet.
 *
 * A registration writes the 'home' row here in the same transaction as the
 * `users` row; the moderator's verification queue reads and decides it. The
 * 'temporary' membership type shares the table but belongs to the Community
 * module, so nothing here writes one.
 */
final class UserDivision extends BaseModel
{
    protected string $table = 'user_divisions';
    protected string $columns = 'id, user_id, gn_division_id, membership_type, verified_by, verified_at, status, created_at';

    /**
     * The home membership a new registration applies for. Pending until a
     * moderator decides it (Proposal §19.1).
     */
    public function createHome(int $userId, int $divisionId): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO user_divisions (user_id, gn_division_id, membership_type, status)
             VALUES (:user_id, :division_id, 'home', 'pending')"
        );

        $statement->execute(['user_id' => $userId, 'division_id' => $divisionId]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * One division's verification queue, newest application last so the
     * longest wait is reviewed first.
     *
     * @param string $status '' for every state, or one user_divisions status
     *
     * @return list<array<string, mixed>>
     */
    public function queueForDivision(int $divisionId, string $status): array
    {
        $params = ['division' => $divisionId];
        $filter = '';

        if ($status !== '') {
            $filter = ' AND ud.status = :status';
            $params['status'] = $status;
        }

        return $this->select(
            'SELECT ud.id, ud.status, ud.created_at, ud.verified_at,
                    u.full_name, u.nic, u.email, u.phone, u.address,
                    d.name AS division_name
               FROM user_divisions ud
               JOIN users u        ON u.id = ud.user_id
               JOIN gn_divisions d ON d.id = ud.gn_division_id
              WHERE ud.gn_division_id = :division
                AND ud.membership_type = \'home\'' . $filter . '
              ORDER BY ud.created_at ASC
              LIMIT 100',
            $params
        );
    }

    public function countPendingForDivision(int $divisionId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM user_divisions
              WHERE gn_division_id = :division AND membership_type = 'home' AND status = 'pending'",
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
                    u.full_name, u.nic, u.phone, u.email, u.address, u.status AS user_status,
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
    public function decide(int $id, int $moderatorId, string $status): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_divisions
                SET status = :status, verified_by = :moderator, verified_at = NOW()
              WHERE id = :id AND status = 'pending'"
        );

        $statement->execute(['status' => $status, 'moderator' => $moderatorId, 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    /**
     * The division this member calls home, whatever state the membership is
     * in. Used after sign-in to route a moderator to their own queue.
     */
    public function homeDivisionId(int $userId): ?int
    {
        $id = $this->selectValue(
            "SELECT gn_division_id FROM user_divisions
              WHERE user_id = :id AND membership_type = 'home'
              LIMIT 1",
            ['id' => $userId]
        );

        return $id === false || $id === null ? null : (int) $id;
    }
}
