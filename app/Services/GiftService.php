<?php

declare(strict_types=1);

/**
 * Gifting (Plan 4.5, §11): a member sends points to a neighbour, with a
 * reason. Gifts cannot be reversed.
 *
 * Caps: 200 points a day and 2,000 a year per sender (§11.1). They are
 * checked after the sender's wallet row is locked, so two gifts sent at the
 * same moment queue on the lock and the second sees the first — the caps are
 * summed from `gifts` itself, so no separate counter table is needed.
 *
 * Blocked while the sender has a pending damage claim, an open dispute or an
 * overdue return, or the recipient has a pending damage claim (§11.1, §19).
 */
final class GiftService
{
    public const REASON_MAX = 100;

    /** A pair of members trading gifts back and forth this often is flagged (§19). */
    public const ROUND_TRIPS   = 3;
    public const PATTERN_DAYS  = 30;

    /** Gifts per page in the history (Rules/CONVENTIONS.md §9). */
    public const PER_PAGE = 20;

    public function __construct(
        private PDO $pdo,
        private Gift $gifts,
        private User $users,
        private UserDivision $memberships,
        private Booking $bookings,
        private DamageClaim $claims,
        private Dispute $disputes,
        private GnDivision $divisions,
        private LedgerService $ledger,
        private Notification $notifications
    ) {
    }

    /**
     * The caps and the amount's own rules. Pure.
     *
     * @return array<string, string>
     */
    public static function amountErrors(int $amount, int $sentToday, int $sentThisYear): array
    {
        if ($amount < 1) {
            return ['amount' => 'Send at least 1 point.'];
        }

        if ($sentToday + $amount > Gift::DAILY_CAP) {
            return ['amount' => sprintf(
                'Daily gift cap exceeded — you’ve sent %d of %d pts today. You can send up to %d pts more.',
                $sentToday,
                Gift::DAILY_CAP,
                max(0, Gift::DAILY_CAP - $sentToday)
            )];
        }

        if ($sentThisYear + $amount > Gift::ANNUAL_CAP) {
            return ['amount' => sprintf(
                'Yearly gift cap exceeded — you’ve sent %d of %d pts this year.',
                $sentThisYear,
                Gift::ANNUAL_CAP
            )];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function reasonErrors(string $reason): array
    {
        if ($reason === '') {
            return ['reason' => 'Say what the gift is for.'];
        }

        if (mb_strlen($reason) > self::REASON_MAX) {
            return ['reason' => sprintf('Keep the reason to %d characters.', self::REASON_MAX)];
        }

        return [];
    }

    /**
     * Send a gift. Everything is checked and moved in one transaction.
     *
     * @throws ValidationException
     *
     * @return int the gift id
     */
    public function send(int $senderId, int $recipientId, int $amount, string $reason): int
    {
        $reason = trim($reason);
        $errors = self::reasonErrors($reason);

        if ($amount < 1) {
            $errors['amount'] = 'Send at least 1 point.';
        }

        if ($recipientId === $senderId) {
            $errors['recipient'] = 'You cannot send a gift to yourself.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return Database::transaction($this->pdo, function () use ($senderId, $recipientId, $amount, $reason): int {
            // Match every ledger movement: pools first, then wallets in id order.
            $balance = $this->ledger->lockMemberBalances([$senderId, $recipientId])[$senderId] ?? 0;

            $this->checkPeople($senderId, $recipientId);

            $errors = self::amountErrors($amount, $this->gifts->sentToday($senderId), $this->gifts->sentThisYear($senderId));

            if ($errors === [] && $balance < $amount) {
                $errors['amount'] = sprintf('You hold %d points.', $balance);
            }

            if ($errors !== []) {
                throw new ValidationException($errors);
            }

            $giftId = $this->gifts->create($senderId, $recipientId, $amount, $reason);
            $this->ledger->memberToMember($senderId, $recipientId, $amount, 'gift', ['gift_id' => $giftId]);

            $this->notifications->push($recipientId, 'gift_received', [
                'title'   => 'You received a gift',
                'detail'  => $reason,
                'icon'    => 'gift',
                'href'    => '/gifts?box=received',
                'gift_id' => $giftId,
            ]);

            return $giftId;
        });
    }

    /**
     * scripts/detect_gift_patterns.php: a pair of members who sent gifts back
     * and forth three times in 30 days is reported to the moderator, once per
     * window (§19). When the moderator is one of the pair, the Admin is told
     * instead (Rule 1, §16.5).
     */
    public function detectPatterns(): string
    {
        $reported = 0;

        foreach ($this->gifts->roundTripPairs(self::PATTERN_DAYS, self::ROUND_TRIPS) as $pair) {
            $key     = $pair['a'] . '-' . $pair['b'];
            $readers = $this->moderatorsOf([(int) $pair['a'], (int) $pair['b']]) ?: $this->users->idsInRole('admin');

            foreach ($readers as $moderator) {
                if ($this->notifications->sentRecently($moderator, 'gift_pattern', $key, self::PATTERN_DAYS)) {
                    continue;
                }

                $this->notifications->push($moderator, 'gift_pattern', [
                    'title'  => 'Unusual gifting: ' . $pair['a_name'] . ' and ' . $pair['b_name'],
                    'detail' => sprintf('%d round trips in %d days. Check that nobody is being pressured.', (int) $pair['trips'], self::PATTERN_DAYS),
                    'icon'   => 'alert-triangle',
                    'href'   => '/members/' . $pair['a'],
                    'pair'   => $key,
                ]);
                $reported++;
            }
        }

        return plural($reported, 'gift pattern') . ' reported';
    }

    /**
     * @throws ValidationException
     */
    private function checkPeople(int $senderId, int $recipientId): void
    {
        $sender    = $this->users->findAccount($senderId);
        $recipient = $this->users->findAccount($recipientId);

        if ($sender === null || $sender['status'] !== 'active') {
            throw ValidationException::field('recipient', 'Only an active member can send gifts.');
        }

        if ($recipient === null || $recipient['status'] !== 'active' || !in_array($recipient['role_code'], ['member', 'moderator'], true)) {
            throw ValidationException::field('recipient', 'Choose an active member.');
        }

        if ((int) $recipient['gift_receive_enabled'] !== 1) {
            throw ValidationException::field('recipient', 'This member is not accepting gifts.');
        }

        if (array_intersect($this->memberships->activeDivisionIds($senderId), $this->memberships->activeDivisionIds($recipientId)) === []) {
            throw ValidationException::field('recipient', 'You can only send gifts to members of your own GN division.');
        }

        if ($this->claims->countPendingAgainst($senderId) > 0) {
            throw ValidationException::field('form', 'Gifting is paused while a damage claim against you is open.');
        }

        if ($this->disputes->countOpenFor($senderId) > 0) {
            throw ValidationException::field('form', 'Gifting is paused while you have an open dispute.');
        }

        if ($this->bookings->countOverdueBorrowings($senderId) > 0) {
            throw ValidationException::field('form', 'Gifting is paused while you have an overdue return.');
        }

        if ($this->claims->countPendingAgainst($recipientId) > 0) {
            throw ValidationException::field('recipient', 'This member cannot receive gifts while a damage claim against them is open.');
        }
    }

    /**
     * @param list<int> $memberIds
     *
     * @return list<int>
     */
    private function moderatorsOf(array $memberIds): array
    {
        $moderators = [];

        foreach ($memberIds as $memberId) {
            foreach ($this->memberships->activeDivisionIds($memberId) as $divisionId) {
                $moderator = $this->divisions->findBasic($divisionId)['moderator_id'] ?? null;

                if ($moderator !== null && !in_array($moderator, $memberIds, true)) {
                    $moderators[$moderator] = $moderator;
                }
            }
        }

        return array_values($moderators);
    }
}
