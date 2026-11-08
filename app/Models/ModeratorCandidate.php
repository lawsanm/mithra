<?php

declare(strict_types=1);

final class ModeratorCandidate extends BaseModel
{
    public function closeAppointmentNomination(int $userId, int $divisionId, string $status): void
    {
        $this->execute("UPDATE moderator_candidates SET status = :status WHERE user_id = :user AND gn_division_id = :division AND status IN ('provisional','appointed')", ['user' => $userId, 'division' => $divisionId, 'status' => $status]);
    }

    public function withdraw(int $id): bool
    {
        return $this->execute("UPDATE moderator_candidates SET status = 'withdrawn' WHERE id = :id AND status IN ('nominated','checks_passed','evaluated')", ['id' => $id]) === 1;
    }

    protected string $table = 'moderator_candidates';
    protected string $columns = 'id, gn_division_id, user_id, candidate_name, nominated_by, endorsement, nic_verified, residence_verified, reference_1, reference_2, declaration_signed, interview_score, involvement_score, references_score, competency_score, total_score, status, created_at, updated_at';

    /** @return list<array<string, mixed>> */
    public function candidatesForDivision(int $divisionId): array
    {
        return $this->select(
            'SELECT u.id, u.full_name, u.trust_score, u.address, u.joined_at,
                    ud.verified_at, u.nic_photo_path, ud.proof_file_path,
                    mc.declaration_reference, mc.capability_note, mc.reviewed_at, reviewer.full_name AS reviewer_name,
                    (SELECT COUNT(*) FROM bookings b
                      WHERE (b.borrower_id = u.id OR b.lender_id = u.id)
                        AND b.status = \'completed\')
                    + (SELECT COUNT(*) FROM donations dn
                        WHERE dn.donor_id = u.id AND dn.status = \'completed\') AS completed_transactions,
                    (SELECT COUNT(*) FROM damage_claims dc
                       JOIN bookings cb ON cb.id = dc.booking_id
                      WHERE cb.borrower_id = u.id AND dc.status = \'resolved\') AS upheld_claims,
                    EXISTS (SELECT 1 FROM account_suspensions susp WHERE susp.user_id = u.id) AS has_suspension,
                    mc.id AS candidate_id,
                    (mc.endorsement IS NOT NULL AND mc.endorsement <> \'\') AS gn_endorsed,
                    mc.endorsement,
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
              LEFT JOIN users reviewer ON reviewer.id = mc.reviewed_by
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

    /** @return array<string, mixed>|null */
    public function findForReview(int $candidateId, int $divisionId): ?array
    {
        return $this->selectOne(
            "SELECT id, gn_division_id, user_id, candidate_name, endorsement,
                    nic_verified, residence_verified, reference_1, reference_2,
                    declaration_signed, interview_score, involvement_score,
                    references_score, competency_score, total_score, status
               FROM moderator_candidates
              WHERE id = :id AND gn_division_id = :division
                AND status IN ('nominated', 'checks_passed', 'evaluated')",
            ['id' => $candidateId, 'division' => $divisionId]
        );
    }

    /** @param array<string, mixed> $review */
    public function updateReview(int $candidateId, int $divisionId, array $review): bool
    {
        return $this->execute(
            "UPDATE moderator_candidates
                SET endorsement = :endorsement, declaration_reference = :declaration_reference,
                    capability_note = :capability_note, reviewed_by = :reviewed_by, reviewed_at = NOW(),
                    nic_verified = :nic_verified,
                    residence_verified = :residence_verified,
                    reference_1 = :reference_1,
                    reference_2 = :reference_2,
                    declaration_signed = :declaration_signed,
                    interview_score = :interview_score,
                    involvement_score = :involvement_score,
                    references_score = :references_score,
                    competency_score = :competency_score,
                    total_score = :total_score,
                    status = :status
              WHERE id = :id AND gn_division_id = :division
                AND status IN ('nominated', 'checks_passed', 'evaluated')",
            $review + ['id' => $candidateId, 'division' => $divisionId]
        ) === 1;
    }

    /** @return array<string, mixed>|null */
    public function lockForAppointment(int $candidateId, int $divisionId): ?array
    {
        return $this->selectOne(
            "SELECT id, user_id, status
               FROM moderator_candidates
              WHERE id = :id AND gn_division_id = :division
                AND status IN ('nominated', 'checks_passed', 'evaluated')
              FOR UPDATE",
            ['id' => $candidateId, 'division' => $divisionId]
        );
    }

    public function markProvisional(int $candidateId, int $divisionId): bool
    {
        return $this->execute(
            "UPDATE moderator_candidates SET status = 'provisional'
              WHERE id = :id AND gn_division_id = :division
                AND status IN ('nominated', 'checks_passed', 'evaluated')",
            ['id' => $candidateId, 'division' => $divisionId]
        ) === 1;
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
                    WHERE nominator.id = :nominator_user AND nominator.status = 'active'
                )",
            [
                'division' => $divisionId,
                'nominator' => $nominatorId,
                'member_division' => $divisionId,
                'nominator_division' => $divisionId,
                'nominator_user' => $nominatorId,
                'user' => $userId,
            ]
        );

        if ($inserted !== 1) {
            throw ValidationException::field('candidate_id', 'The nominee or nominator is no longer active in this division. Reload and try again.');
        }

        return (int) $this->pdo->lastInsertId();
    }
}
