<?php

declare(strict_types=1);

/**
 * Temporary community membership (Plan 1.2, §6.5).
 *
 * A member staying somewhere else for a while joins that GN division for six
 * months, verified by its moderator with proof of the stay. They keep their
 * home community. While active they browse, list and borrow there; at expiry
 * their listings there pause for a 14-day grace period in which fresh proof
 * extends the membership; after that it lapses and they apply again.
 *
 * The moderator's half (approve, reject, extend) lives in VerificationService
 * beside the home-membership decision it shares a queue with.
 */
final class CommunityService
{
    /** Months a temporary membership lasts, and each extension adds. */
    public const TERM_MONTHS = 6;

    /** Days after expiry in which fresh proof still extends it. */
    public const GRACE_DAYS = 14;

    /** Days before expiry the member is reminded. */
    public const REMINDER_DAYS = 14;

    /** Days after a rejection in which the page offers to resubmit (§19). */
    public const RESUBMIT_DAYS = 7;

    /** Proof of a temporary stay, with the words the form uses. */
    public const PROOF_TYPES = [
        'enrolment_letter' => 'Enrolment letter',
        'lease'            => 'Lease or rental agreement',
        'employer_letter'  => 'Employer letter',
        'other'            => 'Other proof of stay',
    ];

    public function __construct(
        private PDO $pdo,
        private User $users,
        private UserDivision $memberships,
        private GnDivision $divisions,
        private Item $items,
        private Booking $bookings,
        private Notification $notifications,
        private PhotoStore $photos
    ) {
    }

    // ── Pure date rules ─────────────────────────────────────────────────────

    /** When a membership approved now expires. */
    public static function expiryFrom(DateTimeImmutable $approvedAt): DateTimeImmutable
    {
        return $approvedAt->modify('+' . self::TERM_MONTHS . ' months');
    }

    /**
     * The new expiry after an extension: six months on from the current
     * expiry, or from today when it had already lapsed into the grace period.
     */
    public static function renewedExpiry(DateTimeImmutable $currentExpiry, DateTimeImmutable $now): DateTimeImmutable
    {
        $base = $currentExpiry > $now ? $currentExpiry : $now;

        return self::expiryFrom($base);
    }

    /** The last moment fresh proof still extends a lapsed membership. */
    public static function graceEnds(DateTimeImmutable $expiry): DateTimeImmutable
    {
        return $expiry->modify('+' . self::GRACE_DAYS . ' days');
    }

    /**
     * Why an application cannot go ahead. Pure, so the
     * refusals are testable without a database.
     *
     * @param array{account_active: bool, home_active: bool, home_division: int, division_active: bool,
     *              open_temporary: int, proof_type: string, has_proof: bool} $facts
     *
     * @return array<string, string> field => message; empty when it may proceed
     */
    public static function applicationErrors(int $divisionId, array $facts): array
    {
        if (!$facts['account_active'] || !$facts['home_active']) {
            return ['form' => 'Only a verified member with an active home community can join a temporary one.'];
        }

        $errors = [];

        if ($divisionId === $facts['home_division']) {
            $errors['division'] = 'That is already your home community.';
        } elseif (!$facts['division_active']) {
            $errors['division'] = 'Choose an active GN division from the list.';
        }

        if ($facts['open_temporary'] > 0) {
            $errors['division'] = 'You can hold one temporary community at a time. Leave or withdraw the current one first.';
        }

        if (!isset(self::PROOF_TYPES[$facts['proof_type']])) {
            $errors['proof_type'] = 'Choose what kind of proof you are sending.';
        }

        if (!$facts['has_proof']) {
            $errors['proof'] = 'Upload a photo of your proof of stay.';
        }

        return $errors;
    }

    // ── Member actions ──────────────────────────────────────────────────────

    /**
     * Apply to a division as a temporary member. The proof goes into the
     * identity documents, so the photo proxy shows it to that division's
     * moderator only (Plan §25.3).
     *
     * @param list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     *
     * @throws ValidationException
     */
    public function apply(int $userId, int $divisionId, string $proofType, array $uploads): string
    {
        $member = $this->users->findWithDivision($userId) ?? [];
        $hasUpload = array_filter($uploads, static fn (array $u): bool => ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) !== [];

        $errors = self::applicationErrors($divisionId, [
            'account_active'  => ($member['status'] ?? '') === 'active',
            'home_active'     => ($member['membership_status'] ?? '') === 'active',
            'home_division'   => (int) ($member['division_id'] ?? 0),
            'division_active' => $divisionId > 0 && $this->divisions->isActive($divisionId),
            'open_temporary'  => $this->memberships->countOpenTemporary($userId),
            'proof_type'      => $proofType,
            'has_proof'       => $hasUpload,
        ]);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $proofPath = $this->photos->storeMany($uploads, RegistrationService::DOCUMENT_FOLDER, 'proof', 1)[0];
        $existing  = $this->memberships->findFor($userId, $divisionId);

        $this->pdo->beginTransaction();

        try {
            if ($existing === null) {
                $this->memberships->createTemporary($userId, $divisionId, $proofType, $proofPath);
            } elseif (!$this->memberships->reapplyTemporary((int) $existing['id'], $proofType, $proofPath)) {
                throw ValidationException::field('division', 'You already have a membership in that division.');
            }

            $division = $this->divisions->findBasic($divisionId) ?? [];
            $this->notifyModerator($division, 'community_application', [
                'title'  => 'Temporary community request: ' . ($member['full_name'] ?? 'a member'),
                'detail' => 'Check the proof of stay and decide within the week.',
                'icon'   => 'users',
                'href'   => '/moderator/verifications?status=temporary',
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            $this->photos->delete($proofPath);

            throw $exception;
        }

        return (string) ($division['name'] ?? '');
    }

    /**
     * Fresh proof for an extension, while active or within the grace period.
     *
     * @param list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     *
     * @throws ValidationException
     */
    public function requestExtension(int $userId, array $uploads): void
    {
        $current = $this->memberships->latestTemporary($userId);

        if ($current === null || !in_array($current['status'], ['active', 'paused'], true)) {
            throw ValidationException::field('renewal_proof', 'Only an active temporary membership, or one in its grace period, can be extended.');
        }

        if ($current['renewal_requested_at'] !== null) {
            throw ValidationException::field('renewal_proof', 'Your extension is already with the moderator.');
        }

        $stored = $this->photos->storeMany($uploads, RegistrationService::DOCUMENT_FOLDER, 'renewal_proof', 1);

        if ($stored === []) {
            throw ValidationException::field('renewal_proof', 'Upload a photo of your fresh proof of stay.');
        }

        if (!$this->memberships->requestRenewal((int) $current['id'], $stored[0])) {
            $this->photos->delete($stored[0]);

            throw ValidationException::field('renewal_proof', 'This membership can no longer be extended. Apply again instead.');
        }

        $this->notifyModerator($this->divisions->findBasic((int) $current['gn_division_id']) ?? [], 'community_application', [
            'title'  => 'Temporary membership extension requested',
            'detail' => 'Fresh proof of stay is waiting in your verification queue.',
            'icon'   => 'users',
            'href'   => '/moderator/verifications?status=temporary',
        ]);
    }

    /**
     * Withdraw a pending application, or leave an active membership. Leaving
     * pauses the member's listings there, and is refused while a booking in
     * that division is still open, as account closure is (Plan §17).
     *
     * @throws ValidationException
     *
     * @return string 'withdrawn' or 'left'
     */
    public function leave(int $userId): string
    {
        $current = $this->memberships->latestTemporary($userId);

        if ($current === null || !in_array($current['status'], ['pending', 'active', 'paused'], true)) {
            throw ValidationException::field('form', 'You have no temporary community to leave.');
        }

        $divisionId = (int) $current['gn_division_id'];

        if ($current['status'] === 'pending') {
            $this->memberships->moveTemporary((int) $current['id'], ['pending'], 'deactivated');

            return 'withdrawn';
        }

        if ($this->bookings->countOpenInDivisionFor($userId, $divisionId) > 0) {
            throw ValidationException::field('form', 'You still have a booking running in ' . $current['division_name'] . '. Finish it before leaving.');
        }

        $this->pdo->beginTransaction();

        try {
            $this->memberships->moveTemporary((int) $current['id'], ['active', 'paused'], 'deactivated');
            $this->items->pauseAllInDivision($userId, $divisionId);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return 'left';
    }

    /**
     * Make the active temporary community the home one (Plan §6.5). The old
     * home row is kept, deactivated, and the member's listings there pause.
     * Refused while a booking in the old home is still open.
     *
     * @throws ValidationException
     *
     * @return string the new home division's name
     */
    public function promote(int $userId): string
    {
        $current = $this->memberships->activeTemporary($userId);
        $home    = $this->users->findWithDivision($userId);

        if ($current === null || $home === null) {
            throw ValidationException::field('form', 'Only an active temporary community can become your home.');
        }

        $oldHome = (int) $home['division_id'];

        if ($this->bookings->countOpenInDivisionFor($userId, $oldHome) > 0) {
            throw ValidationException::field('form', 'You still have a booking running in ' . $home['division_name'] . '. Finish it before moving your home.');
        }

        $this->pdo->beginTransaction();

        try {
            if (!$this->memberships->swapHome($userId, (int) $current['id'])) {
                throw ValidationException::field('form', 'Your memberships changed while you were deciding. Try again.');
            }

            $this->items->pauseAllInDivision($userId, $oldHome);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return (string) $current['division_name'];
    }

    /**
     * The division a new listing goes into: the home one, or the active
     * temporary one when the member chose it.
     *
     * @throws ValidationException when the temporary one is not active
     */
    public function listingDivision(int $userId, string $choice): int
    {
        if ($choice === 'temporary') {
            $temporary = $this->memberships->activeTemporary($userId);

            if ($temporary === null) {
                throw ValidationException::field('community', 'Your temporary community is not active, so you can only list at home.');
            }

            return (int) $temporary['gn_division_id'];
        }

        return (int) (($this->users->findWithDivision($userId) ?? [])['division_id'] ?? 0);
    }

    // ── Scheduled job ───────────────────────────────────────────────────────

    /**
     * scripts/expire_temporary_memberships.php: remind, pause, expire. Each
     * step only touches rows still in the state it looks for, so a second run
     * the same day changes nothing.
     */
    public function runExpiry(): string
    {
        $reminded = 0;
        foreach ($this->memberships->dueForReminder(self::REMINDER_DAYS) as $row) {
            $this->memberships->markReminded((int) $row['id']);
            $this->notifications->push((int) $row['user_id'], 'community_expiring', [
                'title'  => 'Your temporary membership of ' . $row['division_name'] . ' ends soon',
                'detail' => 'It expires on ' . date('j M Y', strtotime((string) $row['expires_at'])) . '. Upload fresh proof to extend it.',
                'icon'   => 'clock',
                'href'   => '/community/temporary',
            ]);
            $reminded++;
        }

        $paused = 0;
        foreach ($this->memberships->lapsed('active', 0) as $row) {
            $this->pdo->beginTransaction();

            try {
                if ($this->memberships->moveTemporary((int) $row['id'], ['active'], 'paused')) {
                    $this->items->pauseAllInDivision((int) $row['user_id'], (int) $row['gn_division_id']);
                    $this->notifications->push((int) $row['user_id'], 'community_paused', [
                        'title'  => 'Your temporary membership of ' . $row['division_name'] . ' has expired',
                        'detail' => 'Your listings there are paused. You have ' . self::GRACE_DAYS . ' days to extend it with fresh proof.',
                        'icon'   => 'alert-triangle',
                        'href'   => '/community/temporary',
                    ]);
                    $paused++;
                }

                $this->pdo->commit();
            } catch (Throwable $exception) {
                $this->pdo->rollBack();

                throw $exception;
            }
        }

        $expired = 0;
        foreach ($this->memberships->lapsed('paused', self::GRACE_DAYS) as $row) {
            if ($this->memberships->moveTemporary((int) $row['id'], ['paused'], 'expired')) {
                $this->notifications->push((int) $row['user_id'], 'community_expired', [
                    'title'  => 'Your temporary membership of ' . $row['division_name'] . ' has ended',
                    'detail' => 'The grace period passed without an extension. Apply again if you are still staying there.',
                    'icon'   => 'info',
                    'href'   => '/community/temporary',
                ]);
                $expired++;
            }
        }

        return sprintf('%d reminded, %d paused, %d expired', $reminded, $paused, $expired);
    }

    /**
     * @param array<string, mixed>                                   $division a findBasic() row
     * @param array{title:string, detail:string, icon:string, href:string} $payload
     */
    private function notifyModerator(array $division, string $type, array $payload): void
    {
        $moderatorId = (int) ($division['moderator_id'] ?? 0);

        if ($moderatorId > 0) {
            $this->notifications->push($moderatorId, $type, $payload);
        }
    }
}
