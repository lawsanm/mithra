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
