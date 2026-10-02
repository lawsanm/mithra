<?php

declare(strict_types=1);

/**
 * Aid grants, the member's side (Plan 4.4, §12): asking the community Aid
 * Pool for help with an essential need.
 *
 *   request → the division's moderator vouches → the Sponsor Liaison approves,
 *   adjusts, rejects or asks for more → points move from the Aid Pool
 *
 * The vouch and the decision happen in the moderator and liaison portals. A
 * member may hold one live grant at a time, wait 60 days after a grant before
 * asking again, and receive at most 500 points a year (§12.1). A request can
 * be changed until the moderator vouches, answered when the Liaison asks a
 * question, and withdrawn while it is still being considered.
 */
final class AidGrantService
{
    public const YEARLY_CAP    = 500;
    public const COOLING_DAYS  = 60;
    public const DETAILS_MAX   = 2000;
    public const REPLY_MAX     = 2000;
    public const PHOTO_FOLDER  = 'aid-evidence';
    public const MAX_PHOTOS    = 5;

    public const PURPOSES = [
        'School supplies',
        'Medical costs',
        'Household essentials',
        'Disaster recovery',
        'Other essential need',
    ];

    public function __construct(
        private PDO $pdo,
        private AidGrant $grants,
        private User $users,
        private GnDivision $divisions,
        private PhotoStore $photos,
        private Notification $notifications
    ) {
    }

    /**
     * Whether this member may ask now, and if not why — the cooling and cap
     * messages on the request page. Pure.
     *
     * @return array<string, string>
     */
    public static function eligibilityErrors(int $liveGrants, ?string $lastGrantedAt, int $usedThisYear, DateTimeInterface $now): array
    {
        if ($liveGrants > 0) {
            return ['form' => 'You already have an aid request or grant in progress.'];
        }

        if ($lastGrantedAt !== null) {
            $again = (new DateTimeImmutable($lastGrantedAt))->modify('+' . self::COOLING_DAYS . ' days');

            if ($again > $now) {
                return ['form' => 'You can ask again from ' . $again->format('j M Y') . ', 60 days after your last grant.'];
            }
        }

        if ($usedThisYear >= self::YEARLY_CAP) {
            return ['form' => sprintf('You have reached this year’s %d-point aid limit.', self::YEARLY_CAP)];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function requestErrors(int $amount, string $purpose, string $details, int $usedThisYear): array
    {
        $errors    = [];
        $remaining = max(0, self::YEARLY_CAP - $usedThisYear);

        if ($amount < 1) {
            $errors['amount'] = 'Ask for at least 1 point.';
        } elseif ($amount > $remaining) {
            $errors['amount'] = sprintf('You can ask for up to %d more points this year.', $remaining);
        }

        if (!in_array($purpose, self::PURPOSES, true)) {
            $errors['purpose'] = 'Choose a purpose from the list.';
        }

        if ($details === '') {
            $errors['details'] = 'Tell your moderator what the points are for.';
        } elseif (mb_strlen($details) > self::DETAILS_MAX) {
            $errors['details'] = sprintf('Keep it to %d characters.', self::DETAILS_MAX);
        }

        return $errors;
    }

    /**
     * What the request page says before the form, or '' when the member may ask.
     */
    public function eligibility(int $memberId): string
    {
        return implode(' ', self::eligibilityErrors(
            $this->grants->countLiveFor($memberId),
            $this->grants->lastGrantedAt($memberId),
            $this->grants->usedThisYear($memberId),
            new DateTimeImmutable()
        ));
    }

    public function remainingThisYear(int $memberId): int
    {
        return max(0, self::YEARLY_CAP - $this->grants->usedThisYear($memberId));
    }

    /**
     * @param list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     *
     * @throws ValidationException
     */
    public function request(int $memberId, int $amount, string $purpose, string $details, array $uploads): int
    {
        $member = $this->users->findWithDivision($memberId);

        if ($member === null || $member['status'] !== 'active' || $member['membership_status'] !== 'active') {
            throw ValidationException::field('form', 'Only a verified member can ask for an aid grant.');
        }

        $details = trim($details);
        $used    = $this->grants->usedThisYear($memberId);
        $errors  = $this->eligibility($memberId) === ''
            ? self::requestErrors($amount, $purpose, $details, $used)
            : ['form' => $this->eligibility($memberId)];

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stored = $this->photos->storeMany($uploads, self::PHOTO_FOLDER, 'evidence', self::MAX_PHOTOS);

        $this->pdo->beginTransaction();

        try {
            // A grant belongs to the home division, whose moderator vouches (§12.1).
            $id = $this->grants->create([
                'member_id'       => $memberId,
                'division_id'     => (int) $member['division_id'],
                'amount'          => $amount,
                'purpose'         => $purpose,
                'details'         => $details,
                'evidence_photos' => $stored,
            ]);

            $moderator = $this->divisions->findBasic((int) $member['division_id'])['moderator_id'] ?? null;

            if ($moderator !== null && $moderator !== $memberId) {
                $this->notifications->push($moderator, 'aid_grant_requested', [
                    'title'  => 'Aid request to vouch for: ' . $member['full_name'],
                    'detail' => $amount . ' pts · ' . $purpose,
                    'icon'   => 'heart',
                    'href'   => '/moderator/aid-vouching',
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            foreach ($stored as $path) {
                $this->photos->delete($path);
            }

            throw $exception;
        }

        return $id;
    }

    /**
     * Change a request the moderator has not vouched for yet.
     *
     * @throws ValidationException|RecordNotFoundException
     */
    public function update(int $grantId, int $memberId, int $amount, string $purpose, string $details): void
    {
        $grant   = $this->ownOrFail($grantId, $memberId);
        $details = trim($details);

        if ($grant['status'] !== 'requested') {
            throw ValidationException::field('form', 'Your moderator has already acted on this request, so it can no longer be changed.');
        }

        // This request's own amount does not count against itself.
        $used   = $this->grants->usedThisYear($memberId) - (int) $grant['requested_amount'];
        $errors = self::requestErrors($amount, $purpose, $details, $used);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if (!$this->grants->updateRequest($grantId, $memberId, $amount, $purpose, $details)) {
            throw ValidationException::field('form', 'Your moderator has already acted on this request.');
        }
    }

    /**
     * Answer the Sponsor Liaison's question.
     *
     * @throws ValidationException|RecordNotFoundException
     */
    public function reply(int $grantId, int $memberId, string $reply): void
    {
        $this->ownOrFail($grantId, $memberId);
        $reply = trim($reply);

        if ($reply === '' || mb_strlen($reply) > self::REPLY_MAX) {
            throw ValidationException::field('reply', sprintf('Write an answer of up to %d characters.', self::REPLY_MAX));
        }

        if (!$this->grants->reply($grantId, $memberId, $reply)) {
            throw ValidationException::field('reply', 'Nobody is waiting for an answer on this request.');
        }
    }

    /**
     * @throws ValidationException|RecordNotFoundException
     */
    public function withdraw(int $grantId, int $memberId): void
    {
        $this->ownOrFail($grantId, $memberId);

        if (!$this->grants->withdraw($grantId, $memberId)) {
            throw ValidationException::field('form', 'Only a request still being considered can be withdrawn.');
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException
     */
    public function ownOrFail(int $grantId, int $memberId): array
    {
        return $this->grants->findOwned($grantId, $memberId) ?? throw new RecordNotFoundException('No such aid request of yours.');
    }
}
