<?php

declare(strict_types=1);

/**
 * Ratings and reviews (Plan 3.5): after a booking, a cancellation, a
 * donation or a gift, each side may rate the other once — 1 to 5 stars, a
 * few quick tags and an optional comment. A rating can be edited or removed
 * for 7 days. Every change recalculates the ratee's trust score (Plan 1.6).
 */
final class RatingService
{
    public const COMMENT_MAX = 500;

    /** Days in which a rating can still be changed or removed. */
    public const EDIT_DAYS = 7;

    /** The popup's quick tags: value => label. */
    public const TAGS = [
        'as-described'  => 'Item as described',
        'smooth'        => 'Smooth handover',
        'communication' => 'Great communication',
        'flexible'      => 'Flexible timing',
        'on-time'       => 'On time',
        'careful'       => 'Took good care',
    ];

    public function __construct(
        private Rating $ratings,
        private Booking $bookings,
        private Donation $donations,
        private Gift $gifts,
        private TrustScoreService $trust
    ) {
    }

    // ── Pure rules ──────────────────────────────────────────────────────────

    /**
     * @param list<string> $tags
     *
     * @return array<string, string>
     */
    public static function inputErrors(int $stars, string $comment, array $tags): array
    {
        $errors = [];

        if ($stars < 1 || $stars > 5) {
            $errors['rating'] = 'Choose from 1 to 5 stars.';
        }

        if (mb_strlen($comment) > self::COMMENT_MAX) {
            $errors['review'] = sprintf('Keep the review to %d characters.', self::COMMENT_MAX);
        }

        if (array_diff($tags, array_keys(self::TAGS)) !== []) {
            $errors['tags'] = 'Choose tags from the list.';
        }

        return $errors;
    }

    public static function stillEditable(string $createdAt, DateTimeInterface $now): bool
    {
        return (new DateTimeImmutable($createdAt))->modify('+' . self::EDIT_DAYS . ' days') >= $now;
    }

    /**
     * Who this member rates for this record, and in what context — or why
     * they cannot. Pure: the caller passes the record's facts.
     *
     * @param array<string, mixed>|null $record booking: borrower_id, lender_id, status;
     *                                          donation: donor_id, recipient_id, status;
     *                                          gift: sender_id, recipient_id
     *
     * @return array{ratee: int, context: string}|string the subject, or the refusal
     */
    public static function subject(string $kind, ?array $record, int $raterId): array|string
    {
        if ($record === null) {
            return 'That record does not exist.';
        }

        [$a, $b] = match ($kind) {
            'booking'  => [(int) $record['borrower_id'], (int) $record['lender_id']],
            'donation' => [(int) $record['donor_id'], (int) ($record['recipient_id'] ?? 0)],
            default    => [(int) $record['sender_id'], (int) $record['recipient_id']],
        };

        if (!in_array($raterId, [$a, $b], true)) {
            return 'You can only rate records you took part in.';
        }

        $context = match ($kind) {
            'booking'  => match ((string) $record['status']) {
                'completed'                   => 'rental',
                'cancelled', 'auto_cancelled' => 'cancellation',
                default                       => null,
            },
            'donation' => $record['status'] === 'completed' ? 'donation' : null,
            default    => 'gift',
        };

        if ($context === null) {
            return 'You can rate this once it has finished.';
        }

        return ['ratee' => $raterId === $a ? $b : $a, 'context' => $context];
    }

    // ── Create, update, delete ─────────────────────────────────────────────

    /**
     * @param list<string> $tags
     *
     * @throws ValidationException
     */
    public function rate(int $raterId, string $kind, int $recordId, int $stars, string $comment, array $tags): void
    {
        if (!in_array($kind, Rating::KINDS, true)) {
            throw ValidationException::field('form', 'Choose something to rate.');
        }

        $subject = self::subject($kind, $this->record($kind, $recordId), $raterId);

        if (is_string($subject)) {
            throw ValidationException::field('form', $subject);
        }

        if ($this->ratings->byRater($raterId, $kind, $recordId) !== null) {
            throw ValidationException::field('form', 'You have already rated this. You can edit your rating for 7 days.');
        }

        $comment = trim($comment);
        $errors  = self::inputErrors($stars, $comment, $tags);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->ratings->create([
            'kind'      => $kind,
            'record_id' => $recordId,
            'rater_id'  => $raterId,
            'ratee_id'  => $subject['ratee'],
            'context'   => $subject['context'],
            'stars'     => $stars,
            'comment'   => $comment === '' ? null : $comment,
            'tags'      => array_values(array_unique($tags)),
        ]);

        $this->trust->recalculate($subject['ratee']);
    }

    /**
     * @param list<string> $tags
     *
     * @throws ValidationException|RecordNotFoundException
     */
    public function update(int $ratingId, int $raterId, int $stars, string $comment, array $tags): void
    {
        $rating  = $this->editableOrFail($ratingId, $raterId);
        $comment = trim($comment);
        $errors  = self::inputErrors($stars, $comment, $tags);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->ratings->updateOwned($ratingId, $raterId, $stars, $comment === '' ? null : $comment, array_values(array_unique($tags)));
        $this->trust->recalculate((int) $rating['ratee_id']);
    }

    /**
     * @throws ValidationException|RecordNotFoundException
     */
    public function delete(int $ratingId, int $raterId): void
    {
        $rating = $this->editableOrFail($ratingId, $raterId);
        $this->ratings->deleteOwned($ratingId, $raterId);
        $this->trust->recalculate((int) $rating['ratee_id']);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException|RecordNotFoundException
     */
    public function editableOrFail(int $ratingId, int $raterId): array
    {
        $rating = $this->ratings->findOwned($ratingId, $raterId);

        if ($rating === null) {
            throw new RecordNotFoundException('No such rating of yours.');
        }

        if (!self::stillEditable((string) $rating['created_at'], new DateTimeImmutable())) {
            throw ValidationException::field('form', 'Ratings can only be changed for 7 days.');
        }

        return $rating;
    }

    /**
     * The record behind a rating, as subject() needs it.
     *
     * @return array<string, mixed>|null
     */
    public function record(string $kind, int $recordId): ?array
    {
        return match ($kind) {
            'booking'  => $this->bookings->find($recordId),
            'donation' => $this->donations->find($recordId),
            'gift'     => $this->gifts->find($recordId),
            default    => null,
        };
    }
}
