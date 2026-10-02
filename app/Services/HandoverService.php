<?php

declare(strict_types=1);

/**
 * Handover photos and acceptance (Plan 3.2, §10.1).
 *
 * At handover each side photographs the item — 1 to 5 photos stamped with the
 * time and the uploader — and writes a condition note. Each side accepts once
 * their own photos are in. When both have accepted, in one transaction:
 *
 *   - the record becomes the locked baseline a return is compared against;
 *   - the rental charge moves from In-Flight to the lender ('rental_payout');
 *   - the booking is 'in_progress' and the item 'borrowed'.
 *
 * Until then either side may cancel with a full refund (BookingService), and
 * if the two have not both accepted 48 hours after the start date, the
 * booking auto-cancels.
 */
final class HandoverService
{
    public const PHOTO_FOLDER = 'handover-photos';
    public const MIN_PHOTOS   = 1;
    public const MAX_PHOTOS   = 5;
    public const NOTE_MAX     = 1000;

    /** Hours after the start date before an unfinished handover auto-cancels. */
    public const AUTO_CANCEL_HOURS = 48;

    public function __construct(
        private PDO $pdo,
        private Booking $bookings,
        private HandoverRecord $records,
        private Item $items,
        private LedgerService $ledger,
        private BookingService $bookingRules,
        private PhotoStore $photos,
        private Notification $notifications
    ) {
    }

    /**
     * How many photos a side may keep (Plan §10.1: 1–5).
     *
     * @return array<string, string>
     */
    public static function photoCountErrors(int $count): array
    {
        if ($count < self::MIN_PHOTOS) {
            return ['photos' => 'Add at least one photo of the item.'];
        }

        if ($count > self::MAX_PHOTOS) {
            return ['photos' => sprintf('Upload %d photos at most.', self::MAX_PHOTOS)];
        }

        return [];
    }

    /**
     * May this side still change or accept its half? Only before it accepted,
     * only while the booking waits for the handover, and never once both have
     * locked the baseline.
     *
     * @param array<string, mixed>|null $record
     */
    public static function sideOpen(?array $record, string $side, string $bookingStatus): bool
    {
        if ($bookingStatus !== 'awaiting_handover') {
            return false;
        }

        return $record === null || $record[$side . '_accepted_at'] === null;
    }

    /**
     * Upload (or replace) this side's photos and note.
     *
     * @param list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function savePhotos(int $bookingId, int $memberId, array $uploads, string $note): void
    {
        $note = trim($note);

        if (mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::field('note', sprintf('Keep the note to %d characters.', self::NOTE_MAX));
        }

        $usable = array_filter($uploads, static fn (array $u): bool => ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        $errors = self::photoCountErrors(count($usable));

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stamp  = PhotoStore::stampText($memberId, new DateTimeImmutable());
        $stored = $this->photos->storeMany($usable, self::PHOTO_FOLDER, 'photos', self::MAX_PHOTOS, $stamp);
        $old    = [];

        $this->pdo->beginTransaction();

        try {
            [$booking, $side] = $this->partyOrFail($bookingId, $memberId);
            $this->records->ensure($bookingId);
            $record = $this->records->forBooking($bookingId, true);

            if (!self::sideOpen($record, $side, (string) $booking['status'])) {
                throw ValidationException::field('photos', 'You have already accepted the handover, so your photos are locked.');
            }

            $old = self::decode($record[$side . '_photos'] ?? null);
            $this->records->saveSide($bookingId, $side, $stored, $note === '' ? null : $note);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            foreach ($stored as $path) {
                $this->photos->delete($path);
            }

            throw $exception;
        }

        // Replaced photos are no longer on any record — remove them last.
        foreach ($old as $path) {
            $this->photos->delete($path);
        }
    }

    /**
     * Accept the handover. The second acceptance locks the baseline and pays
     * the lender.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return bool whether this acceptance completed the handover
     */
    public function accept(int $bookingId, int $memberId): bool
    {
        $this->pdo->beginTransaction();

        try {
            [$booking, $side] = $this->partyOrFail($bookingId, $memberId);
            $record = $this->records->forBooking($bookingId, true);

            if (!self::sideOpen($record, $side, (string) $booking['status'])) {
                throw ValidationException::field('form', 'There is no handover waiting for your acceptance.');
            }

            if (self::decode($record[$side . '_photos'] ?? null) === []) {
                throw ValidationException::field('form', 'Upload your photos of the item before you accept.');
            }

            $this->records->accept($bookingId, $side);

            $other     = $side === 'lender' ? 'borrower' : 'lender';
            $completed = $record[$other . '_accepted_at'] !== null;

            if ($completed) {
                $this->records->lockBaseline($bookingId);
                $this->ledger->poolToMember('in_flight', (int) $booking['lender_id'], (int) $booking['rental_charge'], 'rental_payout', ['booking_id' => $bookingId]);

                if (!$this->bookings->move($bookingId, 'awaiting_handover', 'in_progress')) {
                    throw ValidationException::field('form', 'This booking changed while you were accepting. Reload and try again.');
                }

                $this->items->updateOwnedStatus((int) $booking['item_id'], (int) $booking['lender_id'], 'borrowed');
            }

            $this->notifications->push((int) $booking[$other . '_id'], $completed ? 'handover_complete' : 'handover_ready', [
                'title'      => $completed ? 'Handover complete: ' . $booking['item_title'] : 'Accept the handover of ' . $booking['item_title'],
                'detail'     => $completed
                    ? 'Both of you accepted the condition photos. Return by ' . date('j M Y', strtotime((string) $booking['end_date'])) . '.'
                    : 'The other side has uploaded photos and accepted. Add yours and accept.',
                'icon'       => $completed ? 'check-circle' : 'camera',
                'href'       => '/bookings/' . $bookingId,
                'booking_id' => $bookingId,
            ]);

            $this->pdo->commit();

            return $completed;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * Who has accepted so far, for the page's polling (§21.1).
     *
     * @throws RecordNotFoundException|AccessDeniedException
     *
     * @return array{status: string, lender_accepted: bool, borrower_accepted: bool}
     */
    public function status(int $bookingId, int $memberId): array
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null) {
            throw new RecordNotFoundException('No such booking.');
        }

        if (!in_array($memberId, [(int) $booking['lender_id'], (int) $booking['borrower_id']], true)) {
            throw new AccessDeniedException('This booking belongs to other members.');
        }

        $record = $this->records->forBooking($bookingId);

        return [
            'status'            => (string) $booking['status'],
            'lender_accepted'   => ($record['lender_accepted_at'] ?? null) !== null,
            'borrower_accepted' => ($record['borrower_accepted_at'] ?? null) !== null,
        ];
    }

    /**
     * scripts/auto_cancel_handovers.php: 48 hours after the start date with
     * the handover still unfinished, the booking auto-cancels and the borrower
     * gets everything back. Only bookings still waiting are touched.
     */
    public function autoCancelStale(): string
    {
        $count = 0;

        foreach ($this->bookings->staleHandovers(self::AUTO_CANCEL_HOURS) as $stale) {
            $this->pdo->beginTransaction();

            try {
                $booking = $this->bookings->lockForUpdate((int) $stale['id']);

                if ($booking !== null && $booking['status'] === 'awaiting_handover') {
                    $this->bookingRules->refundAndClose($booking, 'auto_cancelled', null);

                    foreach ([(int) $booking['borrower_id'], (int) $booking['lender_id']] as $party) {
                        $this->notifications->push($party, 'booking_auto_cancelled', [
                            'title'      => 'Booking cancelled: ' . $booking['item_title'],
                            'detail'     => 'The handover was not completed within 48 hours of the start date. The borrower was refunded in full.',
                            'icon'       => 'clock',
                            'href'       => '/bookings/' . $booking['id'],
                            'booking_id' => (int) $booking['id'],
                        ]);
                    }

                    $count++;
                }

                $this->pdo->commit();
            } catch (Throwable $exception) {
                $this->pdo->rollBack();

                throw $exception;
            }
        }

        return $count . ' unfinished handover' . ($count === 1 ? '' : 's') . ' auto-cancelled';
    }

    /**
     * @return list<string>
     */
    public static function decode(mixed $json): array
    {
        $decoded = json_decode((string) ($json ?? '[]'), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * The locked booking and which side this member is on.
     *
     * @return array{0: array<string, mixed>, 1: string}
     *
     * @throws RecordNotFoundException|AccessDeniedException
     */
    private function partyOrFail(int $bookingId, int $memberId): array
    {
        $booking = $this->bookings->lockForUpdate($bookingId);

        if ($booking === null) {
            throw new RecordNotFoundException('No such booking.');
        }

        $side = match ($memberId) {
            (int) $booking['lender_id']   => 'lender',
            (int) $booking['borrower_id'] => 'borrower',
            default                       => null,
        };

        if ($side === null) {
            throw new AccessDeniedException('This booking belongs to other members.');
        }

        return [$booking, $side];
    }
}
