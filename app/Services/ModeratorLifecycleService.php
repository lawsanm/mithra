<?php

declare(strict_types=1);

/** Admin decisions about an existing appointment; every decision is atomic. */
final class ModeratorLifecycleService
{
    public function __construct(
        private PDO $pdo,
        private Moderator $moderators,
        private ModeratorObjection $objections,
        private ModeratorConductHistory $history,
        private GnDivision $divisions,
        private User $users,
        private ModeratorCandidate $candidates,
        private Wallet $wallets,
        private LedgerService $ledger,
        private Notification $notifications
    ) {
    }

    public function decide(int $id, int $adminId, array $input): void
    {
        Database::transaction($this->pdo, function () use ($id, $adminId, $input): void {
            $snapshot = $this->moderators->lifecycle($id);
            if ($snapshot === null) {
                throw new RecordNotFoundException('Appointment not found.');
            }
            $division = $this->divisions->lockForAppointment((int) $snapshot['gn_division_id']);
            $row = $this->moderators->lifecycle($id, true);
            self::requireCurrent($row, $division);
            $action = $input['action'];
            if ($action === 'record_objection') {
                $memberId = (int) $input['member_id'];
                if ($row['objection_window_ends'] === null || new DateTimeImmutable($row['objection_window_ends']) <= new DateTimeImmutable()) {
                    throw ValidationException::field('action', 'The seven-day objection window has closed.');
                }
                if (!$this->candidates->isActiveHomeMember($memberId, (int) $row['gn_division_id'])) {
                    throw ValidationException::field('member_id', 'Select an active home member of this division.');
                }
                $objectionId = $this->objections->record($id, $memberId, $input['reason']);
                $this->history->record((int) $row['user_id'], $adminId, 'objection_recorded', 'Appointment #' . $id . ', objection #' . $objectionId . ': recorded on behalf of member #' . $memberId);
                return;
            }
            if (in_array($action, ['dismiss', 'uphold'], true)) {
                $objectionId = (int) $input['objection_id'];
                $objection = $this->objections->lockForAssignment($objectionId, $id);
                if ($objection === null || $objection['status'] !== 'pending') {
                    throw ValidationException::field('objection_id', 'Choose a pending objection for this appointment.');
                }
                $this->objections->decide($objectionId, $action === 'dismiss' ? 'dismissed' : 'upheld');
                $this->history->record((int) $row['user_id'], $adminId, 'objection_' . $action, 'Appointment #' . $id . ', objection #' . $objectionId . ': ' . $input['reason']);
                if ($action === 'uphold') {
                    $this->end($row, $adminId, $input['bond_decision'], $input['reason']);
                }
                $this->notifications->push((int) $objection['member_id'], 'account_notice', [
                    'title' => 'Moderator objection reviewed',
                    'detail' => 'Your objection was ' . ($action === 'dismiss' ? 'dismissed' : 'upheld') . '. ' . $input['reason'],
                    'href' => '/notifications',
                ]);
                return;
            }
            if ($action === 'confirm') {
                self::requireTrialComplete($row, $this->objections->unresolvedCount($id), new DateTimeImmutable());
                $this->moderators->confirmTrial($id);
                $this->candidates->closeAppointmentNomination((int) $row['user_id'], (int) $row['gn_division_id'], 'appointed');
                $this->history->record((int) $row['user_id'], $adminId, 'trial_confirmed', 'Appointment #' . $id . ': ' . $input['reason']);
                $this->notifications->push((int) $row['user_id'], 'account_notice', ['title' => 'Moderator trial confirmed', 'detail' => 'The Admin has reviewed and confirmed your appointment. Your conduct bond remains held.', 'href' => '/notifications']);
                return;
            }
            if ($action !== 'remove') {
                throw ValidationException::field('action', 'Choose a valid appointment action.');
            }
            if ($input['bond_decision'] === 'return' && $this->objections->unresolvedCount($id) > 0) {
                throw ValidationException::field('bond_decision', 'Review outstanding objections before recording departure in good standing.');
            }
            $this->end($row, $adminId, $input['bond_decision'], $input['reason']);
        });
    }

    public static function requireCurrent(?array $row, ?array $division): void
    {
        if ($row === null || $division === null || !in_array($row['status'], ['trial', 'active', 'suspended'], true)
            || $row['ended_at'] !== null || (int) $division['moderator_id'] !== (int) $row['user_id']) {
            throw ValidationException::field('action', 'This appointment is no longer current. Reload the page.');
        }
    }

    public static function requireTrialComplete(array $row, int $unresolved, DateTimeImmutable $now): void
    {
        if ($row['status'] !== 'trial' || $row['confirmed_at'] !== null) {
            throw ValidationException::field('action', 'Only an unconfirmed trial appointment can be confirmed.');
        }
        if ($row['trial_ends_at'] === null || $row['objection_window_ends'] === null
            || new DateTimeImmutable($row['trial_ends_at']) > $now
            || new DateTimeImmutable($row['objection_window_ends']) > $now) {
            throw ValidationException::field('action', 'Both the objection window and three-month trial must end before confirmation.');
        }
        if ($unresolved > 0) {
            throw ValidationException::field('action', 'All objections must be dismissed before confirming the trial.');
        }
    }

    private function end(array $row, int $adminId, string $decision, string $reason): void
    {
        if (!in_array($decision, ['return', 'forfeit'], true)) {
            throw ValidationException::field('bond_decision', 'Choose whether the departure is in good standing or for cause.');
        }
        $userId = (int) $row['user_id'];
        $divisionId = (int) $row['gn_division_id'];
        $pool = $decision === 'return' ? 'sponsor' : 'reserve';
        $this->ledger->lockMemberBalances([$userId], [$pool]);
        $bond = $this->wallets->bondLocked($userId);
        if ($row['bond_status'] !== 'held' || $bond !== (int) $row['bond_points']) {
            throw ValidationException::field('bond_decision', 'The recorded bond and wallet do not agree. Reconcile them before ending this appointment.');
        }
        if ($bond > 0) {
            if (!$this->wallets->adjustBondLocked($userId, -$bond)) {
                throw ValidationException::field('bond_decision', 'The bond could not be released. Nothing was saved.');
            }
            $this->ledger->memberToPool($userId, $pool, $bond, $decision === 'return' ? 'bond_return' : 'bond_forfeit');
        }
        if (!$this->divisions->releaseModerator($divisionId, $userId) || !$this->users->demoteModerator($userId)) {
            throw ValidationException::field('action', 'The moderator assignment changed. Nothing was saved; reload and try again.');
        }
        $this->moderators->endAppointment((int) $row['id'], $decision === 'return' ? 'resigned' : 'removed', $decision === 'return' ? 'returned' : 'forfeited', $reason);
        $this->candidates->closeAppointmentNomination($userId, $divisionId, $decision === 'return' ? 'withdrawn' : 'rejected');
        $this->history->record($userId, $adminId, 'appointment_ended', 'Appointment #' . $row['id'] . '; bond ' . $decision . ': ' . $reason);
        $this->notifications->pushToDivisionMembers($divisionId, 'account_notice', ['title' => 'Moderator appointment ended', 'detail' => $row['full_name'] . ' is no longer the division moderator. The Admin will manage the vacancy.', 'href' => '/notifications']);
    }
}
