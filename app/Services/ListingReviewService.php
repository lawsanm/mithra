<?php

declare(strict_types=1);

/**
 * Declared-value validation: the reviewer's decision on a new or edited
 * listing (Plan §9.2, module 2.1).
 *
 * The reviewer can approve the listing, adjust its declared value with a reason
 * the lender sees, or reject it with a reason. Every decision is written to the
 * listing's append-only audit trail and notified to the lender, in one
 * transaction.
 *
 * Who reviews (Plan §16.4, §16.5):
 *   - the moderator of the listing's division, for everyone else's listings;
 *   - the Admin, for a moderator's own listings and for a division that has no
 *     moderator yet (the Admin is interim moderator, §16.1).
 * A moderator never validates their own listing, and never acts outside their
 * division. Ids from the URL are never trusted (§8).
 */
final class ListingReviewService
{
    /** Queue filters, as the pills on the screen offer them. */
    public const FILTERS = ['', 'pending', 'approved', 'rejected'];

    public const DECISIONS = ['approve', 'adjust', 'reject'];

    private const REASON_MAX = 255;

    public function __construct(
        private PDO $pdo,
        private Item $items,
        private ItemValueReview $reviews,
        private GnDivision $divisions,
        private Notification $notifications,
        private Donation $donations
    ) {
    }

    /**
     * Every listing this reviewer may decide, optionally filtered.
     *
     * @throws AccessDeniedException when a moderator account moderates no division
     *
     * @return list<array<string, mixed>>
     */
    public function queue(int $reviewerId, string $role, string $filter): array
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : '';

        $rows = $role === 'admin'
            ? $this->items->reviewQueue([], $filter)
            : $this->items->reviewQueue([$this->divisionFor($reviewerId)], $filter);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->mayDecide($row, $reviewerId, $role)
        ));
    }

    /**
     * One listing this reviewer is entitled to see, with its audit trail.
     *
     * @throws RecordNotFoundException|AccessDeniedException
     *
     * @return array{listing: array<string, mixed>, trail: list<array<string, mixed>>}
     */
    public function review(int $id, int $reviewerId, string $role): array
    {
        $listing = $this->items->findForReview($id);

        if ($listing === null) {
            throw new RecordNotFoundException('No such listing.');
        }

        if (!$this->mayDecide($listing, $reviewerId, $role)) {
            throw new AccessDeniedException('This listing is reviewed by someone else.');
        }

        return ['listing' => $listing, 'trail' => $this->reviews->forItem($id)];
    }

    /**
     * Approve, adjust or reject.
     *
     * @param int|null    $adjustedValue the corrected declared value, for 'adjust'
     * @param string|null $reason        required for 'adjust' and 'reject'
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return string the listing's title, for the confirmation message
     */
    public function decide(int $id, int $reviewerId, string $role, string $decision, ?int $adjustedValue, ?string $reason): string
    {
        $listing = $this->review($id, $reviewerId, $role)['listing'];
        $reason  = $reason === null || trim($reason) === '' ? null : trim($reason);

        if ($listing['status'] !== 'pending_approval') {
            throw ValidationException::field('form', 'This listing has already been decided.');
        }

        $this->validate($decision, (int) $listing['declared_value'], $adjustedValue, $reason);

        $previous = (int) $listing['declared_value'];
        [$status, $valueStatus, $newValue, $logged] = match ($decision) {
            'approve' => ['active', 'validated', $previous, 'approved'],
            'adjust'  => ['active', 'adjusted', (int) $adjustedValue, 'adjusted'],
            default   => ['rejected', 'rejected', $previous, 'rejected'],
        };

        $this->pdo->beginTransaction();

        try {
            if (!$this->items->recordReview($id, $status, $valueStatus, $newValue, $reviewerId)) {
                throw ValidationException::field('form', 'This listing has already been decided.');
            }

            $this->reviews->record([
                'item_id'        => $id,
                'reviewer_id'    => $reviewerId,
                'decision'       => $logged,
                'previous_value' => $previous,
                'new_value'      => $newValue,
                'reason'         => $reason,
            ]);

            // An approved donation listing opens for requests at once (Plan §13.1).
            if ($status === 'active' && $listing['listing_type'] === 'donation'
                && !$this->donations->hasLiveForItem($id)) {
                $this->donations->openFor($id, (int) $listing['owner_id']);
            }

            $this->notifications->push((int) $listing['owner_id'], 'listing_' . $logged, $this->notice(
                (string) $listing['title'],
                $logged,
                $previous,
                $newValue,
                $reason,
                $id
            ));

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return (string) $listing['title'];
    }

    /**
     * The rule of §16.4–16.5 in one place.
     *
     * @param array<string, mixed> $listing needs owner_id and moderator_id
     */
    private function mayDecide(array $listing, int $reviewerId, string $role): bool
    {
        $ownerId     = (int) $listing['owner_id'];
        $moderatorId = $listing['moderator_id'] === null ? null : (int) $listing['moderator_id'];

        if ($role === 'admin') {
            return $moderatorId === null || $moderatorId === $ownerId;
        }

        return $role === 'moderator' && $moderatorId === $reviewerId && $ownerId !== $reviewerId;
    }

    private function divisionFor(int $moderatorId): int
    {
        $divisionId = $this->divisions->moderatedBy($moderatorId);

        if ($divisionId === null) {
            throw new AccessDeniedException('This account does not moderate a division.');
        }

        return $divisionId;
    }

    /**
     * @throws ValidationException
     */
    private function validate(string $decision, int $current, ?int $adjustedValue, ?string $reason): void
    {
        $errors = [];

        if (!in_array($decision, self::DECISIONS, true)) {
            $errors['decision'] = 'Choose approve, adjust or reject.';
        }

        if ($decision === 'adjust') {
            if ($adjustedValue === null || $adjustedValue < 1) {
                $errors['declared_value'] = 'Enter the corrected declared value in points.';
            } elseif ($adjustedValue === $current) {
                $errors['declared_value'] = 'The corrected value is the same as the declared one — approve it instead.';
            }
        }

        if (in_array($decision, ['adjust', 'reject'], true) && $reason === null) {
            $errors['reason'] = 'Give the lender a reason — they will see it.';
        }

        if ($reason !== null && mb_strlen($reason) > self::REASON_MAX) {
            $errors['reason'] = sprintf('Keep the reason to %d characters.', self::REASON_MAX);
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @return array{title:string, detail:string, icon:string, href:string}
     */
    private function notice(string $title, string $decision, int $previous, int $newValue, ?string $reason, int $id): array
    {
        return match ($decision) {
            'approved' => [
                'title'  => 'Listing approved: ' . $title,
                'detail' => 'Your listing is now visible to members of your division.',
                'icon'   => 'check-circle',
                'item_id' => $id,
                'href'   => '/items/' . $id . '/edit',
            ],
            'adjusted' => [
                'title'  => 'Listing approved with a new value: ' . $title,
                'detail' => sprintf(
                    'Declared value changed from %s to %s pts. Reason: %s',
                    number_format($previous),
                    number_format($newValue),
                    (string) $reason
                ),
                'icon'   => 'check-circle',
                'item_id' => $id,
                'href'   => '/items/' . $id . '/edit',
            ],
            default => [
                'title'  => 'Listing not approved: ' . $title,
                'detail' => 'Reason: ' . (string) $reason,
                'icon'   => 'alert-triangle',
                'item_id' => $id,
                'href'   => '/items/' . $id . '/edit',
            ],
        };
    }
}
