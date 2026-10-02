<?php

declare(strict_types=1);

/**
 * Return photos and acceptance (Plan 3.3, §10.2) and the late-fee rules of
 * §7.6.
 *
 * Each side photographs the item when it comes back (1–5 stamped photos and
 * a note). The first upload records when it came back and moves the booking
 * to 'awaiting_return'. The lender then accepts the condition — or raises a
 * damage claim instead (DamageClaimService). Accepting settles the booking in
 * one transaction:
 *
 *   on time (by the end of the end date)  the buffer goes back to the borrower
 *   1–24 hours late                       the buffer goes to the lender
 *   more than 24 hours late               the buffer, plus one daily rate per
 *                                         extra started 24 hours (two at most),
 *                                         taken from the borrower; the Reserve
 *                                         covers any shortfall (§7.7)
 *
 * Past 72 hours with no return, the moderator is told; at 7 days a total-loss
 * claim opens on the moderator path (scripts/flag_overdue_returns.php).
 */
final class ReturnService
{
    public const PHOTO_FOLDER = 'return-photos';

    /** Hours late after which the item counts as not returned (§7.6). */
    public const NOT_RETURNED_HOURS = 72;

    /** Days late after which a total-loss claim opens (§7.6). */
    public const TOTAL_LOSS_DAYS = 7;

    /** Extra daily charges at most, on top of the buffer (the 25–72 hour band). */
    private const MAX_EXTRA_DAYS = 2;

    public function __construct(
        private PDO $pdo,
        private Booking $bookings,
        private ReturnRecord $records,
        private Item $items,
        private DamageClaim $claims,
        private GnDivision $divisions,
        private LedgerService $ledger,
        private PhotoStore $photos,
        private Notification $notifications,
        private TrustScoreService $trust,
        private Dispute $disputes,
        private User $users
    ) {
    }

    // ── Pure rules ──────────────────────────────────────────────────────────

    /**
     * Whole hours after the end of the end date, rounded up; 0 when on time.
     * The same rule User::profileStats() uses for on-time returns.
     */
    public static function hoursLate(string $endDate, DateTimeInterface $returnedAt): int
    {
        $deadline = (new DateTimeImmutable($endDate))->modify('+1 day')->getTimestamp();
        $seconds  = $returnedAt->getTimestamp() - $deadline;

        return $seconds <= 0 ? 0 : (int) ceil($seconds / 3600);
    }

    /**
     * What a return this late costs (Plan §7.6).
     *
     * @return array{buffer_to: string, extra: int} who gets the buffer, and the
     *         extra points the borrower owes the lender on top of it
     */
    public static function lateCharges(int $hoursLate, int $dailyRate): array
    {
        if ($hoursLate === 0) {
            return ['buffer_to' => 'borrower', 'extra' => 0];
        }

        if ($hoursLate <= 24) {
            return ['buffer_to' => 'lender', 'extra' => 0];
        }

        $extraDays = min(self::MAX_EXTRA_DAYS, (int) ceil(($hoursLate - 24) / 24));

        return ['buffer_to' => 'lender', 'extra' => $dailyRate * $extraDays];
    }

    /**
     * May this side change its return photos? Only while the item is out or
     * coming back, and only until the lender has decided.
     *
     * @param array<string, mixed>|null $record
     */
    public static function photosOpen(?array $record, string $bookingStatus): bool
    {
        return in_array($bookingStatus, ['in_progress', 'awaiting_return'], true)
            && ($record === null || $record['lender_decision'] === null);
    }

    // ── Member actions ──────────────────────────────────────────────────────

    /**
     * Upload (or replace) this side's return photos and note. The first upload
     * records the return time and moves the booking to 'awaiting_return'.
     *
     * @param list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function savePhotos(int $bookingId, int $memberId, array $uploads, string $note): void
    {
        $note   = trim($note);
        $stored = HandoverService::storeSidePhotos($this->photos, self::PHOTO_FOLDER, $memberId, $uploads, $note);

        $replaced = Database::transaction($this->pdo, function () use ($bookingId, $memberId, $note, $stored): array {
            $booking = $this->bookings->lockForUpdate($bookingId);
            $side    = BookingService::sideOf($booking, $memberId);
            $record  = $this->records->forBooking($bookingId, true);

            if (!self::photosOpen($record, (string) $booking['status'])) {
                throw ValidationException::field('photos', 'Return photos can no longer be changed for this booking.');
            }

            $this->records->ensure($bookingId);
            $this->records->saveSide($bookingId, $side, $stored, $note === '' ? null : $note);

            if ($booking['status'] === 'in_progress') {
                $this->bookings->move($bookingId, 'in_progress', 'awaiting_return');
            }

            $other = $side === 'lender' ? 'borrower' : 'lender';
            $this->notifications->push((int) $booking[$other . '_id'], 'return_started', [
                'title'      => 'Return photos added: ' . $booking['item_title'],
                'detail'     => $other === 'lender'
                    ? 'Check the item against the handover photos, then accept the return or raise a claim.'
                    : 'Add your own return photos so both sides are on record.',
                'icon'       => 'camera',
                'href'       => '/bookings/' . $bookingId . '#return',
                'booking_id' => $bookingId,
            ]);

            return PhotoStore::paths($record[$side . '_photos'] ?? null);
        }, fn () => $this->photos->delete(...$stored));

        $this->photos->delete(...$replaced);
    }

    /**
     * The lender accepts the item's condition: settle the buffer and any late
     * fee, complete the booking and put the item back on the shelf.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return array{hours_late: int, buffer_to: string, extra: int, covered: int}
     */
    public function accept(int $bookingId, int $lenderId): array
    {
        return Database::transaction($this->pdo, function () use ($bookingId, $lenderId): array {
            $booking = $this->bookings->lockForUpdate($bookingId);
            $record  = $this->records->forBooking($bookingId, true);

            if (BookingService::sideOf($booking, $lenderId) !== 'lender') {
                throw ValidationException::field('form', 'Only the lender accepts a return.');
            }

            if ($booking['status'] !== 'awaiting_return' || $record === null || $record['lender_decision'] !== null) {
                throw ValidationException::field('form', 'There is no return waiting for your decision.');
            }

            if (PhotoStore::paths($record['lender_photos']) === []) {
                throw ValidationException::field('form', 'Add your own return photos before you accept.');
            }

            $this->records->decide($bookingId, 'accepted');
            $outcome = $this->settle($booking, new DateTimeImmutable((string) $record['return_at']));

            if (!$this->bookings->move($bookingId, 'awaiting_return', 'completed')) {
                throw ValidationException::field('form', 'This booking changed while you were accepting. Reload and try again.');
            }

            $this->finish($booking, $outcome);

            return $outcome;
        });
    }

    /**
     * Release the buffer and charge any late fee (Plan §7.6). Called inside
     * the caller's transaction — by the return acceptance here, and by a
     * damage claim when it closes.
     *
     * @param array<string, mixed> $booking a Booking::lockForUpdate() row
     *
     * @return array{hours_late: int, buffer_to: string, extra: int, covered: int}
     */
    public function settle(array $booking, DateTimeInterface $returnedAt): array
    {
        $id      = (int) $booking['id'];
        $links   = ['booking_id' => $id];
        $hours   = self::hoursLate((string) $booking['end_date'], $returnedAt);
        $charges = self::lateCharges($hours, (int) $booking['late_buffer']);

        if ($charges['buffer_to'] === 'borrower') {
            $this->ledger->poolToMember('in_flight', (int) $booking['borrower_id'], (int) $booking['late_buffer'], 'buffer_refund', $links);
        } else {
            $this->ledger->poolToMember('in_flight', (int) $booking['lender_id'], (int) $booking['late_buffer'], 'late_fee', $links);
        }

        $covered = 0;

        if ($charges['extra'] > 0) {
            $covered = $this->ledger->chargeWithCover(
                (int) $booking['borrower_id'],
                (int) $booking['lender_id'],
                $charges['extra'],
                'late_fee',
                $id
            )['covered'];
        }

        return ['hours_late' => $hours] + $charges + ['covered' => $covered];
    }

    /**
     * After a settled return: the item goes back on the shelf, both trust
     * scores are recalculated (Plan 1.6), and both are asked to rate each
     * other.
     *
     * @param array<string, mixed>                                              $booking
     * @param array{hours_late: int, buffer_to: string, extra: int, covered: int} $outcome
     */
    public function finish(array $booking, array $outcome): void
    {
        $id = (int) $booking['id'];

        $this->items->returnToShelf((int) $booking['item_id']);

        $late = $outcome['hours_late'] === 0
            ? 'Returned on time; the late buffer went back to the borrower.'
            : sprintf('Returned %d hours late; the buffer%s went to the lender.', $outcome['hours_late'],
                $outcome['extra'] > 0 ? ' and ' . $outcome['extra'] . ' pts more' : '');

        foreach ([(int) $booking['borrower_id'], (int) $booking['lender_id']] as $party) {
            $this->trust->recalculate($party);
            $this->notifications->push($party, 'booking_completed', [
                'title'      => 'Booking complete: ' . $booking['item_title'],
                'detail'     => $late . ' Rate each other from the booking page.',
                'icon'       => 'check-circle',
                'href'       => '/bookings/' . $id,
                'booking_id' => $id,
            ]);
        }
    }

    // ── Scheduled job ──────────────────────────────────────────────────────

    /**
     * scripts/flag_overdue_returns.php. Past 72 hours late with nothing
     * returned, the division's moderator is told once; past 7 days a
     * total-loss claim opens on the moderator path and the booking waits for
     * the moderator (§7.6). Each step only touches bookings still waiting
     * for it.
     */
    public function flagOverdue(): string
    {
        $flagged = 0;

        foreach ($this->bookings->unreturnedPast(self::NOT_RETURNED_HOURS, true) as $row) {
            Database::transaction($this->pdo, function () use ($row, &$flagged): void {
                $booking = $this->unreturnedBooking((int) $row['id']);

                if ($booking === null || !$this->bookings->markOverdueFlagged((int) $booking['id'])) {
                    return;
                }

                $moderator = (int) ($this->divisions->findBasic((int) $booking['gn_division_id'])['moderator_id'] ?? 0);

                foreach (array_unique(array_filter([$moderator, (int) $booking['lender_id'], (int) $booking['borrower_id']])) as $who) {
                    $this->notifications->push($who, 'return_overdue', [
                        'title'      => 'Not returned: ' . $booking['item_title'],
                        'detail'     => 'More than 72 hours past the return date. At 7 days a total-loss claim opens for review.',
                        'icon'       => 'alert-triangle',
                        'href'       => $who === $moderator && !(bool) $booking['moderator_involved']
                            ? '/moderator/cases' : '/bookings/' . $booking['id'],
                        'booking_id' => (int) $booking['id'],
                    ]);
                }

                $flagged++;
            });
        }

        $opened = 0;

        foreach ($this->bookings->unreturnedPast(self::TOTAL_LOSS_DAYS * 24, false) as $row) {
            Database::transaction($this->pdo, function () use ($row, &$opened): void {
                $booking = $this->unreturnedBooking((int) $row['id']);

                if ($booking === null) {
                    return;
                }

                $status = (bool) $booking['moderator_involved'] ? 'escalated' : 'pending_moderator';
                $this->bookings->move((int) $booking['id'], 'in_progress', $status);
                $claimId = $this->claims->open([
                    'booking_id'       => (int) $booking['id'],
                    'raised_by'        => (int) $booking['lender_id'],
                    'severity'         => 'total_loss',
                    'description'      => 'Opened automatically: the item was not returned within 7 days of the return date.',
                    'proposed_penalty' => (int) $booking['declared_value'],
                    'track'            => 'moderator',
                    'status'           => $status,
                ]);

                if ($status === 'escalated') {
                    $disputeId = $this->disputes->open(
                        (int) $booking['id'],
                        $claimId,
                        (int) $booking['lender_id'],
                        'Automatic total-loss claim on a booking the division moderator is party to.'
                    );

                    foreach ($this->users->idsInRole('admin') as $admin) {
                        $this->notifications->push($admin, 'dispute_opened', [
                            'title'  => 'Total-loss claim: ' . $booking['item_title'],
                            'detail' => 'The item is over 7 days late. The division moderator is a party, so an Admin must review it.',
                            'icon'   => 'alert-triangle',
                            'href'   => '/admin/disputes/' . $disputeId,
                        ]);
                    }
                }

                $opened++;
            });
        }

        return sprintf('%d flagged as not returned, %d total-loss claims opened', $flagged, $opened);
    }

    /** Recheck a scheduled candidate after locking it; a return may have arrived meanwhile. */
    private function unreturnedBooking(int $bookingId): ?array
    {
        $booking = $this->bookings->lockForUpdate($bookingId);

        if ($booking === null || $booking['status'] !== 'in_progress'
            || ($this->records->forBooking($bookingId, true)['return_at'] ?? null) !== null) {
            return null;
        }

        return $booking;
    }
}
