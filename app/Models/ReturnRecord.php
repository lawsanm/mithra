<?php

declare(strict_types=1);

/**
 * return_records — both sides' photos and notes when the item comes back,
 * and the lender's decision on its condition (Plan 3.3, §10.2). One row per
 * booking, compared against the locked handover baseline.
 */
final class ReturnRecord extends BaseModel
{
    protected string $table = 'return_records';
    protected string $columns = 'id, booking_id, return_at, lender_photos, borrower_photos, lender_notes, borrower_notes, lender_decision, borrower_decision, decided_at';

    /**
     * @return array<string, mixed>|null
     */
    public function forBooking(int $bookingId, bool $lock = false): ?array
    {
        return $this->selectOne(
            "SELECT {$this->columns} FROM return_records WHERE booking_id = :booking" . ($lock ? ' FOR UPDATE' : ''),
            ['booking' => $bookingId]
        );
    }

    /**
     * The first upload creates the row and records when the item came back
     * — the moment the late fee is measured from.
     */
    public function ensure(int $bookingId): void
    {
        $this->execute(
            'INSERT INTO return_records (booking_id, return_at) VALUES (:booking, NOW())
             ON DUPLICATE KEY UPDATE return_at = COALESCE(return_at, NOW())',
            ['booking' => $bookingId]
        );
    }

    /**
     * Replace one side's photos and note, only until the lender decides.
     *
     * @param list<string> $photos
     */
    public function saveSide(int $bookingId, string $side, array $photos, ?string $notes): bool
    {
        $side = HandoverRecord::side($side);

        return $this->execute(
            "UPDATE return_records SET {$side}_photos = :photos, {$side}_notes = :notes
              WHERE booking_id = :booking AND lender_decision IS NULL",
            ['photos' => json_encode(array_values($photos), JSON_UNESCAPED_SLASHES), 'notes' => $notes, 'booking' => $bookingId]
        ) === 1;
    }

    /**
     * The lender's decision: 'accepted', or 'claim_raised' when a damage
     * claim goes in instead (Plan 3.4).
     */
    public function decide(int $bookingId, string $decision): bool
    {
        return $this->execute(
            'UPDATE return_records SET lender_decision = :decision, decided_at = NOW()
              WHERE booking_id = :booking AND lender_decision IS NULL',
            ['decision' => $decision, 'booking' => $bookingId]
        ) === 1;
    }

    /** A withdrawn claim: the return counts as accepted after all. */
    public function reopenAsAccepted(int $bookingId): void
    {
        $this->execute(
            "UPDATE return_records SET lender_decision = 'accepted', decided_at = NOW()
              WHERE booking_id = :booking AND lender_decision = 'claim_raised'",
            ['booking' => $bookingId]
        );
    }

    /** The borrower's answer to a claim, kept on the return for the record. */
    public function borrowerDecision(int $bookingId, string $decision): void
    {
        $this->execute('UPDATE return_records SET borrower_decision = :decision WHERE booking_id = :booking', ['decision' => $decision, 'booking' => $bookingId]);
    }
}
