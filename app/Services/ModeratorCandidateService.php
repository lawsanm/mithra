<?php

declare(strict_types=1);

/** Admin recording of member nominations for a division (§16.1). */
final class ModeratorCandidateService
{
    public function __construct(
        private GnDivision $divisions,
        private ModeratorCandidate $candidates
    ) {
    }

    public function nominate(int $divisionId, int $candidateId, int $nominatorId): int
    {
        if (!$this->divisions->isActive($divisionId)) {
            throw ValidationException::field('division_id', 'Choose an active GN division.');
        }

        if (!$this->candidates->isActiveHomeMember($candidateId, $divisionId)) {
            throw ValidationException::field('candidate_id', 'The nominee must be an active member of this division.');
        }

        if (!$this->candidates->isActiveHomeMember($nominatorId, $divisionId)) {
            throw ValidationException::field('nominated_by', 'The nominator must be an active member of this division.');
        }

        if ($this->candidates->hasOpenNomination($candidateId, $divisionId)) {
            throw ValidationException::field('candidate_id', 'This member already has an open moderator nomination in this division.');
        }

        return $this->candidates->createNomination($divisionId, $candidateId, $nominatorId);
    }

    public function withdraw(int $id): void
    {
        if (!$this->candidates->withdraw($id)) {
            throw ValidationException::field('candidate_id', 'Only an open nomination can be withdrawn. Appointed candidates must use the appointment review.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function selectionPool(int $divisionId): array
    {
        $rows = $this->candidates->candidatesForDivision($divisionId);

        $candidates = array_map(static function (array $row): array {
            $joinedAt = $row['verified_at'] === null ? null : new DateTimeImmutable((string) $row['verified_at']);
            $now = new DateTimeImmutable();
            $tenure = $joinedAt === null ? null : $joinedAt->diff($now);
            $months = $tenure === null || $tenure->invert === 1 ? 0 : ($tenure->y * 12) + $tenure->m;
            $clean = (int) $row['upheld_claims'] === 0 && !(bool) $row['has_suspension'];
            $declared = (bool) $row['declaration_signed'];
            $status = (string) ($row['candidate_status'] ?? '');
            $evaluated = $status === 'evaluated';
            $referenceOne = trim((string) ($row['reference_1'] ?? ''));
            $referenceTwo = trim((string) ($row['reference_2'] ?? ''));
            $launchReady = (bool) $row['gn_endorsed']
                && (bool) $row['nic_verified']
                && (bool) $row['residence_verified']
                && $referenceOne !== ''
                && $referenceTwo !== ''
                && mb_strtolower($referenceOne) !== mb_strtolower($referenceTwo)
                && $declared
                && $evaluated
                && $row['interview_score'] !== null
                && $row['involvement_score'] !== null
                && $row['references_score'] !== null
                && $row['competency_score'] !== null
                && $row['total_score'] !== null;
            $eligible = $months >= 6
                && (bool) $row['nic_verified']
                && (bool) $row['residence_verified']
                && (int) $row['trust_score'] >= 70
                && (int) $row['completed_transactions'] >= 10
                && $clean
                && $declared
                && in_array($status, ['nominated', 'checks_passed', 'evaluated'], true)
                && $row['competency_score'] !== null;

            return [
                'id'                 => (int) $row['id'],
                'candidate_id'       => $row['candidate_id'] === null ? null : (int) $row['candidate_id'],
                'candidate_status'   => $status,
                'name'               => (string) $row['full_name'],
                'initials'           => User::initials((string) $row['full_name']),
                'trust_score'        => (int) $row['trust_score'],
                'address'            => (string) $row['address'],
                'verified_at'        => (string) ($row['verified_at'] ?? ''),
                'member_since'       => $joinedAt === null ? '' : $joinedAt->format('M Y'),
                'months'             => $months,
                'transactions'       => (int) $row['completed_transactions'],
                'upheld_claims'      => (int) $row['upheld_claims'],
                'has_suspension'     => (bool) $row['has_suspension'],
                'record'             => $clean ? 'Clean' : 'Review required',
                'gn_endorsed'        => (bool) $row['gn_endorsed'],
                'nic_verified'       => (bool) $row['nic_verified'],
                'residence_verified' => (bool) $row['residence_verified'],
                'reference_1'        => (string) ($row['reference_1'] ?? ''),
                'reference_2'        => (string) ($row['reference_2'] ?? ''),
                'declaration_signed' => $declared,
                'endorsement'        => (string) ($row['endorsement'] ?? ''),
                'interview_score'    => $row['interview_score'] === null ? '' : (string) $row['interview_score'],
                'involvement_score'  => $row['involvement_score'] === null ? '' : (string) $row['involvement_score'],
                'references_score'   => $row['references_score'] === null ? '' : (string) $row['references_score'],
                'competency_score'   => $row['competency_score'] === null ? '' : (string) $row['competency_score'],
                'total_score'        => $row['total_score'] === null ? '' : (string) $row['total_score'],
                'launch_ready'       => $launchReady,
                'eligible'           => $eligible,
                'conflict'           => $declared ? null : 'Declaration not recorded',
                'recommended'        => false,
            ];
        }, $rows);

        foreach ($candidates as $index => $candidate) {
            if ($candidate['eligible']) {
                $candidates[$index]['recommended'] = true;
                break;
            }
        }

        return $candidates;
    }

    /**
     * @param array{
     *     endorsement:?string,
     *     nic_verified:bool,
     *     residence_verified:bool,
     *     reference_1:?string,
     *     reference_2:?string,
     *     declaration_signed:bool,
     *     interview_score:?int,
     *     involvement_score:?int,
     *     references_score:?int,
     *     competency_score:?int
     * } $input
     */
    public function review(int $divisionId, int $candidateId, array $input): void
    {
        $candidate = $this->candidates->findForReview($candidateId, $divisionId);

        if ($candidate === null) {
            throw new RecordNotFoundException('No editable nomination exists in this division.');
        }

        $referenceOne = self::optionalText($input['reference_1']);
        $referenceTwo = self::optionalText($input['reference_2']);

        if ($referenceOne !== null && $referenceTwo !== null
            && mb_strtolower($referenceOne) === mb_strtolower($referenceTwo)) {
            throw ValidationException::field('reference_2', 'Record two different references from separate households.');
        }

        $endorsement = self::optionalText($input['endorsement']);
        $checksPassed = $endorsement !== null
            && $input['nic_verified']
            && $input['residence_verified']
            && $referenceOne !== null
            && $referenceTwo !== null
            && $input['declaration_signed'];
        $scoresComplete = $input['interview_score'] !== null
            && $input['involvement_score'] !== null
            && $input['references_score'] !== null
            && $input['competency_score'] !== null;
        $status = $checksPassed ? ($scoresComplete ? 'evaluated' : 'checks_passed') : 'nominated';
        $totalScore = $checksPassed && $scoresComplete
            ? round(
                $input['interview_score'] * 0.35
                + $input['involvement_score'] * 0.35
                + $input['references_score'] * 0.20
                + $input['competency_score'] * 0.10,
                2
            )
            : null;

        $updated = $this->candidates->updateReview($candidateId, $divisionId, [
            'endorsement'        => $endorsement,
            'nic_verified'       => (int) $input['nic_verified'],
            'residence_verified' => (int) $input['residence_verified'],
            'reference_1'        => $referenceOne,
            'reference_2'        => $referenceTwo,
            'declaration_signed' => (int) $input['declaration_signed'],
            'interview_score'    => $input['interview_score'],
            'involvement_score'  => $input['involvement_score'],
            'references_score'  => $input['references_score'],
            'competency_score'   => $input['competency_score'],
            'total_score'        => $totalScore,
            'status'             => $status,
        ]);

        if (!$updated && $this->candidates->findForReview($candidateId, $divisionId) === null) {
            throw new RecordNotFoundException('This nomination can no longer be edited.');
        }
    }

    private static function optionalText(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
