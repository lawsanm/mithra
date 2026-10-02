<?php

declare(strict_types=1);

/**
 * Availability calendar (Plan 2.4): a lender blocks date ranges on their own
 * item, and borrowers cannot request those dates.
 *
 * A block may not cover dates already promised to a borrower: an accepted or
 * running booking holds its dates. The overlap rule here is the one booking
 * requests use too, so a block and a booking can never disagree about what
 * "the same day" means.
 */
final class AvailabilityService
{
    public const NOTE_MAX = 255;

    public function __construct(
        private Item $items,
        private ItemAvailabilityBlock $blocks,
        private Booking $bookings
    ) {
    }

    /**
     * Do two inclusive date ranges share at least one day? Dates are Y-m-d,
     * which compare correctly as strings.
     */
    public static function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    /**
     * The range rules every block and booking request shares: real dates, end
     * on or after start, not starting in the past.
     *
     * @return array<string, string> field => message; empty when the range is fine
     */
    public static function rangeErrors(string $start, string $end, string $today): array
    {
        if (!Validator::isDate($start)) {
            return ['start_date' => 'Enter a real start date.'];
        }

        if (!Validator::isDate($end)) {
            return ['end_date' => 'Enter a real end date.'];
        }

        if ($start < $today) {
            return ['start_date' => 'The start date has already passed.'];
        }

        if ($end < $start) {
            return ['end_date' => 'The end date must be on or after the start date.'];
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upcoming(int $itemId): array
    {
        return $this->blocks->upcomingForItem($itemId);
    }

    /**
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     */
    public function create(int $itemId, int $ownerId, string $start, string $end, string $note): int
    {
        $this->ownedItemOrFail($itemId, $ownerId);
        $this->check($itemId, $start, $end, $note, 0);

        return $this->blocks->create($itemId, $start, $end, $note === '' ? null : $note);
    }

    /**
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     *
     * @return int the item the block belongs to
     */
    public function update(int $blockId, int $ownerId, string $start, string $end, string $note): int
    {
        $block = $this->ownedBlockOrFail($blockId, $ownerId);
        $this->check((int) $block['item_id'], $start, $end, $note, $blockId);
        $this->blocks->update($blockId, $start, $end, $note === '' ? null : $note);

        return (int) $block['item_id'];
    }

    /**
     * The item a block belongs to, once the block is known to be this lender's.
     *
     * @throws AccessDeniedException|RecordNotFoundException
     */
    public function itemOf(int $blockId, int $ownerId): int
    {
        return (int) $this->ownedBlockOrFail($blockId, $ownerId)['item_id'];
    }

    /**
     * @throws AccessDeniedException|RecordNotFoundException
     *
     * @return int the item the block belonged to
     */
    public function delete(int $blockId, int $ownerId): int
    {
        $block = $this->ownedBlockOrFail($blockId, $ownerId);
        $this->blocks->delete($blockId);

        return (int) $block['item_id'];
    }

    /**
     * Is any of the range blocked by the lender? Booking requests ask this.
     */
    public function isBlocked(int $itemId, string $start, string $end): bool
    {
        return $this->blocks->countOverlapping($itemId, $start, $end) > 0;
    }

    /**
     * @throws ValidationException
     */
    private function check(int $itemId, string $start, string $end, string $note, int $exceptId): void
    {
        $errors = self::rangeErrors($start, $end, date('Y-m-d'));

        if (mb_strlen($note) > self::NOTE_MAX) {
            $errors['note'] = sprintf('Keep the note to %d characters.', self::NOTE_MAX);
        }

        if ($errors === [] && $this->bookings->countOverlapping($itemId, $start, $end, Booking::DATE_HOLDING_STATES) > 0) {
            $errors['start_date'] = 'A borrower already has the item on some of those dates.';
        }

        if ($errors === [] && $this->blocks->countOverlapping($itemId, $start, $end, $exceptId) > 0) {
            $errors['start_date'] = 'Those dates overlap a range you already blocked.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @throws AccessDeniedException|RecordNotFoundException
     */
    private function ownedItemOrFail(int $itemId, int $ownerId): void
    {
        $item = $this->items->findOwned($itemId, $ownerId);

        if ($item !== null) {
            if ($item['listing_type'] !== 'rental' || $item['status'] === 'archived') {
                throw ValidationException::field('start_date', 'Only a rental listing has a calendar.');
            }

            return;
        }

        if ($this->items->find($itemId) !== null) {
            throw new AccessDeniedException('This listing belongs to another member.');
        }

        throw new RecordNotFoundException('No such listing.');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AccessDeniedException|RecordNotFoundException
     */
    private function ownedBlockOrFail(int $blockId, int $ownerId): array
    {
        $block = $this->blocks->findWithOwner($blockId);

        if ($block === null) {
            throw new RecordNotFoundException('No such blocked range.');
        }

        if ((int) $block['owner_id'] !== $ownerId) {
            throw new AccessDeniedException('This calendar belongs to another member.');
        }

        return $block;
    }
}
