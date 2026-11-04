<?php

declare(strict_types=1);

final class ModeratorCandidate extends BaseModel
{
    protected string $table = 'moderator_candidates';
    protected string $columns = 'id, gn_division_id, user_id, candidate_name, nominated_by, endorsement, nic_verified, residence_verified, reference_1, reference_2, declaration_signed, interview_score, involvement_score, references_score, competency_score, total_score, status, created_at, updated_at';

    /** @return list<array<string, mixed>> */
    public function candidatesForDivision(int $divisionId): array
    {
        return $this->select(
            'SELECT u.id, u.full_name, u.trust_score, u.address, u.joined_at,
                    ud.verified_at,
                    (SELECT COUNT(*) FROM bookings b
                      WHERE (b.borrower_id = u.id OR b.lender_id = u.id)
                        AND b.status = \'completed\')
                    + (SELECT COUNT(*) FROM donations dn
                        WHERE dn.donor_id = u.id AND dn.status = \'completed\') AS completed_transactions,
                    (SELECT COUNT(*) FROM damage_claims dc
                       JOIN bookings cb ON cb.id = dc.booking_id
                      WHERE cb.borrower_id = u.id AND dc.status = \'resolved\') AS upheld_claims,
                    EXISTS (SELECT 1 FROM account_suspensions susp WHERE susp.user_id = u.id) AS has_suspension,
                    (mc.endorsement IS NOT NULL AND mc.endorsement <> \'\') AS gn_endorsed,
                    mc.nic_verified, mc.residence_verified, mc.reference_1, mc.reference_2,
                    mc.declaration_signed, mc.interview_score, mc.involvement_score,
                    mc.references_score, mc.competency_score, mc.total_score,
                    mc.status AS candidate_status
               FROM users u
               JOIN user_divisions ud ON ud.user_id = u.id AND ud.gn_division_id = :division
                    AND ud.membership_type = \'home\' AND ud.status = \'active\'
               JOIN roles r ON r.id = u.role_id
               LEFT JOIN (
                    SELECT candidate.*
                      FROM moderator_candidates candidate
                      JOIN (
                          SELECT user_id, gn_division_id, MAX(id) AS latest_id
                            FROM moderator_candidates
                           WHERE user_id IS NOT NULL
                           GROUP BY user_id, gn_division_id
                      ) latest ON latest.latest_id = candidate.id
               ) mc ON mc.user_id = u.id AND mc.gn_division_id = :candidate_division
              WHERE r.code = \'member\'
                AND u.status = \'active\'
              ORDER BY u.trust_score DESC, u.id',
            ['division' => $divisionId, 'candidate_division' => $divisionId]
        );
    }

    public function isActiveHomeMember(int $userId, int $divisionId): bool
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*)
               FROM users u
               JOIN roles r ON r.id = u.role_id AND r.code = 'member'
               JOIN user_divisions ud ON ud.user_id = u.id
                    AND ud.gn_division_id = :division
                    AND ud.membership_type = 'home'
                    AND ud.status = 'active'
              WHERE u.id = :user AND u.status = 'active'",
            ['division' => $divisionId, 'user' => $userId]
        ) === 1;
    }

    public function hasOpenNomination(int $userId, int $divisionId): bool
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM moderator_candidates
              WHERE user_id = :user AND gn_division_id = :division
                AND status IN ('nominated', 'checks_passed', 'evaluated', 'provisional')",
            ['user' => $userId, 'division' => $divisionId]
        ) > 0;
    }

    public function createNomination(int $divisionId, int $userId, int $nominatorId): int
    {
        $inserted = $this->execute(
            "INSERT INTO moderator_candidates (gn_division_id, user_id, candidate_name, nominated_by, status)
             SELECT :division, u.id, u.full_name, :nominator, 'nominated'
               FROM users u
               JOIN roles r ON r.id = u.role_id AND r.code = 'member'
               JOIN user_divisions ud ON ud.user_id = u.id
                    AND ud.gn_division_id = :member_division
                    AND ud.membership_type = 'home'
                    AND ud.status = 'active'
              WHERE u.id = :user AND u.status = 'active'
                AND EXISTS (
                    SELECT 1 FROM users nominator
                    JOIN roles nr ON nr.id = nominator.role_id AND nr.code = 'member'
                    JOIN user_divisions nud ON nud.user_id = nominator.id
                         AND nud.gn_division_id = :nominator_division
                         AND nud.membership_type = 'home'
                         AND nud.status = 'active'
                    WHERE nominator.id = :nominator AND nominator.status = 'active'
                )",
            [
                'division' => $divisionId,
                'nominator' => $nominatorId,
                'member_division' => $divisionId,
                'nominator_division' => $divisionId,
                'user' => $userId,
            ]
        );

        if ($inserted !== 1) {
            throw ValidationException::field('candidate_id', 'The nominee or nominator is no longer active in this division. Reload and try again.');
        }

        return (int) $this->pdo->lastInsertId();
    }
}
