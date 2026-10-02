<?php

declare(strict_types=1);

/**
 * handover_records — the condition baseline both sides agree on when the item
 * changes hands (Plan 3.2, §10.1). One row per booking. Once both sides have
 * accepted, the row is the baseline a damage claim is judged against, and no
 * method here changes it again.
 */
final class HandoverRecord extends BaseModel
{
    protected string $table = 'handover_records';
    protected string $columns = 'id, booking_id, handover_at, lender_photos, borrower_photos, lender_notes, borrower_notes, lender_accepted_at, borrower_accepted_at';

    /** The two sides, whitelisted for the column names built from them. */
    public const SIDES = ['lender', 'borrower'];

    /**
     * @return array<string, mixed>|null
     */
    public function forBooking(int $bookingId, bool $lock = false): ?array
    {
        return $this->selectOne(
            "SELECT {$this->columns} FROM handover_records WHERE booking_id = :booking" . ($lock ? ' FOR UPDATE' : ''),
            ['booking' => $bookingId]
        );
    }

    /** The first upload creates the row; later calls leave it as it is. */
    public function ensure(int $bookingId): void
    {
        $this->execute(
            'INSERT INTO handover_records (booking_id) VALUES (:booking) ON DUPLICATE KEY UPDATE booking_id = booking_id',
            ['booking' => $bookingId]
        );
    }

    /**
     * Replace one side's photos and note, only while that side has not
     * accepted.
     *
     * @param list<string> $photos
     */
    public function saveSide(int $bookingId, string $side, array $photos, ?string $notes): bool
    {
        $side = self::side($side);

        return $this->execute(
            "UPDATE handover_records SET {$side}_photos = :photos, {$side}_notes = :notes
              WHERE booking_id = :booking AND {$side}_accepted_at IS NULL",
            ['photos' => json_encode(array_values($photos), JSON_UNESCAPED_SLASHES), 'notes' => $notes, 'booking' => $bookingId]
        ) === 1;
    }

    public function accept(int $bookingId, string $side): bool
    {
        $side = self::side($side);

        return $this->execute(
            "UPDATE handover_records SET {$side}_accepted_at = NOW() WHERE booking_id = :booking AND {$side}_accepted_at IS NULL",
            ['booking' => $bookingId]
        ) === 1;
    }

    /** Both sides accepted: the moment the item changed hands. */
    public function lockBaseline(int $bookingId): void
    {
        $this->execute(
            'UPDATE handover_records SET handover_at = NOW()
              WHERE booking_id = :booking AND lender_accepted_at IS NOT NULL AND borrower_accepted_at IS NOT NULL',
            ['booking' => $bookingId]
        );
    }

    /** A side name, whitelisted before it becomes part of a column name. */
    public static function side(string $side): string
    {
        if (!in_array($side, self::SIDES, true)) {
            throw new LogicException('Unknown side: ' . $side);
        }

        return $side;
    }
}
