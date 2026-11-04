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
}
