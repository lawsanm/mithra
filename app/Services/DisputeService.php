<?php

declare(strict_types=1);

/**
 * Disputes, the member's side (Plan 3.6, §10.4, §19): the last step when a
 * damage claim's outcome is not accepted. The Admin rules on it in the admin
 * portal.
 *
 * A party to the booking may raise one:
 *   - within 7 days of accepting a simple-path claim (§10.4), or
 *   - instead of signing a moderator's recorded resolution (§19) — the claim
 *     and the booking then wait for the Admin.
 * One open dispute per claim. The raiser may change the reason until an Admin
 * picks it up, or withdraw it while it is open.
 */
final class DisputeService
{
    public const REASON_MAX = 255;

    /** Days after accepting a simple-path claim in which it can still be disputed. */
    public const WINDOW_DAYS = 7;

    public function __construct(
        private PDO $pdo,
        private Dispute $disputes,
        private DamageClaim $claims,
        private Booking $bookings,
        private User $users,
        private Notification $notifications
    ) {
    }

    /**
     * Whether this claim can be disputed now, and how — 'accepted' (the
     * simple-path window) or 'resolution' (refusing the moderator's outcome) —
     * or why not.
     *
     * @param array<string, mixed>|null $claim a latestForBooking() row
     */
    public static function route(?array $claim, int $openDisputes, DateTimeInterface $now): string
    {
        if ($claim === null) {
            return 'There is no damage claim on this booking to dispute.';
        }

        if ($openDisputes > 0) {
            return 'This claim already has an open dispute.';
        }

        if ($claim['status'] === 'closed' && $claim['borrower_response'] === 'accepted' && $claim['responded_at'] !== null) {
            $deadline = (new DateTimeImmutable((string) $claim['responded_at']))->modify('+' . self::WINDOW_DAYS . ' days');

            return $now <= $deadline ? 'accepted' : 'The 7 days to dispute an accepted claim have passed.';
        }

        if ($claim['status'] === 'pending_moderator' && $claim['met_at'] !== null && $claim['resolution_closed_at'] === null) {
            return 'resolution';
        }

        return 'This claim cannot be disputed at this stage.';
    }

    /**
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function raise(int $bookingId, int $memberId, string $reason): int
    {
        $reason = trim($reason);
        $this->checkReason($reason);

        return Database::transaction($this->pdo, function () use ($bookingId, $memberId, $reason): int {
            $booking = $this->bookings->lockForUpdate($bookingId);
            BookingService::sideOf($booking, $memberId);

            $claim = $this->claims->latestForBooking($bookingId);
            $route = self::route($claim, $claim === null ? 0 : $this->disputes->countOpenForClaim((int) $claim['id']), new DateTimeImmutable());

            if (!in_array($route, ['accepted', 'resolution'], true)) {
                throw ValidationException::field('reason', $route);
            }

            $id = $this->disputes->open($bookingId, (int) $claim['id'], $memberId, $reason);

            if ($route === 'resolution') {
                $this->claims->move((int) $claim['id'], 'pending_moderator', 'escalated');
                $this->bookings->move($bookingId, 'pending_moderator', 'escalated');
            }

            foreach ($this->users->idsInRole('admin') as $admin) {
                $this->notifications->push($admin, 'dispute_opened', [
                    'title'  => 'New dispute: ' . $booking['item_title'],
                    'detail' => $reason,
                    'icon'   => 'alert-triangle',
                    'href'   => '/admin/disputes/' . $id,
                ]);
            }

            $other = $memberId === (int) $booking['lender_id'] ? (int) $booking['borrower_id'] : (int) $booking['lender_id'];
            $this->notifications->push($other, 'dispute_opened', [
                'title'      => 'Dispute raised: ' . $booking['item_title'],
                'detail'     => 'The Admin will review the claim and rule on it.',
                'icon'       => 'alert-triangle',
                'href'       => '/bookings/' . $bookingId . '#dispute',
                'booking_id' => $bookingId,
            ]);

            return $id;
        });
    }

    /**
     * @throws ValidationException|RecordNotFoundException
     *
     * @return int the booking it belongs to
     */
    public function update(int $disputeId, int $memberId, string $reason): int
    {
        $reason = trim($reason);
        $this->checkReason($reason);

        $dispute = $this->ownOpenOrFail($disputeId, $memberId);

        if ($dispute['admin_id'] !== null || !$this->disputes->updateReason($disputeId, $reason)) {
            throw ValidationException::field('reason', 'The Admin is already reviewing this dispute, so it can no longer be changed.');
        }

        return (int) $dispute['booking_id'];
    }

    /**
     * Withdraw an open dispute. One raised instead of signing a resolution
     * hands the claim back to the moderator's resolution, still to be signed.
     *
     * @throws ValidationException|RecordNotFoundException
     *
     * @return int the booking it belongs to
     */
    public function withdraw(int $disputeId, int $memberId): int
    {
        return Database::transaction($this->pdo, function () use ($disputeId, $memberId): int {
            $dispute = $this->ownOpenOrFail($disputeId, $memberId, true);
            $this->disputes->withdraw($disputeId);

            if ($dispute['damage_claim_id'] !== null) {
                $claim = $this->claims->lockForUpdate((int) $dispute['damage_claim_id']);

                if ($claim !== null && $claim['status'] === 'escalated' && $claim['met_at'] !== null) {
                    $this->claims->move((int) $claim['id'], 'escalated', 'pending_moderator');
                    $this->bookings->move((int) $dispute['booking_id'], 'escalated', 'pending_moderator');
                }
            }

            return (int) $dispute['booking_id'];
        });
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException|RecordNotFoundException
     */
    private function ownOpenOrFail(int $disputeId, int $memberId, bool $lock = false): array
    {
        $dispute = $lock ? $this->disputes->lockForUpdate($disputeId) : $this->disputes->find($disputeId);

        if ($dispute === null || (int) $dispute['raised_by'] !== $memberId) {
            throw new RecordNotFoundException('No such dispute of yours.');
        }

        if ($dispute['status'] !== 'open') {
            throw ValidationException::field('reason', 'This dispute is closed.');
        }

        return $dispute;
    }

    /**
     * @throws ValidationException
     */
    private function checkReason(string $reason): void
    {
        if ($reason === '') {
            throw ValidationException::field('reason', 'Explain what you disagree with.');
        }

        if (mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::field('reason', sprintf('Keep the reason to %d characters.', self::REASON_MAX));
        }
    }
}
