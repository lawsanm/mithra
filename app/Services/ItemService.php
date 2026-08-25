<?php

declare(strict_types=1);

/**
 * Listing rules for the Items module (Rules/CONVENTIONS.md §6).
 *
 * Everything that could change for a business reason lives here: what a member
 * may list, when a moderator has to look at it again, and when a listing is
 * allowed to leave the shelf. Controllers do the HTTP work, models do the SQL.
 *
 * A member's own write path ends at 'pending_approval' — flipping a listing to
 * 'active' or 'rejected' is the moderator module's job (Proposal §9), so no
 * method here ever sets those.
 */
final class ItemService
{
    public const MAX_PHOTOS = 5;

    /** Folder under storage/uploads that item photos live in. */
    private const PHOTO_FOLDER = 'item-photos';

    /** Points: a plausible declared value for a household item. */
    private const MAX_DECLARED_VALUE = 1000000;

    /** Statuses a member may still edit. 'borrowed' is out — the item is away. */
    private const EDITABLE_STATES = ['pending_approval', 'active', 'paused', 'rejected'];

    /**
     * Changing any of these invalidates the moderator's decision, so the
     * listing goes back into the queue. A rate change alone does not.
     */
    private const APPROVAL_FIELDS = ['category_id', 'title', 'description', 'listing_type', 'declared_value', 'photos'];

    private Item $items;
    private ItemCategory $categories;
    private Booking $bookings;
    private PhotoStore $photos;

    public function __construct(Item $items, ItemCategory $categories, Booking $bookings, PhotoStore $photos)
    {
        $this->items      = $items;
        $this->categories = $categories;
        $this->bookings   = $bookings;
        $this->photos     = $photos;
    }

    /**
     * Store photos on their own, before the listing row exists — the create
     * wizard collects them at step 1 and submits four steps later.
     *
     * @param  list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     * @return list<string>
     */
    public function storePhotos(array $uploads, int $alreadyHeld = 0): array
    {
        $room = self::MAX_PHOTOS - $alreadyHeld;

        if ($room <= 0) {
            throw ValidationException::field('photos', sprintf('A listing carries %d photos at most.', self::MAX_PHOTOS));
        }

        return $this->photos->storeMany($uploads, self::PHOTO_FOLDER, 'photos', $room);
    }

    /**
     * Drop files that never made it onto a row — an abandoned wizard draft.
     *
     * @param list<string> $paths
     */
    public function discardPhotos(array $paths): void
    {
        foreach ($paths as $path) {
            $this->photos->delete($path);
        }
    }

    /**
     * Create a listing. It enters the moderator's queue, never the shelf.
     *
     * @param  array<string, mixed> $input validated by the controller
     * @param  list<string>         $photoPaths already stored by storePhotos()
     * @throws ValidationException
     */
    public function create(int $ownerId, int $divisionId, array $input, array $photoPaths): int
    {
        if ($divisionId <= 0) {
            throw ValidationException::field(
                'category',
                'Your home community is not confirmed yet, so you cannot list items.'
            );
        }

        $clean = $this->cleanFields($input, $photoPaths);

        return $this->items->create([
            'owner_id'         => $ownerId,
            // Never taken from the form: a member lists into their own division.
            'gn_division_id'   => $divisionId,
            'category_id'      => $clean['category_id'],
            'title'            => $clean['title'],
            'description'      => $clean['description'],
            'listing_type'     => $clean['listing_type'],
            'declared_value'   => $clean['declared_value'],
            'value_proof_type' => $clean['value_proof_type'],
            'value_proof_path' => $clean['value_proof_path'],
            'photos'           => $clean['photos'],
            'daily_rate'       => $clean['daily_rate'],
            'monthly_rate'     => $clean['monthly_rate'],
        ]);
    }

    /**
     * Edit a listing the member owns.
     *
     * @param  array<string, mixed> $input      validated by the controller
     * @param  list<string>         $photoPaths the full set the listing should end with
     * @return bool                 true when the edit sent it back for approval
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     */
    public function update(int $id, int $ownerId, array $input, array $photoPaths): bool
    {
        $current = $this->ownedOrFail($id, $ownerId);

        if (!in_array($current['status'], self::EDITABLE_STATES, true)) {
            throw ValidationException::field(
                'title',
                $current['status'] === 'borrowed'
                    ? 'This item is out on loan. You can edit it once it is back.'
                    : 'This listing can no longer be edited.'
            );
        }

        $clean = $this->cleanFields($input, $photoPaths);
        $requeued = $this->needsReapproval($current, $clean);

        $this->items->updateOwned($id, $ownerId, [
            'category_id'    => $clean['category_id'],
            'title'          => $clean['title'],
            'description'    => $clean['description'],
            'listing_type'   => $clean['listing_type'],
            'declared_value' => $clean['declared_value'],
            'photos'         => $clean['photos'],
            'daily_rate'     => $clean['daily_rate'],
            'monthly_rate'   => $clean['monthly_rate'],
            'status'         => $requeued ? 'pending_approval' : $current['status'],
        ]);

        // Files dropped from the set are no longer reachable — delete them last,
        // so a failed UPDATE never leaves the row pointing at missing photos.
        $this->discardPhotos(array_values(array_diff($this->decodePhotos($current), $clean['photos'])));

        return $requeued;
    }

    /**
     * Soft delete (§6): the row survives because bookings reference it.
     *
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     */
    public function archive(int $id, int $ownerId): void
    {
        $current = $this->ownedOrFail($id, $ownerId);

        if ($current['status'] === 'archived') {
            return;
        }

        if ($current['status'] === 'borrowed' || $this->bookings->countOpenForItem($id) > 0) {
            throw ValidationException::field(
                'status',
                'This item has a booking running. Finish or cancel it before removing the listing.'
            );
        }

        $this->items->updateOwnedStatus($id, $ownerId, 'archived');
    }

    /**
     * Take an approved listing off the shelf without losing its approval.
     *
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     */
    public function pause(int $id, int $ownerId): void
    {
        $current = $this->ownedOrFail($id, $ownerId);

        if ($current['status'] !== 'active') {
            throw ValidationException::field('status', 'Only an approved, available listing can be paused.');
        }

        $this->items->updateOwnedStatus($id, $ownerId, 'paused');
    }

    /**
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     */
    public function resume(int $id, int $ownerId): void
    {
        $current = $this->ownedOrFail($id, $ownerId);

        if ($current['status'] !== 'paused') {
            throw ValidationException::field('status', 'Only a paused listing can be put back on the shelf.');
        }

        // Approval still stands — nothing about the item changed while paused.
        $this->items->updateOwnedStatus($id, $ownerId, 'active');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AccessDeniedException|RecordNotFoundException
     */
    public function ownedOrFail(int $id, int $ownerId): array
    {
        $row = $this->items->findOwned($id, $ownerId);

        if ($row !== null) {
            return $row;
        }

        // Distinguish the two only after the ownership check, so a member can
        // never use the response to learn which ids exist (§8, fail closed).
        if ($this->items->find($id) !== null) {
            throw new AccessDeniedException('This listing belongs to another member.');
        }

        throw new RecordNotFoundException('No such listing.');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    public function decodePhotos(array $row): array
    {
        $decoded = json_decode((string) ($row['photos'] ?? '[]'), true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    /**
     * Business rules common to create and update.
     *
     * @param  array<string, mixed> $input
     * @param  list<string>         $photoPaths
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function cleanFields(array $input, array $photoPaths): array
    {
        $errors = [];

        $categoryId = (int) ($input['category'] ?? 0);

        if (!$this->categoryExists($categoryId)) {
            $errors['category'] = 'Choose a category from the list.';
        }

        $listingType = (string) ($input['listing_type'] ?? 'rental');

        if (!in_array($listingType, ['rental', 'donation'], true)) {
            $errors['listing_type'] = 'Choose whether this is a rental or a donation.';
        }

        $declaredValue = (int) ($input['declared_value'] ?? 0);

        if ($declaredValue < 1 || $declaredValue > self::MAX_DECLARED_VALUE) {
            $errors['declared_value'] = 'Declared value must be between 1 and '
                . number_format(self::MAX_DECLARED_VALUE) . ' points.';
        }

        if ($photoPaths === []) {
            $errors['photos'] = 'Add at least one photo so borrowers can see the item.';
        }

        if (count($photoPaths) > self::MAX_PHOTOS) {
            $errors['photos'] = sprintf('A listing carries %d photos at most.', self::MAX_PHOTOS);
        }

        $dailyRate   = $this->optionalRate($input['daily_rate'] ?? null);
        $monthlyRate = $this->optionalRate($input['monthly_rate'] ?? null);

        if ($listingType === 'donation') {
            // A donation moves no points, so it carries no rate (chk_rental_has_rate).
            $dailyRate   = null;
            $monthlyRate = null;
        } elseif ($dailyRate === null && $monthlyRate === null) {
            $errors['daily_rate'] = 'A rental needs a daily rate, a monthly rate, or both.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $description = trim((string) ($input['description'] ?? ''));

        return [
            'category_id'      => $categoryId,
            'title'            => trim((string) ($input['name'] ?? '')),
            'description'      => $description === '' ? null : $description,
            'listing_type'     => $listingType,
            'declared_value'   => $declaredValue,
            'value_proof_type' => $this->nullableString($input['value_proof_type'] ?? null),
            'value_proof_path' => $this->nullableString($input['value_proof_path'] ?? null),
            'photos'           => array_values($photoPaths),
            'daily_rate'       => $dailyRate,
            'monthly_rate'     => $monthlyRate,
        ];
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $clean
     */
    private function needsReapproval(array $current, array $clean): bool
    {
        if ($current['status'] === 'rejected') {
            // Re-submitting after a rejection is the whole point of editing it.
            return true;
        }

        foreach (self::APPROVAL_FIELDS as $field) {
            $before = $field === 'photos' ? $this->decodePhotos($current) : $current[$field];
            $after  = $clean[$field];

            if (is_array($before) || is_array($after)) {
                if ($before !== $after) {
                    return true;
                }

                continue;
            }

            if ((string) $before !== (string) $after) {
                return true;
            }
        }

        return false;
    }

    private function categoryExists(int $id): bool
    {
        foreach ($this->categories->allActive() as $category) {
            if ((int) $category['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    private function optionalRate(mixed $value): ?int
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '' || (int) $text < 1) {
            return null;
        }

        return (int) $text;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
