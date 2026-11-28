<?php

declare(strict_types=1);

/** Admin suspension lifecycle for resident member accounts (Proposal §6.3.3, §20). */
final class AdminUserSuspensionService
{
    public function __construct(
        private PDO $pdo,
        private User $users,
        private Booking $bookings,
        private AccountSuspension $suspensions,
        private Notification $notifications,
        private TrustScoreService $trust
    ) {
    }

    public function suspend(int $userId, int $adminId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255 || !Validator::hasLetters($reason)) {
            throw ValidationException::field('reason', 'Enter a clear suspension reason of no more than 255 characters.');
        }

        Database::transaction($this->pdo, function () use ($userId, $adminId, $reason): void {
            $user = $this->users->lockForAdminSuspension($userId)
                ?? throw new RecordNotFoundException('Member account not found.');
            $this->requireMember($user, 'Only a member account can be suspended here. Moderator appointments have their own Admin workflow.');
            if ($user['status'] !== 'active') {
                throw ValidationException::field('form', 'Only an active member can be suspended.');
            }
            if ($this->bookings->countOpenForMember($userId) > 0) {
                throw ValidationException::field('form', 'Resolve or close this member’s active bookings before suspending the account.');
            }
            if ($this->suspensions->lockOpenForUser($userId) !== null) {
                throw ValidationException::field('form', 'This member already has an active suspension record.');
            }
            if (!$this->users->suspendMemberByAdmin($userId)) {
                throw ValidationException::field('form', 'The account changed while you were suspending it. Reload and try again.');
            }

            $this->suspensions->record($userId, $adminId, $reason);
            $this->notifications->push($userId, 'account_notice', [
                'title' => 'Account suspended by the Admin',
                'detail' => $reason,
                'href' => '/notifications',
            ]);
        });
    }

    public function reinstate(int $userId): void
    {
        Database::transaction($this->pdo, function () use ($userId): void {
            $user = $this->users->lockForAdminSuspension($userId)
                ?? throw new RecordNotFoundException('Member account not found.');
            $this->requireMember($user, 'Only a member account can be reinstated here. Moderator appointments have their own Admin workflow.');
            if ($user['status'] !== 'suspended') {
                throw ValidationException::field('form', 'This member is not currently suspended.');
            }

            $suspension = $this->suspensions->lockOpenForUser($userId);
            if ($suspension === null) {
                throw ValidationException::field('form', 'No active suspension record exists. Reconcile the account history before reinstating it.');
            }
            if (!$this->users->reinstateMemberByAdmin($userId) || !$this->suspensions->close((int) $suspension['id'])) {
                throw ValidationException::field('form', 'The account changed while you were reinstating it. Reload and try again.');
            }

            $this->trust->recalculate($userId);
            $this->notifications->push($userId, 'account_notice', [
                'title' => 'Account reinstated by the Admin',
                'detail' => 'Your account is active again. A past suspension is included in your trust-score history.',
                'href' => '/notifications',
            ]);
        });
    }

    /** @param array<string, mixed> $user */
    private function requireMember(array $user, string $message): void
    {
        if ($user['role_code'] !== 'member') {
            throw ValidationException::field('form', $message);
        }
    }
}
