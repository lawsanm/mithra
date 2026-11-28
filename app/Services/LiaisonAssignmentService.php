<?php

declare(strict_types=1);

final class LiaisonAssignmentService
{
    public function __construct(private PDO $pdo, private User $users, private Notification $notifications)
    {
    }

    public function assign(int $userId, int $adminId, string $action): void
    {
        Database::transaction($this->pdo, function () use ($userId, $adminId, $action): void {
            $this->users->lockLiaisonRole();
            $user = $this->users->lockStaffAccount($userId);
            if ($user === null || $user['status'] !== 'active') {
                throw ValidationException::field('user_id', 'Choose an active account.');
            }
            if ($action === 'assign') {
                if ($user['role_code'] !== 'member' || $this->users->hasMemberCommitments($userId)) {
                    throw ValidationException::field('user_id', 'Use an account without active community memberships, listings, bookings or wallet points. Existing member commitments must be settled first.');
                }
            } elseif ($action === 'remove') {
                if ($user['role_code'] !== 'sponsor_liaison' || $this->users->activeLiaisonCount() <= 1) {
                    throw ValidationException::field('user_id', 'Assign a replacement before removing the last active Sponsor Liaison.');
                }
            } else {
                throw ValidationException::field('action', 'Choose a valid assignment action.');
            }
            $this->users->assignLiaisonRole($userId, $action === 'assign');
            $payload = ['title' => 'Sponsor Liaison assignment updated', 'detail' => 'Admin #' . $adminId . ' recorded ' . $action . ' for account #' . $userId . '.', 'href' => '/notifications'];
            $this->notifications->push($userId, 'account_notice', $payload);
            $this->notifications->push($adminId, 'account_notice', $payload);
        });
    }
}
