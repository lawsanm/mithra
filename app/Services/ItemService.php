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
 * 'active' or 'rejected' is the moderator's decision (Plan §9.2, see
 * ListingReviewService), so no method here ever sets those.
 *
 * The declared value anchors pricing guidance and damage penalties, so the
 * proof it needs rises with it (Plan §9.1):
 *
 *   up to 2,000 points     photos and a description are enough
 *   2,001 to 10,000        plus a receipt, warranty card or retail price reference
 *   above 10,000           a receipt or warranty card, or an in-person inspection
 */
final class ItemService
{
    public const MAX_PHOTOS = 5;

    /** Folder under storage/uploads that item photos live in. */
    private const PHOTO_FOLDER = 'item-photos';

    /**
     * Proofs of value are kept apart from listing photos: they are shown to
     * the owner and the reviewing moderator, not to every borrower.
     */
    public const PROOF_FOLDER = 'value-proofs';

    /** Plan §9.1 thresholds, in points (proposed values, Plan §28). */
    public const PROOF_FREE_UP_TO = 2000;
    public const DOCUMENT_UP_TO   = 10000;

    /** Proof kinds a member can offer, with the words the screens use. */
    public const PROOF_TYPES = [
        'receipt'         => 'Purchase receipt',
        'warranty'        => 'Warranty card',
        'price_reference' => 'Current retail price reference',
        'inspection'      => 'In-person inspection by the moderator',
    ];

    /** Points: a plausible declared value for a household item. */
    public const MAX_DECLARED_VALUE = 1000000;

    /** Points per day or per month. */
    public const MAX_RATE = 1000000;

    /** items.title is VARCHAR(150); the description cap keeps listings readable. */
    public const NAME_MAX        = 150;
    public const DESCRIPTION_MAX = 2000;

    public const LISTING_TYPES = ['rental', 'donation'];

    /** Statuses a member may still edit. 'borrowed' is out — the item is away. */
    private const EDITABLE_STATES = ['pending_approval', 'active', 'paused', 'rejected'];

    /**
     * Changing any of these invalidates the moderator's decision, so the
     * listing goes back into the queue. A rate change alone does not.
     */
    private const APPROVAL_FIELDS = ['category_id', 'title', 'description', 'listing_type', 'declared_value', 'photos'];

    public function __construct(
        private Item $items,
        private ItemCategory $categories,
        private Booking $bookings,
        private PhotoStore $photos
    ) {
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
     * Store one proof-of-value document on its own, before the listing exists.
     *
     * @param  list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     * @return string|null the stored path, or null when nothing was uploaded
     */
    public function storeProof(array $uploads): ?string
    {
        $stored = $this->photos->storeMany($uploads, self::PROOF_FOLDER, 'value_proof', 1);

        return $stored[0] ?? null;
    }

    /**
     * What a declared value asks of the lister, in words for the form.
     */
    public static function proofRequirement(int $declaredValue): string
    {
        if ($declaredValue <= self::PROOF_FREE_UP_TO) {
            return 'Up to 2,000 points: your photos and description are enough.';
        }

        if ($declaredValue <= self::DOCUMENT_UP_TO) {
            return '2,001 to 10,000 points: add a purchase receipt, warranty card or current retail price reference.';
        }

        return 'Above 10,000 points: add a purchase receipt or warranty card, or ask your moderator to inspect the item in person.';
    }

    /**
     * Whether this proof meets the tier its declared value falls in (Plan §9.1).
     *
     * @return array<string, string> field => message; empty when it does
     */
    public static function proofErrors(int $declaredValue, ?string $proofType, ?string $proofPath): array
    {
        if ($declaredValue <= self::PROOF_FREE_UP_TO) {
            return [];
        }

        $accepted = $declaredValue <= self::DOCUMENT_UP_TO
            ? ['receipt', 'warranty', 'price_reference']
            : ['receipt', 'warranty', 'inspection'];

        if ($proofType === null || !in_array($proofType, $accepted, true)) {
            return ['value_proof_type' => self::proofRequirement($declaredValue)];
        }

        // An inspection is arranged in person, so it carries no document.
        if ($proofType !== 'inspection' && $proofPath === null) {
            return ['value_proof' => 'Upload a photo of the ' . strtolower(self::PROOF_TYPES[$proofType]) . '.'];
        }

        return [];
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

        // Proof already on file still stands unless the edit replaces it.
        $newProof = Validator::optional($input, 'value_proof_path');
        $input['value_proof_type'] = Validator::optional($input, 'value_proof_type') ?? $current['value_proof_type'];
        $input['value_proof_path'] = $newProof ?? $current['value_proof_path'];

        try {
            $clean = $this->cleanFields($input, $photoPaths);
        } catch (ValidationException $exception) {
            $this->photos->delete(...array_filter([$newProof]));

            throw $exception;
        }

        $requeued = $this->needsReapproval($current, $clean)
            || $clean['value_proof_type'] !== $current['value_proof_type']
            || $clean['value_proof_path'] !== $current['value_proof_path'];

        $this->items->updateOwned($id, $ownerId, [
            'category_id'    => $clean['category_id'],
            'title'          => $clean['title'],
            'description'    => $clean['description'],
            'listing_type'   => $clean['listing_type'],
            'declared_value' => $clean['declared_value'],
            'value_proof_type' => $clean['value_proof_type'],
            'value_proof_path' => $clean['value_proof_path'],
            'photos'         => $clean['photos'],
            'daily_rate'     => $clean['daily_rate'],
            'monthly_rate'   => $clean['monthly_rate'],
            'status'         => $requeued ? 'pending_approval' : $current['status'],
        ]);

        // Files dropped from the set are no longer reachable — delete them last,
        // so a failed UPDATE never leaves the row pointing at missing photos.
        $this->photos->delete(...array_diff(PhotoStore::paths($current['photos']), $clean['photos']));

        if ($current['value_proof_path'] !== null && $current['value_proof_path'] !== $clean['value_proof_path']) {
            $this->photos->delete((string) $current['value_proof_path']);
        }

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

        if (!in_array($listingType, self::LISTING_TYPES, true)) {
            $errors['listing_type'] = 'Choose whether this is a rental or a donation.';
        }

        $declaredValue = (int) ($input['declared_value'] ?? 0);

        if ($declaredValue < 1 || $declaredValue > self::MAX_DECLARED_VALUE) {
            $errors['declared_value'] = 'Declared value must be between 1 and '
                . number_format(self::MAX_DECLARED_VALUE) . ' points.';
        }

        $proofType = Validator::optional($input, 'value_proof_type');
        $proofPath = Validator::optional($input, 'value_proof_path');

        if ($proofType !== null && !isset(self::PROOF_TYPES[$proofType])) {
            $errors['value_proof_type'] = 'Choose the kind of proof you are offering.';
        } elseif (!isset($errors['declared_value'])) {
            $errors += self::proofErrors($declaredValue, $proofType, $proofPath);
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
            'value_proof_type' => $proofType,
            'value_proof_path' => $proofType === 'inspection' ? null : $proofPath,
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
            $before = $field === 'photos' ? PhotoStore::paths($current['photos']) : $current[$field];
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
        return (int) $value > 0 ? (int) $value : null;
    }
}
