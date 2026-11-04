<?php

declare(strict_types=1);

/** Provisional moderator appointment, public notice and day-one conduct bond (§16). */
final class ModeratorAppointmentService
{
    public const CONDUCT_BOND = 500;

    public function __construct(
        private PDO $pdo,
        private GnDivision $divisions,
        private ModeratorCandidate $candidateRecords,
        private ModeratorCandidateService $candidateReviews,
        private Moderator $moderators,
        private User $users,
        private Wallet $wallets,
        private LedgerService $ledger,
        private Notification $notifications,
        private ModeratorConductHistory $history
    ) {
    }

    public function appoint(int $divisionId, int $candidateUserId, int $adminId, bool $acknowledged = false): int
    {
        if (!$acknowledged) {
            throw ValidationException::field('acknowledged', 'Record the candidate’s signed misconduct acknowledgement before appointment.');
        }
        return Database::transaction($this->pdo, function () use ($divisionId, $candidateUserId, $adminId): int {
            $division = $this->divisions->lockForAppointment($divisionId);

            if ($division === null || $division['status'] !== 'active') {
                throw ValidationException::field('division_id', 'Choose an active division.');
            }

            if ($division['moderator_id'] !== null) {
                throw ValidationException::field('candidate_user_id', 'This division already has a moderator. End the current appointment before selecting a replacement.');
            }

            $initialAppointment = !$this->moderators->hasAppointmentHistory($divisionId);

            if ($initialAppointment && $this->divisions->activeMemberCount($divisionId) < 10) {
                throw ValidationException::field('candidate_user_id', 'The first appointment is available after the division reaches 10 active members.');
            }

            $pool = $this->candidateReviews->selectionPool($divisionId);
            $qualified = array_values(array_filter(
                $pool,
                static fn (array $candidate): bool => $initialAppointment
                    ? $candidate['launch_ready']
                    : $candidate['eligible']
            ));

            usort($qualified, static function (array $left, array $right) use ($initialAppointment): int {
                if ($initialAppointment) {
                    $scoreOrder = (float) $right['total_score'] <=> (float) $left['total_score'];

                    if ($scoreOrder !== 0) {
                        return $scoreOrder;
                    }
                }

                return $right['trust_score'] <=> $left['trust_score'];
            });

            $selected = $qualified[0] ?? null;

            if ($selected === null || $selected['id'] !== $candidateUserId) {
                throw ValidationException::field(
                    'candidate_user_id',
                    $initialAppointment
                        ? 'Select the highest-scoring candidate with all launch checks complete.'
                        : 'Select the highest-trust eligible member for this division.'
                );
            }

            $candidateId = $selected['candidate_id'];
            $candidate = $candidateId === null
                ? null
                : $this->candidateRecords->lockForAppointment($candidateId, $divisionId);

            if ($candidate === null || (int) $candidate['user_id'] !== $candidateUserId) {
                throw ValidationException::field('candidate_user_id', 'Reload the candidate list; this nomination is no longer available.');
            }

            if (!$this->candidateRecords->isActiveHomeMember($candidateUserId, $divisionId)) {
                throw ValidationException::field('candidate_user_id', 'The selected nominee is no longer an active home member of this division.');
            }

            $this->wallets->openFor($candidateUserId);
            $this->ledger->lockMemberBalances([$candidateUserId], ['sponsor']);

            if ($this->wallets->bondLocked($candidateUserId) > 0) {
                throw ValidationException::field('candidate_user_id', 'This member already has a locked conduct bond.');
            }

            try {
                $this->ledger->poolToMember('sponsor', $candidateUserId, self::CONDUCT_BOND, 'bond_hold');
            } catch (InsufficientPointsException $exception) {
                throw ValidationException::field(
                    'candidate_user_id',
                    'The Sponsor Pool cannot fund the 500-point conduct bond, so the appointment was not made.'
                );
            }

            if (!$this->wallets->adjustBondLocked($candidateUserId, self::CONDUCT_BOND)) {
                throw ValidationException::field('candidate_user_id', 'The conduct bond could not be locked. The appointment was not made.');
            }

            if (!$this->users->promoteActiveMemberToModerator($candidateUserId)) {
                throw ValidationException::field('candidate_user_id', 'The selected member is no longer available for appointment.');
            }

            if (!$this->candidateRecords->markProvisional($candidateId, $divisionId)) {
                throw ValidationException::field('candidate_user_id', 'The nomination changed during appointment. Nothing was saved.');
            }

            $assignmentId = $this->moderators->createProvisional($candidateUserId, $divisionId, $adminId);
            $this->history->record($candidateUserId, $adminId, 'appointment_acknowledgement', 'Appointment #' . $assignmentId . ': Admin verified the signed acknowledgement of removal, bond forfeiture and permanent conduct history for misconduct.');

            if (!$this->divisions->assignModerator($divisionId, $candidateUserId)) {
                throw ValidationException::field('candidate_user_id', 'Another appointment was recorded for this division. Nothing was saved.');
            }

            $this->notifications->pushToDivisionMembers($divisionId, 'moderator_appointed', [
                'title'  => 'Provisional moderator appointed: ' . $selected['name'],
                'detail' => 'A 7-day community objection window is open. The six-month trial and 500-point conduct bond have started.',
                'icon'   => 'users',
                'href'   => '/notifications',
            ]);

            return $assignmentId;
        });
    }
}
