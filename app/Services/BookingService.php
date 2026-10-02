<?php

declare(strict_types=1);

/**
 * Booking requests (Plan 3.1, §8, §18.3): a borrower asks for dates, the
 * lender accepts or declines within 24 hours, and either can cancel before the
 * handover.
 *
 * Points move only at acceptance: the rental charge and the late-fee buffer
 * go from the borrower's wallet to the In-Flight Pool, linked to the booking,
 * in the same transaction that accepts it (Plan §7.4). A request costs
 * nothing, so a lender who never answers costs the borrower nothing either.
 *
 * Every allowed status change is listed once in TRANSITIONS, so adding a state
 * is one line here (Rules/DESIGN_PRINCIPLES.md).
 */
final class BookingService
{
    /** Hours a lender has to answer before the request auto-cancels. */
    public const ANSWER_HOURS = 24;

    /** Days in a "month" when a monthly rate is applied (Plan §8.4). */
    public const MONTH_DAYS = 30;

    /** Requests longer than this are nudged toward the monthly rate (§8.4). */
    public const MONTHLY_NUDGE_DAYS = 27;

    /** Longest single booking a member can request, in days. */
    public const MAX_DAYS = 180;

    public const MESSAGE_MAX = 255;

    /** from => the statuses a booking may move to next. */
    public const TRANSITIONS = [
        'requested'         => ['awaiting_handover', 'rejected', 'cancelled', 'auto_cancelled'],
        'awaiting_handover' => ['in_progress', 'cancelled', 'auto_cancelled'],
        'in_progress'       => ['awaiting_return', 'pending_moderator'],
        'awaiting_return'   => ['completed', 'pending_moderator', 'escalated'],
        'pending_moderator' => ['completed', 'escalated'],
        'escalated'         => ['completed'],
    ];

    public function __construct(
        private PDO $pdo,
        private Booking $bookings,
        private Item $items,
        private UserDivision $memberships,
        private GnDivision $divisions,
        private Wallet $wallets,
        private LedgerService $ledger,
        private AvailabilityService $availability,
        private Notification $notifications
    ) {
    }

    // ── Pure rules ──────────────────────────────────────────────────────────

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * The late-fee buffer: one daily rate (Plan §7.6). A monthly-only item has
     * no daily rate, so its daily equivalent is the monthly rate ÷ 30, rounded
     * up so the lender is never short (team decision, checklist "Booking
     * request › Rules").
     */
    public static function lateBuffer(?int $dailyRate, ?int $monthlyRate): int
    {
        if ($dailyRate !== null && $dailyRate > 0) {
            return $dailyRate;
        }

        return (int) ceil(((int) $monthlyRate) / self::MONTH_DAYS);
    }

    /**
     * Everything the borrower is shown before asking, worked out on the
     * server so the form works without JavaScript.
     *
     * Days are counted inclusively (a booking from Monday to Monday is one
     * day). A monthly booking is charged one monthly rate per started 30 days.
     *
     * @param string $basis 'daily', 'monthly', or '' for the cheaper one
     *
     * @return array{days: int, daily_total: ?int, monthly_total: ?int, cheaper: string,
     *               basis: string, rate: int, charge: int, buffer: int, total: int, nudge: bool}
     */
    public static function quote(string $start, string $end, ?int $dailyRate, ?int $monthlyRate, string $basis = ''): array
    {
        $days = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;

        $dailyTotal   = $dailyRate !== null && $dailyRate > 0 ? $dailyRate * $days : null;
        $monthlyTotal = $monthlyRate !== null && $monthlyRate > 0
            ? $monthlyRate * (int) ceil($days / self::MONTH_DAYS)
            : null;

        $cheaper = match (true) {
            $dailyTotal === null   => 'monthly',
            $monthlyTotal === null => 'daily',
            default                => $monthlyTotal < $dailyTotal ? 'monthly' : 'daily',
        };

        // A basis the item does not offer falls back to the cheaper one.
        if (($basis === 'daily' && $dailyTotal === null) || ($basis === 'monthly' && $monthlyTotal === null)
            || !in_array($basis, ['daily', 'monthly'], true)) {
            $basis = $cheaper;
        }

        $charge = (int) ($basis === 'daily' ? $dailyTotal : $monthlyTotal);
        $buffer = self::lateBuffer($dailyRate, $monthlyRate);

        return [
            'days'          => $days,
            'daily_total'   => $dailyTotal,
            'monthly_total' => $monthlyTotal,
            'cheaper'       => $cheaper,
            'basis'         => $basis,
            'rate'          => (int) ($basis === 'daily' ? $dailyRate : $monthlyRate),
            'charge'        => $charge,
            'buffer'        => $buffer,
            'total'         => $charge + $buffer,
            'nudge'         => $days > self::MONTHLY_NUDGE_DAYS && $monthlyTotal !== null && $basis === 'daily',
        ];
    }

    /**
     * Range rules for a request, on top of the shared ones: not longer than
     * MAX_DAYS.
     *
     * @return array<string, string>
     */
    public static function datesErrors(string $start, string $end, string $today): array
    {
        $errors = AvailabilityService::rangeErrors($start, $end, $today);

        if ($errors === [] && (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1 > self::MAX_DAYS) {
            $errors['end_date'] = sprintf('A single booking can run %d days at most.', self::MAX_DAYS);
        }

        return $errors;
    }

    /**
     * Who may move a booking from its current status: the borrower may
     * cancel a request; either side may cancel before the handover; only the
     * lender accepts or declines.
     */
    public static function mayAct(string $action, string $status, bool $isLender): bool
    {
        return match ($action) {
            'accept', 'decline' => $isLender && $status === 'requested',
            'cancel'            => ($status === 'requested' && !$isLender) || $status === 'awaiting_handover',
            default             => false,
        };
    }

    // ── Create ──────────────────────────────────────────────────────────────

    /**
     * Ask to borrow an item. Nothing is charged until the lender accepts, but
     * the borrower must be able to cover the charge and the buffer now, so a
     * request is never accepted into a shortfall.
     *
     * @throws ValidationException|RecordNotFoundException
     *
     * @return int the new booking id
     */
    public function request(int $itemId, int $borrowerId, string $start, string $end, string $basis, string $message): int
    {
        $item = $this->items->findForDetail($itemId);

        if ($item === null) {
            throw new RecordNotFoundException('No such listing.');
        }

        $message = trim($message);
        $errors  = self::datesErrors($start, $end, date('Y-m-d'));

        if ((int) $item['owner_id'] === $borrowerId) {
            throw ValidationException::field('form', 'You cannot borrow your own item.');
        }

        if ($item['listing_type'] !== 'rental' || $item['status'] !== 'active') {
            throw ValidationException::field('form', 'This item is not available to borrow right now.');
        }

        if (!in_array((int) $item['gn_division_id'], $this->memberships->activeDivisionIds($borrowerId), true)) {
            throw ValidationException::field('form', 'Only active members of this GN division can borrow it.');
        }

        if (mb_strlen($message) > self::MESSAGE_MAX) {
            $errors['message'] = sprintf('Keep the message to %d characters.', self::MESSAGE_MAX);
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($this->availability->isBlocked($itemId, $start, $end)) {
            throw ValidationException::field('start_date', 'The lender has blocked some of those dates.');
        }

        if ($this->bookings->countOverlapping($itemId, $start, $end, Booking::DATE_HOLDING_STATES) > 0) {
            throw ValidationException::field('start_date', 'Someone already has the item on some of those dates.');
        }

        $quote   = self::quote($start, $end, self::rate($item['daily_rate']), self::rate($item['monthly_rate']), $basis);
        $balance = $this->wallets->balance($borrowerId);

        if ($balance < $quote['total']) {
            throw ValidationException::field('form', sprintf(
                'You need %d points (%d rental charge and a %d-point late buffer) and hold %d. '
                . 'Earn points by lending something, or ask your moderator about an aid grant.',
                $quote['total'],
                $quote['charge'],
                $quote['buffer'],
                $balance
            ));
        }

        $division = $this->divisions->findBasic((int) $item['gn_division_id']);
        $moderator = $division['moderator_id'] ?? null;

        $this->pdo->beginTransaction();

        try {
            $id = $this->bookings->create([
                'item_id'            => $itemId,
                'borrower_id'        => $borrowerId,
                'lender_id'          => (int) $item['owner_id'],
                'start_date'         => $start,
                'end_date'           => $end,
                'rate_basis'         => $quote['basis'],
                'agreed_rate'        => $quote['rate'],
                'rental_charge'      => $quote['charge'],
                'late_buffer'        => $quote['buffer'],
                // Rule 1 (§16.5): a moderator's own booking goes to the Admin if it is ever disputed.
                'moderator_involved' => $moderator !== null && in_array($moderator, [$borrowerId, (int) $item['owner_id']], true),
                'message'            => $message === '' ? null : $message,
            ]);

            $this->notifications->push((int) $item['owner_id'], 'booking_requested', [
                'title'      => 'New request to borrow ' . $item['title'],
                'detail'     => sprintf('%s – %s · answer within %d hours.', date('j M', strtotime($start)), date('j M', strtotime($end)), self::ANSWER_HOURS),
                'icon'       => 'calendar',
                'href'       => '/bookings/' . $id,
                'booking_id' => $id,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return $id;
    }

    // ── Update: the lender decides ─────────────────────────────────────────

    /**
     * Accept a request. In one transaction: lock the item so two acceptances
     * cannot overlap, check the dates again, move the charge and the buffer
     * into In-Flight, accept, and decline every other request for those dates.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function accept(int $bookingId, int $lenderId): void
    {
        $this->inTransaction(function () use ($bookingId, $lenderId): void {
            $booking = $this->lockedFor($bookingId, $lenderId, 'accept');

            if (strtotime((string) $booking['requested_at']) < time() - self::ANSWER_HOURS * 3600) {
                throw ValidationException::field('form', 'This request is more than 24 hours old and can no longer be accepted.');
            }

            $this->items->lockRow((int) $booking['item_id']);

            if ($this->bookings->countOverlapping(
                (int) $booking['item_id'],
                (string) $booking['start_date'],
                (string) $booking['end_date'],
                Booking::DATE_HOLDING_STATES,
                $bookingId
            ) > 0) {
                throw ValidationException::field('form', 'You already accepted another booking for some of these dates.');
            }

            $borrower = (int) $booking['borrower_id'];
            $links    = ['booking_id' => $bookingId];

            try {
                $this->ledger->memberToPool($borrower, 'in_flight', (int) $booking['rental_charge'], 'rental_charge', $links);
                $this->ledger->memberToPool($borrower, 'in_flight', (int) $booking['late_buffer'], 'buffer_hold', $links);
            } catch (InsufficientPointsException $exception) {
                throw ValidationException::field('form', 'The borrower no longer has enough points for this booking. Decline it, or wait for them to top up.');
            }

            $this->bookings->move($bookingId, 'requested', 'awaiting_handover');

            $this->notifications->push($borrower, 'booking_accepted', [
                'title'      => 'Your request was accepted',
                'detail'     => 'View your booking for handover details.',
                'icon'       => 'check-circle',
                'href'       => '/bookings/' . $bookingId,
                'booking_id' => $bookingId,
            ]);

            foreach ($this->bookings->overlappingRequests($bookingId) as $other) {
                $this->bookings->decline((int) $other['id'], 'The item was booked by someone else for those dates.');
                $this->notifications->push((int) $other['borrower_id'], 'booking_declined', [
                    'title'      => 'Request declined: ' . $booking['item_title'],
                    'detail'     => 'Another member booked those dates first.',
                    'icon'       => 'x-circle',
                    'href'       => '/bookings/' . $other['id'],
                    'booking_id' => (int) $other['id'],
                ]);
            }
        });
    }

    /**
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function decline(int $bookingId, int $lenderId, string $reason): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) > self::MESSAGE_MAX) {
            throw ValidationException::field('reason', sprintf('Keep the reason to %d characters.', self::MESSAGE_MAX));
        }

        $this->inTransaction(function () use ($bookingId, $lenderId, $reason): void {
            $booking = $this->lockedFor($bookingId, $lenderId, 'decline');
            $this->bookings->decline($bookingId, $reason === '' ? null : $reason);

            $this->notifications->push((int) $booking['borrower_id'], 'booking_declined', [
                'title'      => 'Request declined: ' . $booking['item_title'],
                'detail'     => $reason === '' ? 'The lender could not lend it for those dates.' : 'Reason: ' . $reason,
                'icon'       => 'x-circle',
                'href'       => '/bookings/' . $bookingId,
                'booking_id' => $bookingId,
            ]);
        });
    }

    // ── Delete: cancel ─────────────────────────────────────────────────────

    /**
     * Cancel before the handover. A request costs nothing to cancel; after
     * acceptance everything in In-Flight goes back to the borrower (§10.1).
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function cancel(int $bookingId, int $memberId): void
    {
        $this->inTransaction(function () use ($bookingId, $memberId): void {
            $booking = $this->lockedFor($bookingId, $memberId, 'cancel');
            $this->refundAndClose($booking, 'cancelled', $memberId);

            $other = $memberId === (int) $booking['lender_id'] ? (int) $booking['borrower_id'] : (int) $booking['lender_id'];
            $this->notifications->push($other, 'booking_cancelled', [
                'title'      => 'Booking cancelled: ' . $booking['item_title'],
                'detail'     => 'You can rate how the cancellation was handled.',
                'icon'       => 'x-circle',
                'href'       => '/bookings/' . $bookingId,
                'booking_id' => $bookingId,
            ]);
        });
    }

    /**
     * Refund whatever this booking holds in In-Flight and close it in one of
     * the cancelled states. Shared by cancel, the 24-hour request expiry and
     * the 48-hour handover expiry.
     *
     * @param array<string, mixed> $booking a lockForUpdate() row
     */
    public function refundAndClose(array $booking, string $status, ?int $cancelledBy): void
    {
        $id    = (int) $booking['id'];
        $links = ['booking_id' => $id];

        if ($booking['status'] === 'awaiting_handover') {
            $this->ledger->poolToMember('in_flight', (int) $booking['borrower_id'], (int) $booking['rental_charge'], 'booking_refund', $links);
            $this->ledger->poolToMember('in_flight', (int) $booking['borrower_id'], (int) $booking['late_buffer'], 'buffer_refund', $links);
        }

        if (!$this->bookings->close($id, (string) $booking['status'], $status, $cancelledBy)) {
            throw ValidationException::field('form', 'This booking changed while you were looking at it. Reload and try again.');
        }
    }

    // ── Scheduled job ──────────────────────────────────────────────────────

    /**
     * scripts/expire_booking_requests.php: a request with no answer after 24
     * hours is auto-cancelled and both sides are told. Only rows still
     * 'requested' are touched, so a second run changes nothing.
     */
    public function expireRequests(): string
    {
        $count = 0;

        foreach ($this->bookings->staleRequests(self::ANSWER_HOURS) as $stale) {
            $this->inTransaction(function () use ($stale, &$count): void {
                $booking = $this->bookings->lockForUpdate((int) $stale['id']);

                if ($booking === null || $booking['status'] !== 'requested') {
                    return;
                }

                $this->refundAndClose($booking, 'auto_cancelled', null);

                foreach ([(int) $booking['borrower_id'], (int) $booking['lender_id']] as $party) {
                    $this->notifications->push($party, 'booking_auto_cancelled', [
                        'title'      => 'Request expired: ' . $booking['item_title'],
                        'detail'     => 'The lender did not answer within 24 hours, so the request was cancelled. No points moved.',
                        'icon'       => 'clock',
                        'href'       => '/bookings/' . $booking['id'],
                        'booking_id' => (int) $booking['id'],
                    ]);
                }

                $count++;
            });
        }

        return $count . ' request' . ($count === 1 ? '' : 's') . ' auto-cancelled';
    }

    // ── Plumbing ───────────────────────────────────────────────────────────

    /**
     * The booking, row-locked, once this member is entitled to this action on
     * it in its current state.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    private function lockedFor(int $bookingId, int $memberId, string $action): array
    {
        $booking = $this->bookings->lockForUpdate($bookingId);

        if ($booking === null) {
            throw new RecordNotFoundException('No such booking.');
        }

        $isLender = (int) $booking['lender_id'] === $memberId;

        if (!$isLender && (int) $booking['borrower_id'] !== $memberId) {
            throw new AccessDeniedException('This booking belongs to other members.');
        }

        if (!self::mayAct($action, (string) $booking['status'], $isLender)) {
            throw ValidationException::field('form', match ($action) {
                'accept', 'decline' => 'Only the lender can answer a request, and only while it is waiting.',
                default             => 'This booking can no longer be cancelled here.',
            });
        }

        return $booking;
    }

    private static function rate(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function inTransaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}
