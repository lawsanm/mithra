<?php

declare(strict_types=1);

/**
 * Damage claims (Plan 3.4, §10.3–10.6).
 *
 * At return the lender can raise a claim instead of accepting the item's
 * condition. Two tracks:
 *
 *   simple     Minor damage, penalty at most 20% of the declared value
 *              (rounded in the lender's favour, §7.1). The borrower accepts
 *              or contests within 48 hours. Accepting with enough points
 *              settles it at once; anything else goes to the moderator.
 *   moderator  Everything else. The moderator meets both sides in person and
 *              records the outcome (moderator portal); then the lender and the
 *              borrower each sign off, and the points move (§10.5).
 *
 * A booking the division's moderator is party to goes to the Admin instead
 * (§10.5 step 7, Rule 1 of §16.5).
 *
 * Claim statuses move only along TRANSITIONS.
 */
final class DamageClaimService
{
    public const PHOTO_FOLDER = 'damage-evidence';

    public const SEVERITIES = ['minor', 'moderate', 'major', 'total_loss'];

    /** The simple path's cap, as a share of the declared value (§10.3). */
    public const SIMPLE_CAP_PERCENT = 20;

    /** Hours the borrower has to answer a simple-path claim. */
    public const ANSWER_HOURS = 48;

    public const DESCRIPTION_MAX = 1000;

    /** from => the statuses a claim may move to next. */
    public const TRANSITIONS = [
        'awaiting_borrower' => ['pending_moderator', 'closed', 'escalated'],
        'pending_moderator' => ['resolved', 'escalated'],
        'escalated'         => ['resolved', 'closed', 'pending_moderator'],
    ];

    public function __construct(
        private PDO $pdo,
        private Booking $bookings,
        private DamageClaim $claims,
        private ReturnRecord $returns,
        private ReturnService $returnRules,
        private Dispute $disputes,
        private GnDivision $divisions,
        private Wallet $wallets,
        private LedgerService $ledger,
        private PhotoStore $photos,
        private Notification $notifications
    ) {
    }

    // ── Pure rules ──────────────────────────────────────────────────────────

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** The largest simple-path penalty: 20% of the declared value, rounded up. */
    public static function simpleCap(int $declaredValue): int
    {
        return (int) ceil($declaredValue * self::SIMPLE_CAP_PERCENT / 100);
    }

    /**
     * Which track a claim takes (§10.3), or 'admin' when the division's
     * moderator is party to the booking.
     */
    public static function track(string $severity, int $penalty, int $declaredValue, bool $moderatorInvolved): string
    {
        if ($moderatorInvolved) {
            return 'admin';
        }

        return $severity === 'minor' && $penalty <= self::simpleCap($declaredValue) ? 'simple' : 'moderator';
    }

    /**
     * @return array<string, string>
     */
    public static function claimErrors(string $severity, int $penalty, int $declaredValue, string $description, int $photoCount): array
    {
        $errors = [];

        if (!in_array($severity, self::SEVERITIES, true)) {
            $errors['severity'] = 'Choose how bad the damage is.';
        }

        if ($penalty < 1 || $penalty > $declaredValue) {
            $errors['amount'] = sprintf('The penalty must be between 1 and the declared value, %s pts.', number_format($declaredValue));
        }

        if (trim($description) === '') {
            $errors['description'] = 'Describe what happened.';
        } elseif (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors['description'] = sprintf('Keep the description to %d characters.', self::DESCRIPTION_MAX);
        }

        $errors += array_intersect_key(HandoverService::photoCountErrors($photoCount), ['photos' => 1]);

        return $errors;
    }

    // ── Create ──────────────────────────────────────────────────────────────

    /**
     * The lender raises a claim at return instead of accepting.
     *
     * @param list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return string the track it took: simple, moderator or admin
     */
    public function raise(int $bookingId, int $lenderId, string $severity, int $penalty, string $description, array $uploads): string
    {
        $booking = $this->bookings->lockForUpdate($bookingId) ?? throw new RecordNotFoundException('No such booking.');

        if ((int) $booking['lender_id'] !== $lenderId) {
            throw new AccessDeniedException('Only the lender raises a damage claim.');
        }

        $usable = array_values(array_filter($uploads, static fn (array $u): bool => ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
        $errors = self::claimErrors($severity, $penalty, (int) $booking['declared_value'], $description, count($usable));

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stored = $this->photos->storeMany($usable, self::PHOTO_FOLDER, 'photos', HandoverService::MAX_PHOTOS, PhotoStore::stampText($lenderId, new DateTimeImmutable()));
        $track  = self::track($severity, $penalty, (int) $booking['declared_value'], (bool) $booking['moderator_involved']);

        $this->pdo->beginTransaction();

        try {
            $booking = $this->bookings->lockForUpdate($bookingId);
            $record  = $this->returns->forBooking($bookingId, true);

            if ($booking['status'] !== 'awaiting_return' || $record === null || $record['lender_decision'] !== null) {
                throw ValidationException::field('form', 'A claim can only be raised at return, before you accept the item.');
            }

            [$claimStatus, $bookingStatus] = match ($track) {
                'simple'    => ['awaiting_borrower', 'awaiting_return'],
                'moderator' => ['pending_moderator', 'pending_moderator'],
                default     => ['escalated', 'escalated'],
            };

            $claimId = $this->claims->open([
                'booking_id'       => $bookingId,
                'raised_by'        => $lenderId,
                'severity'         => $severity,
                'description'      => trim($description),
                'proposed_penalty' => $penalty,
                'evidence_photos'  => $stored,
                'track'            => $track === 'simple' ? 'simple' : 'moderator',
                'status'           => $claimStatus,
            ]);

            $this->returns->decide($bookingId, 'claim_raised');

            if ($bookingStatus !== 'awaiting_return') {
                $this->bookings->move($bookingId, 'awaiting_return', $bookingStatus);
            }

            if ($track === 'admin') {
                $this->disputes->open($bookingId, $claimId, $lenderId, 'Damage claim on a booking the division moderator is party to (Rule 1, §16.5).');
            }

            $this->notify($booking, $track, $penalty);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            foreach ($stored as $path) {
                $this->photos->delete($path);
            }

            throw $exception;
        }

        return $track;
    }

    // ── Update: the borrower answers ───────────────────────────────────────

    /**
     * The borrower accepts a simple-path claim. With enough points it settles
     * at once; without, it goes to the moderator — a member never carries
     * debt, and the moderator decides how the Reserve covers it (§19).
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return bool whether it settled at once
     */
    public function accept(int $claimId, int $borrowerId): bool
    {
        return $this->inTransaction(function () use ($claimId, $borrowerId): bool {
            [$claim, $booking] = $this->borrowerAnswerable($claimId, $borrowerId);
            $penalty = (int) $claim['proposed_penalty'];

            if (($this->wallets->lockBalance($borrowerId) ?? 0) < $penalty) {
                $this->toModerator($claim, $booking, 'accepted');

                return false;
            }

            $this->claims->move($claimId, 'awaiting_borrower', 'closed', null, 'accepted');
            $this->returns->borrowerDecision((int) $booking['id'], 'accepted');
            $this->ledger->memberToMember($borrowerId, (int) $booking['lender_id'], $penalty, 'damage_penalty', ['booking_id' => (int) $booking['id']]);
            $this->complete($booking, 'awaiting_return');

            return true;
        });
    }

    /**
     * The borrower contests: the moderator decides.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function contest(int $claimId, int $borrowerId): void
    {
        $this->inTransaction(function () use ($claimId, $borrowerId): void {
            [$claim, $booking] = $this->borrowerAnswerable($claimId, $borrowerId);
            $this->returns->borrowerDecision((int) $booking['id'], 'contested');
            $this->toModerator($claim, $booking, 'contested');
        });
    }

    /**
     * Each side agrees with the moderator's recorded resolution. When both
     * have, the recorded points move — the Reserve covers any shortfall — and
     * the booking closes (§10.5). Refusing is a dispute instead (Plan 3.6).
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return bool whether this sign-off closed the claim
     */
    public function signOff(int $claimId, int $memberId): bool
    {
        return $this->inTransaction(function () use ($claimId, $memberId): bool {
            $claim   = $this->claims->lockForUpdate($claimId) ?? throw new RecordNotFoundException('No such claim.');
            $booking = $this->bookings->lockForUpdate((int) $claim['booking_id']);
            $side    = $this->sideOf($booking, $memberId);

            if ($claim['status'] !== 'pending_moderator' || $claim['resolution_id'] === null || $claim['met_at'] === null) {
                throw ValidationException::field('form', 'There is no moderator resolution to sign yet.');
            }

            if ($claim[$side . '_signoff_at'] !== null) {
                throw ValidationException::field('form', 'You have already signed this resolution.');
            }

            $this->claims->signOff((int) $claim['resolution_id'], $side);
            $other = $side === 'lender' ? 'borrower' : 'lender';

            if ($claim[$other . '_signoff_at'] === null) {
                return false;
            }

            $penalty = (int) $claim['penalty_points'];

            if ($penalty > 0) {
                $this->ledger->chargeWithCover((int) $booking['borrower_id'], (int) $booking['lender_id'], $penalty, 'damage_penalty', (int) $booking['id']);
            }

            $this->claims->move($claimId, 'pending_moderator', 'resolved');
            $this->claims->closeResolution((int) $claim['resolution_id']);
            $this->complete($booking, 'pending_moderator');

            return true;
        });
    }

    // ── Delete: the lender withdraws ───────────────────────────────────────

    /**
     * Withdraw a simple-path claim before the borrower answers. The return
     * then counts as accepted and settles as one.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function withdraw(int $claimId, int $lenderId): void
    {
        $this->inTransaction(function () use ($claimId, $lenderId): void {
            $claim   = $this->claims->lockForUpdate($claimId) ?? throw new RecordNotFoundException('No such claim.');
            $booking = $this->bookings->lockForUpdate((int) $claim['booking_id']);

            if ((int) $booking['lender_id'] !== $lenderId) {
                throw (int) $booking['borrower_id'] === $lenderId
                    ? ValidationException::field('form', 'Only the lender withdraws a claim.')
                    : new AccessDeniedException('This claim belongs to other members.');
            }

            if ($claim['status'] !== 'awaiting_borrower') {
                throw ValidationException::field('form', 'This claim can no longer be withdrawn.');
            }

            $this->claims->move($claimId, 'awaiting_borrower', 'closed');
            $this->returns->reopenAsAccepted((int) $booking['id']);
            $this->complete($booking, 'awaiting_return');
        });
    }

    // ── Scheduled job ──────────────────────────────────────────────────────

    /**
     * scripts/escalate_unanswered_claims.php: a simple-path claim the borrower
     * has not answered in 48 hours goes to the moderator.
     */
    public function escalateUnanswered(): string
    {
        $count = 0;

        foreach ($this->claims->unansweredSince(self::ANSWER_HOURS) as $row) {
            $this->inTransaction(function () use ($row, &$count): void {
                $claim = $this->claims->lockForUpdate((int) $row['id']);

                if ($claim !== null && $claim['status'] === 'awaiting_borrower') {
                    $this->toModerator($claim, $this->bookings->lockForUpdate((int) $claim['booking_id']), null);
                    $count++;
                }
            });
        }

        return $count . ' unanswered claim' . ($count === 1 ? '' : 's') . ' sent to the moderator';
    }

    // ── Plumbing ───────────────────────────────────────────────────────────

    /**
     * Settle the buffer and late fee as at any return, close the booking and
     * put the item back on the shelf.
     *
     * @param array<string, mixed> $booking
     */
    private function complete(array $booking, string $from): void
    {
        $record  = $this->returns->forBooking((int) $booking['id']);
        $outcome = $this->returnRules->settle($booking, new DateTimeImmutable((string) ($record['return_at'] ?? 'now')));

        if (!$this->bookings->move((int) $booking['id'], $from, 'completed')) {
            throw ValidationException::field('form', 'This booking changed while you were deciding. Reload and try again.');
        }

        $this->returnRules->finish($booking, $outcome);
    }

    /**
     * @param array<string, mixed> $claim
     * @param array<string, mixed> $booking
     */
    private function toModerator(array $claim, array $booking, ?string $response): void
    {
        $this->claims->move((int) $claim['id'], 'awaiting_borrower', 'pending_moderator', 'moderator', $response);
        $this->bookings->move((int) $booking['id'], 'awaiting_return', 'pending_moderator');
        $this->notify($booking, 'moderator', (int) $claim['proposed_penalty']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    private function borrowerAnswerable(int $claimId, int $borrowerId): array
    {
        $claim   = $this->claims->lockForUpdate($claimId) ?? throw new RecordNotFoundException('No such claim.');
        $booking = $this->bookings->lockForUpdate((int) $claim['booking_id']);

        if ((int) $booking['borrower_id'] !== $borrowerId) {
            throw (int) $booking['lender_id'] === $borrowerId
                ? ValidationException::field('form', 'Only the borrower answers a claim.')
                : new AccessDeniedException('This claim belongs to other members.');
        }

        if ($claim['status'] !== 'awaiting_borrower') {
            throw ValidationException::field('form', 'This claim is no longer waiting for your answer.');
        }

        return [$claim, $booking];
    }

    /**
     * @param array<string, mixed>|null $booking
     *
     * @throws AccessDeniedException
     */
    private function sideOf(?array $booking, int $memberId): string
    {
        return match ($memberId) {
            (int) ($booking['lender_id'] ?? 0)   => 'lender',
            (int) ($booking['borrower_id'] ?? 0) => 'borrower',
            default => throw new AccessDeniedException('This claim belongs to other members.'),
        };
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function notify(array $booking, string $track, int $penalty): void
    {
        $id   = (int) $booking['id'];
        $base = ['icon' => 'alert-triangle', 'href' => '/bookings/' . $id . '#claim', 'booking_id' => $id];

        if ($track === 'simple') {
            $this->notifications->push((int) $booking['borrower_id'], 'claim_raised', $base + [
                'title'  => 'Damage claim on ' . $booking['item_title'],
                'detail' => sprintf('The lender asks for %d pts. Accept or contest within 48 hours.', $penalty),
            ]);

            return;
        }

        $moderator = $this->divisions->findBasic((int) $booking['gn_division_id'])['moderator_id'] ?? null;
        $who       = $track === 'admin' ? 'the Admin' : 'your moderator';

        foreach ([(int) $booking['borrower_id'], (int) $booking['lender_id']] as $party) {
            $this->notifications->push($party, 'claim_raised', $base + [
                'title'  => 'Damage claim with ' . $who . ': ' . $booking['item_title'],
                'detail' => 'They will arrange to meet you both in person, then each of you signs the outcome.',
            ]);
        }

        if ($track === 'moderator' && $moderator !== null) {
            $this->notifications->push((int) $moderator, 'claim_raised', [
                'title'  => 'New damage case: ' . $booking['item_title'],
                'detail' => 'Meet both members and record the outcome.',
                'href'   => '/moderator/cases',
            ] + $base);
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function inTransaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}
