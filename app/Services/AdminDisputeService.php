<?php

declare(strict_types=1);

/** Final Admin rulings and their point settlement (Proposal §10.2, §16.5). */
final class AdminDisputeService
{
    public function __construct(
        private PDO $pdo,
        private Dispute $disputes,
        private DamageClaim $claims,
        private Booking $bookings,
        private ReturnRecord $returnRecords,
        private Item $items,
        private User $users,
        private AccountSuspension $suspensions,
        private Notification $notifications,
        private LedgerService $ledger,
        private Moderator $moderators,
        private ModeratorCandidate $candidates,
        private ModeratorConductHistory $conductHistory,
        private GnDivision $divisions,
        private Wallet $wallets,
        private ReturnService $returns,
        private TrustScoreService $trust
    ) {
    }

    /** @throws ValidationException|RecordNotFoundException */
    public function rule(int $id, int $adminId, int $award, string $rationale): void
    {
        $rationale = trim($rationale);

        if ($rationale === '' || mb_strlen($rationale) > 1000 || !Validator::hasLetters($rationale)) {
            throw ValidationException::field('rationale', 'Explain the evidence and why this final award is fair.');
        }

        try {
            Database::transaction($this->pdo, function () use ($id, $adminId, $award, $rationale): void {
                $dispute = $this->disputes->lockForRuling($id)
                    ?? throw new RecordNotFoundException('No such dispute.');

                if ($dispute['status'] !== 'open' || $dispute['damage_claim_id'] === null) {
                    throw ValidationException::field('form', 'This dispute is already closed or has no damage claim to rule on.');
                }

                $claim = $this->claims->lockForUpdate((int) $dispute['damage_claim_id'])
                    ?? throw new RecordNotFoundException('The damage claim for this dispute no longer exists.');
                $booking = $this->bookings->lockForUpdate((int) $dispute['booking_id'])
                    ?? throw new RecordNotFoundException('The booking for this dispute no longer exists.');

                if ($award < 0 || $award > (int) $booking['declared_value']) {
                    throw ValidationException::field('award_pts', 'The award must be between 0 and the item’s declared value.');
                }

                if ($claim['status'] === 'closed' && $claim['borrower_response'] === 'accepted') {
                    $this->adjustAcceptedClaim($claim, $booking, $award, $adminId);
                } elseif ($claim['status'] === 'escalated' && $booking['status'] === 'escalated') {
                    $this->settleEscalatedClaim($claim, $booking, $award, $adminId);
                } else {
                    throw ValidationException::field('form', 'This claim is not waiting for an Admin ruling. Reload the case and check its latest status.');
                }

                if (!$this->disputes->recordRuling($id, $adminId, $award, $rationale)) {
                    throw ValidationException::field('form', 'This dispute changed while you were deciding. Reload and try again.');
                }

                $this->notifyRuling($booking, $id, $award);
                $this->trust->recalculate((int) $booking['borrower_id']);
                $this->trust->recalculate((int) $booking['lender_id']);
            });
        } catch (InsufficientPointsException) {
            throw ValidationException::field('award_pts', 'The Reserve Pool cannot cover this settlement right now. The final ruling was not recorded.');
        }
    }

    /** @param array<string, mixed> $claim @param array<string, mixed> $booking */
    private function adjustAcceptedClaim(array $claim, array $booking, int $award, int $adminId): void
    {
        $previous = (int) $claim['proposed_penalty'];
        $difference = $award - $previous;

        if ($difference > 0) {
            $this->chargePenalty($booking, $difference, $adminId);
        } elseif ($difference < 0) {
            try {
                $this->ledger->memberToMember(
                    (int) $booking['lender_id'],
                    (int) $booking['borrower_id'],
                    abs($difference),
                    'dispute_refund',
                    ['booking_id' => (int) $booking['id']]
                );
            } catch (InsufficientPointsException) {
                throw ValidationException::field('award_pts', 'The lender must have enough available points for this refund before you can record the final ruling.');
            }
        }
    }

    /** @param array<string, mixed> $claim @param array<string, mixed> $booking */
    private function settleEscalatedClaim(array $claim, array $booking, int $award, int $adminId): void
    {
        if ($award > 0) {
            $this->chargePenalty($booking, $award, $adminId);
        }

        $record = $this->returnRecords->forBooking((int) $booking['id']);
        $totalLoss = $record === null && $claim['severity'] === 'total_loss';

        if ($record === null && !$totalLoss) {
            throw ValidationException::field('form', 'A return record is missing. Only a total-loss case can be ruled without one.');
        }

        $returnedAt = $record['return_at'] ?? null;
        $outcome = $this->returns->settle(
            $booking,
            new DateTimeImmutable($returnedAt === null ? 'now' : (string) $returnedAt)
        );

        $claimTarget = $award > 0 ? 'resolved' : 'closed';
        if (!$this->claims->move((int) $claim['id'], 'escalated', $claimTarget)) {
            throw ValidationException::field('form', 'The damage claim changed while you were deciding. Reload and try again.');
        }

        if (!$this->bookings->move((int) $booking['id'], 'escalated', 'completed')) {
            throw ValidationException::field('form', 'The booking changed while you were deciding. Reload and try again.');
        }

        if ($totalLoss) {
            $this->items->archiveAsLost((int) $booking['item_id']);
            $this->removeModeratorForTotalLoss($booking, $adminId);
            if ($award > 0 && $this->users->suspendForTotalLoss((int) $booking['borrower_id'])) {
                $this->suspensions->record(
                    (int) $booking['borrower_id'],
                    $adminId,
                    'Suspended after the Admin’s final ruling on a total-loss case.'
                );
            }
        } else {
            $this->returns->finish($booking, $outcome);
        }
    }

    /** Apply the moderator's held bond before spendable points or Reserve cover. */
    private function chargePenalty(array $booking, int $amount, int $adminId): void
    {
        $borrowerId = (int) $booking['borrower_id'];
        $divisionId = (int) $booking['gn_division_id'];
        $assignment = (int) ($booking['division_moderator_id'] ?? 0) === $borrowerId
            ? $this->moderators->currentForUserAndDivision($borrowerId, $divisionId)
            : null;

        $remaining = $amount;
        if ($assignment !== null) {
            $bond = $this->wallets->bondLocked($borrowerId);
            if ($assignment['bond_status'] !== 'held' || $bond !== (int) $assignment['bond_points']) {
                throw ValidationException::field('award_pts', 'The moderator conduct bond does not match the appointment record. Reconcile it before ruling.');
            }

            $fromBond = $this->ledger->bondToMember(
                $borrowerId,
                (int) $booking['lender_id'],
                $amount,
                'damage_penalty',
                (int) $booking['id']
            );
            $remaining -= $fromBond;

            $bondRemaining = $bond - $fromBond;
            if ($fromBond > 0 && !$this->moderators->updateBondPoints((int) $assignment['id'], $bondRemaining)) {
                throw ValidationException::field('award_pts', 'The moderator appointment changed during bond settlement. Nothing was saved.');
            }

            if ($fromBond === $bond && $bond > 0) {
                $assignment['bond_points'] = 0;
                $this->removeModeratorForMisconduct($assignment, $adminId, 'The conduct bond reached zero after an Admin damage ruling.', 'suspended');
            }
        }

        if ($remaining > 0) {
            $this->ledger->chargeWithCover(
                $borrowerId,
                (int) $booking['lender_id'],
                $remaining,
                'damage_penalty',
                (int) $booking['id']
            );
        }
    }

    /** A total-loss ruling against a Moderator removes the appointment and forfeits any bond left. */
    private function removeModeratorForTotalLoss(array $booking, int $adminId): void
    {
        $borrowerId = (int) $booking['borrower_id'];
        if ((int) ($booking['division_moderator_id'] ?? 0) !== $borrowerId) {
            return;
        }
        $assignment = $this->moderators->currentForUserAndDivision($borrowerId, (int) $booking['gn_division_id']);
        if ($assignment !== null) {
            $this->removeModeratorForMisconduct($assignment, $adminId, 'Removed after an Admin total-loss ruling on an unreturned item.', 'removed');
        }
    }

    /** Release the division and role; replacement selection remains an Admin appointment workflow. */
    private function removeModeratorForMisconduct(array $assignment, int $adminId, string $reason, string $status): void
    {
        $userId = (int) $assignment['user_id'];
        $divisionId = (int) $assignment['gn_division_id'];
        $bond = $this->wallets->bondLocked($userId);

        if ($bond > 0) {
            if (!$this->wallets->adjustBondLocked($userId, -$bond)) {
                throw ValidationException::field('award_pts', 'The remaining conduct bond could not be released. Nothing was saved.');
            }
            $this->ledger->memberToPool($userId, 'reserve', $bond, 'bond_forfeit');
        }

        if (!$this->divisions->releaseModerator($divisionId, $userId) || !$this->users->demoteModerator($userId)) {
            throw ValidationException::field('award_pts', 'The moderator appointment changed during the ruling. Nothing was saved; reload and try again.');
        }
        if (!$this->moderators->endForAdminRuling((int) $assignment['id'], $bond, $status)) {
            throw ValidationException::field('award_pts', 'The moderator appointment could not be closed. Nothing was saved.');
        }

        $this->candidates->closeAfterRuling($userId, $divisionId);
        $this->conductHistory->record($userId, $adminId, 'appointment_ended', $reason . ' Remaining conduct bond forfeited to Reserve Pool.');
        $this->notifications->pushToDivisionMembers($divisionId, 'account_notice', [
            'title' => 'Moderator appointment ended',
            'detail' => 'The Admin ended the moderator appointment after a final dispute ruling. A replacement will be appointed through the Admin selection process.',
            'href' => '/notifications',
        ]);
    }

    /** @param array<string, mixed> $booking */
    private function notifyRuling(array $booking, int $disputeId, int $award): void
    {
        foreach ([(int) $booking['borrower_id'], (int) $booking['lender_id']] as $memberId) {
            $this->notifications->push($memberId, 'dispute_ruled', [
                'title'      => 'Final dispute ruling recorded',
                'detail'     => sprintf('The Admin awarded %s points to the lender. View the case decision.', number_format($award)),
                'icon'       => 'check-circle',
                'href'       => '/bookings/' . (int) $booking['id'],
                'booking_id' => (int) $booking['id'],
                'dispute_id' => $disputeId,
            ]);
        }
    }
}
