<?php

declare(strict_types=1);

/**
 * Member verification: the Admin's bootstrap decision or the moderator's
 * decision on a home-membership application (Plan §17.1 and §18.1).
 *
 * This is the other half of sign-up. Registration leaves an account pending
 * and unable to sign in; approval here is what lets that member through
 * AuthService. Every step of an approval — the membership row, the account
 * status and the 200-point welcome bonus from the Sponsor Pool (Plan §6.4) —
 * moves together or not at all.
 *
 * Review and decision methods verify that the acting reviewer is permitted in
 * the current division phase: an id in a URL is never trusted (§8).
 */
final class VerificationService
{
    /** Queue filters, as the pills on the screen offer them. */
    public const FILTERS = ['', 'pending', 'temporary', 'active', 'rejected'];

    private const REASON_MAX = 255;

    /** Credited once per verified person, from the Sponsor Pool (Plan §4.3, §6.4). */
    public const WELCOME_BONUS = 200;

    public function __construct(
        private PDO $pdo,
        private User $users,
        private UserDivision $memberships,
        private GnDivision $divisions,
        private Wallet $wallets,
        private LedgerService $ledger,
        private Notification $notifications
    ) {
    }

    /**
     * Every application in this moderator's division, optionally filtered.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(int $moderatorId, string $filter): array
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : '';

        return $this->memberships->queueForDivision($this->divisions->moderatedByOrFail($moderatorId), $filter);
    }

    public function pendingCount(int $moderatorId): int
    {
        return $this->memberships->countPendingForDivision($this->divisions->moderatedByOrFail($moderatorId));
    }

    /**
     * One application this moderator is entitled to read.
     *
     * @throws RecordNotFoundException when no such application exists
     * @throws AccessDeniedException   when it belongs to another division
     *
     * @return array<string, mixed>
     */
    public function review(int $id, int $moderatorId): array
    {
        $application = $this->memberships->findForReview($id);

        if ($application === null) {
            throw new RecordNotFoundException('No such verification.');
        }

        if ((int) $application['moderator_id'] !== $moderatorId) {
            throw new AccessDeniedException('This application belongs to another division.');
        }

        return $application;
    }

    /** A pending home registration the Admin may handle before moderator appointment. */
    public function reviewInitialByAdmin(int $id): array
    {
        $application = $this->memberships->findForReview($id);

        if ($application === null) {
            throw new RecordNotFoundException('No such registration.');
        }

        if ($application['membership_type'] !== 'home'
            || $application['status'] !== 'pending'
            || $application['moderator_id'] !== null
            || $application['division_status'] !== 'active') {
            throw new AccessDeniedException('This registration is not in the Admin initial review queue.');
        }

        return $application;
    }

    /** Approve a home registration while the division has no moderator. */
    public function approveInitialByAdmin(int $id, int $adminId): string
    {
        $application = $this->reviewInitialByAdmin($id);

        if (empty($application['nic_photo_path']) || empty($application['proof_file_path'])) {
            throw ValidationException::field('documents', 'Both the NIC photograph and proof of address must be on file before approval.');
        }

        $this->approveHomeApplication($application, $adminId, true);

        return (string) $application['full_name'];
    }

    /** Reject a home registration while the division has no moderator. */
    public function rejectInitialByAdmin(int $id, int $adminId, ?string $reason = null): string
    {
        $application = $this->reviewInitialByAdmin($id);
        $reason      = $reason === null || trim($reason) === '' ? null : trim($reason);

        if ($reason !== null && mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::field('reason', sprintf('Keep the reason to %d characters.', self::REASON_MAX));
        }

        Database::transaction($this->pdo, function () use ($application, $id, $adminId, $reason): void {
            $this->lockInitialDivision($application);
            $this->assertInitialApplicationStillPending($id);

            if (!$this->memberships->decide($id, $adminId, 'rejected', null, $reason)) {
                throw $this->alreadyDecided();
            }
        });

        return (string) $application['full_name'];
    }

    /**
     * Approve one membership: the community lets them in, the account they
     * registered with becomes one they can sign in to, and the welcome bonus
     * moves from the Sponsor Pool into their new wallet.
     *
     * @throws ValidationException when the application was already decided, or
     *                             the Sponsor Pool cannot fund the bonus
     *
     * @return string the applicant's name, for the confirmation message
     */
    public function approve(int $id, int $moderatorId): string
    {
        $application = $this->pendingOrFail($id, $moderatorId);

        if ($application['membership_type'] === 'temporary') {
            return $this->approveTemporary($application, $moderatorId);
        }

        $this->approveHomeApplication($application, $moderatorId, false);

        return (string) $application['full_name'];
    }

    /** Keep membership, account, wallet and welcome bonus changes atomic. */
    private function approveHomeApplication(array $application, int $reviewerId, bool $initialAdminReview): void
    {
        $id       = (int) $application['id'];
        $memberId = (int) $application['user_id'];

        try {
            Database::transaction($this->pdo, function () use ($application, $id, $reviewerId, $memberId, $initialAdminReview): void {
                if ($initialAdminReview) {
                    $this->lockInitialDivision($application);
                    $this->assertInitialApplicationStillPending($id);
                }

                if (!$this->memberships->decide($id, $reviewerId, 'active')) {
                    throw $this->alreadyDecided();
                }

                $this->users->markActive($memberId);
                $this->wallets->openFor($memberId);

                if (!$this->ledger->hasReceived($memberId, 'welcome_bonus')) {
                    $this->ledger->poolToMember('sponsor', $memberId, self::WELCOME_BONUS, 'welcome_bonus');
                }
            });
        } catch (InsufficientPointsException $exception) {
            throw ValidationException::field('form', sprintf(
                'The Sponsor Pool cannot fund the %d-point welcome bonus right now, so nothing was approved. '
                . 'Ask the Sponsor Liaison to record a General contribution, then approve again.',
                self::WELCOME_BONUS
            ));
        }
    }

    /**
     * Reject one application, or one extension request. A home applicant's
     * account stays pending and signs in nowhere, because `users.status` has
     * no rejected state — AuthService reads the membership for the refusal
     * message instead. A temporary applicant must be told why (Plan §19), so
     * a reason is required there; a home rejection may carry one.
     *
     * @throws ValidationException when a temporary decision has no reason
     *
     * @return string the applicant's name
     */
    public function reject(int $id, int $moderatorId, ?string $reason = null): string
    {
        $application = $this->pendingOrFail($id, $moderatorId);
        $reason      = $reason === null || trim($reason) === '' ? null : trim($reason);
        $temporary   = $application['membership_type'] === 'temporary';

        if ($temporary && $reason === null) {
            throw ValidationException::field('reason', 'Tell the member why — they see this reason and can resubmit.');
        }

        if ($reason !== null && mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::field('reason', sprintf('Keep the reason to %d characters.', self::REASON_MAX));
        }

        $decided = self::isExtension($application)
            ? $this->memberships->rejectRenewal($id, $moderatorId, (string) $reason)
            : $this->memberships->decide($id, $moderatorId, 'rejected', null, $reason);

        if (!$decided) {
            throw $this->alreadyDecided();
        }

        if ($temporary) {
            $this->notifications->push((int) $application['user_id'], 'community_rejected', [
                'title'  => (self::isExtension($application) ? 'Extension not approved: ' : 'Temporary community not approved: ')
                    . $application['division_name'],
                'detail' => 'Reason: ' . $reason,
                'icon'   => 'alert-triangle',
                'href'   => '/community/temporary',
            ]);
        }

        return (string) $application['full_name'];
    }

    /** An extension request waits on a live temporary row, not a pending one. */
    public static function isExtension(array $application): bool
    {
        return $application['membership_type'] === 'temporary'
            && $application['status'] !== 'pending'
            && $application['renewal_requested_at'] !== null;
    }

    /**
     * A temporary membership needs no account change and no welcome bonus —
     * the member is already verified at home. It runs six months from today;
     * an extension runs six months from the current expiry, or from today if
     * it had lapsed (Plan §6.5).
     *
     * @param array<string, mixed> $application
     */
    private function approveTemporary(array $application, int $moderatorId): string
    {
        $id  = (int) $application['id'];
        $now = new DateTimeImmutable();

        if (self::isExtension($application)) {
            $current = new DateTimeImmutable((string) ($application['expires_at'] ?? 'now'));
            $expiry  = CommunityService::renewedExpiry($current, $now);
            $decided = $this->memberships->approveRenewal($id, $moderatorId, $expiry->format('Y-m-d H:i:s'));
            $title   = 'Temporary membership extended: ';
        } else {
            $expiry  = CommunityService::expiryFrom($now);
            $decided = $this->memberships->decide($id, $moderatorId, 'active', $expiry->format('Y-m-d H:i:s'));
            $title   = 'Welcome to ';
        }

        if (!$decided) {
            throw $this->alreadyDecided();
        }

        $this->notifications->push((int) $application['user_id'], 'community_approved', [
            'title'  => $title . $application['division_name'],
            'detail' => 'Your temporary membership runs until ' . $expiry->format('j M Y') . '.',
            'icon'   => 'check-circle',
            'href'   => '/community/temporary',
        ]);

        return (string) $application['full_name'];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingOrFail(int $id, int $moderatorId): array
    {
        $application = $this->review($id, $moderatorId);

        if ($application['status'] !== 'pending' && !self::isExtension($application)) {
            throw $this->alreadyDecided();
        }

        return $application;
    }

    private function lockInitialDivision(array $application): void
    {
        $division = $this->divisions->lockForAppointment((int) $application['gn_division_id']);

        if ($division === null || $division['status'] !== 'active' || $division['moderator_id'] !== null) {
            throw new AccessDeniedException('This division is no longer in the Admin registration-review phase.');
        }
    }

    private function assertInitialApplicationStillPending(int $id): void
    {
        $application = $this->memberships->findForReview($id);

        if ($application === null
            || $application['membership_type'] !== 'home'
            || $application['status'] !== 'pending'
            || $application['moderator_id'] !== null) {
            throw $this->alreadyDecided();
        }
    }

    private function alreadyDecided(): ValidationException
    {
        return ValidationException::field('form', 'This application has already been decided.');
    }
}
