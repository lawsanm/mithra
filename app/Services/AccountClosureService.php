<?php

declare(strict_types=1);

/**
 * Closing an account (Plan §17, module 1.1 "deactivation").
 *
 *   Type A — standard:     the remaining points move to the Retired Pool and
 *                          are recycled to the Sponsor Pool on schedule.
 *   Type B — parting gift: the remaining points move to the Aid Pool.
 *
 * Either way the points stay inside the system (§7.4): the move is an ordinary
 * ledger entry. Closure is refused while the member has an active booking, a
 * pending damage claim or an open aid grant, and a moderator must hand over
 * the role (and its conduct bond) before closing. The member re-enters their
 * password to confirm; everything happens in one transaction.
 */
final class AccountClosureService
{
    public const TYPES = ['standard', 'parting_gift'];

    /** Aid grant states that still need the member's account. */
    private const OPEN_GRANT_STATES = ['requested', 'vouched', 'info_requested', 'approved', 'disbursed'];

    public function __construct(
        private PDO $pdo,
        private User $users,
        private UserDivision $memberships,
        private Item $items,
        private Booking $bookings,
        private AidGrant $grants,
        private Dispute $disputes,
        private Donation $donations,
        private Wallet $wallets,
        private PasswordReset $resets,
        private LedgerService $ledger
    ) {
    }

    /**
     * What stands in the way of closing, in words for the member.
     *
     * @return list<string> empty when the account may close
     */
    public function blockers(int $userId): array
    {
        $account  = $this->users->findAccount($userId);
        $blockers = [];

        if ($account === null || $account['status'] !== 'active') {
            return ['Only an active account can be closed.'];
        }

        if ($account['role_code'] !== 'member') {
            $blockers[] = $account['role_code'] === 'moderator'
                ? 'Moderators first resign through the Admin, who settles the conduct bond and appoints a replacement.'
                : 'Staff and sponsor accounts are closed by the Admin.';
        }

        if ($this->bookings->countOpenForMember($userId) > 0) {
            $blockers[] = 'You have an active booking. Finish or cancel it first.';
        }

        if ($this->bookings->countPendingClaimsForMember($userId) > 0) {
            $blockers[] = 'You are part of a damage claim that is not settled yet.';
        }

        $grant = $this->grants->activeForMember($userId);
        if ($grant !== null && in_array($grant['status'], self::OPEN_GRANT_STATES, true)) {
            $blockers[] = 'You have an open aid grant.';
        }

        if ($this->disputes->countOpenFor($userId) > 0) {
            $blockers[] = 'You are part of a dispute the Admin has not ruled on yet.';
        }

        if ($this->donations->countUnfinishedFor($userId) > 0) {
            $blockers[] = 'A donation you are giving or receiving is waiting for its handover.';
        }

        return $blockers;
    }

    public function balance(int $userId): int
    {
        return $this->wallets->balance($userId);
    }

    /**
     * @throws ValidationException
     *
     * @return int the points moved out of the wallet
     */
    public function close(int $userId, string $type, string $password): int
    {
        if (!in_array($type, self::TYPES, true)) {
            throw ValidationException::field('closure_type', 'Choose what happens to your remaining points.');
        }

        $account = $this->users->findAccount($userId);

        if ($account === null || !password_verify($password, (string) $account['password_hash'])) {
            throw ValidationException::field('close_password', 'Enter your current password to confirm.');
        }

        $blockers = $this->blockers($userId);
        if ($blockers !== []) {
            throw ValidationException::field('form', implode(' ', $blockers));
        }

        [$pool, $reason, $status] = $type === 'parting_gift'
            ? ['aid', 'parting_gift', 'closed_donation']
            : ['retired', 'account_closure', 'closed_standard'];

        $this->pdo->beginTransaction();

        try {
            $balance = $this->wallets->lockBalance($userId) ?? 0;

            if ($balance > 0) {
                $this->ledger->memberToPool($userId, $pool, $balance, $reason);
            }

            if (!$this->users->close($userId, $status)) {
                throw ValidationException::field('form', 'This account was already closed.');
            }

            $this->items->archiveAllFor($userId);
            $this->memberships->deactivateAllFor($userId);
            $this->resets->revokeFor($userId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return $balance;
    }
}
