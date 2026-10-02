<?php

declare(strict_types=1);

/**
 * Donations (Plan 2.5, §13): a permanent, free transfer of an item. No points
 * move.
 *
 *   listed → approved (donation opens) → members request it → the donor
 *   chooses a recipient, or first-come mode picks the first request → both
 *   sides confirm the handover → completed, item donated
 *
 * Every change that touches the donation row locks it first, so two requests
 * in first-come mode, or a selection racing a withdrawal, cannot both win.
 */
final class DonationService
{
    public const MESSAGE_MAX = 255;

    public const MODES = ['donor_chooses', 'first_come'];

    public function __construct(
        private PDO $pdo,
        private Donation $donations,
        private Item $items,
        private UserDivision $memberships,
        private Notification $notifications,
        private TrustScoreService $trust
    ) {
    }

    /**
     * Why this member may not request this donation; empty when they may.
     * Pure, so the rule is testable without a database.
     *
     * @param array{donor_id: int, status: string, item_status: string, division_id: int} $donation
     * @param list<int> $memberDivisions the requester's active divisions
     *
     * @return array<string, string>
     */
    public static function requestErrors(array $donation, int $requesterId, array $memberDivisions, ?string $existingStatus): array
    {
        if ($donation['donor_id'] === $requesterId) {
            return ['form' => 'This is your own donation.'];
        }

        if ($donation['status'] !== 'open' || $donation['item_status'] !== 'active') {
            return ['form' => 'This donation is no longer taking requests.'];
        }

        if (!in_array($donation['division_id'], $memberDivisions, true)) {
            return ['form' => 'Only active members of this GN division can request it.'];
        }

        if ($existingStatus !== null && $existingStatus !== 'withdrawn') {
            return ['form' => 'You have already requested this donation.'];
        }

        return [];
    }

    /**
     * Ask for a donation. In first-come mode the first request is chosen at
     * once (Plan §13.1).
     *
     * @throws ValidationException|RecordNotFoundException
     *
     * @return bool whether the requester was chosen straight away
     */
    public function request(int $donationId, int $requesterId, string $message): bool
    {
        $message = trim($message);

        if (mb_strlen($message) > self::MESSAGE_MAX) {
            throw ValidationException::field('message', sprintf('Keep the message to %d characters.', self::MESSAGE_MAX));
        }

        return $this->inTransaction(function () use ($donationId, $requesterId, $message): bool {
            $donation = $this->lockOrFail($donationId);
            $existing = $this->donations->requestBy($donationId, $requesterId);

            $errors = self::requestErrors([
                'donor_id'    => (int) $donation['donor_id'],
                'status'      => (string) $donation['status'],
                'item_status' => (string) $donation['item_status'],
                'division_id' => (int) $donation['gn_division_id'],
            ], $requesterId, $this->memberships->activeDivisionIds($requesterId), $existing['status'] ?? null);

            if ($errors !== []) {
                throw new ValidationException($errors);
            }

            $requestId = $this->donations->saveRequest($donationId, $requesterId, $message === '' ? null : $message);

            if ($donation['selection_mode'] === 'first_come') {
                $this->choose($donation, $requestId, $requesterId);

                return true;
            }

            $this->notifications->push((int) $donation['donor_id'], 'donation_requested', [
                'title'  => 'New request for your donation: ' . $donation['title'],
                'detail' => 'Choose a recipient when you are ready.',
                'icon'   => 'gift',
                'href'   => '/donations/' . $donationId,
            ]);

            return false;
        });
    }

    /**
     * Turn first-come on or off. Turning it on with requests already waiting
     * hands the item to the oldest one.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function setMode(int $donationId, int $donorId, string $mode): void
    {
        if (!in_array($mode, self::MODES, true)) {
            throw ValidationException::field('form', 'Choose how the recipient is picked.');
        }

        $this->inTransaction(function () use ($donationId, $donorId, $mode): void {
            $donation = $this->donorsOrFail($donationId, $donorId);

            if ($donation['status'] !== 'open') {
                throw ValidationException::field('form', 'A recipient is already chosen, so the mode can no longer change.');
            }

            $this->donations->setMode($donationId, $mode);

            $first = $mode === 'first_come' ? $this->donations->firstPendingRequest($donationId) : null;

            if ($first !== null) {
                $this->choose($donation, (int) $first['id'], (int) $first['requester_id']);
            }
        });
    }

    /**
     * The donor chooses who receives it. Everyone else who asked is told.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function select(int $donationId, int $donorId, int $requestId): void
    {
        $this->inTransaction(function () use ($donationId, $donorId, $requestId): void {
            $donation = $this->donorsOrFail($donationId, $donorId);
            $request  = $this->donations->findRequest($requestId);

            if ($request === null || (int) $request['donation_id'] !== $donationId || $request['status'] !== 'pending') {
                throw ValidationException::field('form', 'Choose one of the open requests.');
            }

            if ($donation['status'] !== 'open') {
                throw ValidationException::field('form', 'A recipient is already chosen.');
            }

            $this->choose($donation, $requestId, (int) $request['requester_id']);
        });
    }

    /**
     * One side confirms the handover. When both have, the donation completes
     * and the item is marked donated (Plan §13.1 step 4).
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return bool whether this confirmation completed the donation
     */
    public function confirm(int $donationId, int $memberId): bool
    {
        return $this->inTransaction(function () use ($donationId, $memberId): bool {
            $donation = $this->lockOrFail($donationId);
            $side     = match ($memberId) {
                (int) $donation['donor_id']           => 'donor',
                (int) ($donation['recipient_id'] ?? 0) => 'recipient',
                default                               => null,
            };

            if ($side === null) {
                throw new AccessDeniedException('Only the donor and the chosen recipient confirm a handover.');
            }

            if ($donation['status'] !== 'recipient_selected') {
                throw ValidationException::field('form', 'This donation has no handover waiting.');
            }

            $this->donations->confirmSide($donationId, $side);

            $otherConfirmed = $side === 'donor'
                ? $donation['recipient_confirmed_at'] !== null
                : $donation['donor_confirmed_at'] !== null;

            if (!$otherConfirmed) {
                $other = $side === 'donor' ? (int) $donation['recipient_id'] : (int) $donation['donor_id'];
                $this->notifications->push($other, 'donation_confirm', [
                    'title'  => 'Confirm the handover of ' . $donation['title'],
                    'detail' => 'The other side has confirmed. Confirm once the item has changed hands.',
                    'icon'   => 'gift',
                    'href'   => '/donations/' . $donationId . '/handover',
                ]);

                return false;
            }

            $this->donations->complete($donationId);
            $this->items->updateOwnedStatus((int) $donation['item_id'], (int) $donation['donor_id'], 'donated');

            foreach ([(int) $donation['donor_id'], (int) $donation['recipient_id']] as $party) {
                $this->trust->recalculate($party);
                $this->notifications->push($party, 'donation_completed', [
                    'title'  => 'Donation completed: ' . $donation['title'],
                    'detail' => 'Thank you. You can now rate each other.',
                    'icon'   => 'check-circle',
                    'href'   => '/ratings',
                ]);
            }

            return true;
        });
    }

    /**
     * A requester withdraws. If they had been chosen, the donation opens again.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     *
     * @return int the item, to return to its page
     */
    public function withdraw(int $requestId, int $requesterId): int
    {
        return $this->inTransaction(function () use ($requestId, $requesterId): int {
            $request = $this->donations->findRequest($requestId);

            if ($request === null) {
                throw new RecordNotFoundException('No such request.');
            }

            if ((int) $request['requester_id'] !== $requesterId) {
                throw new AccessDeniedException('This request belongs to another member.');
            }

            $donation = $this->lockOrFail((int) $request['donation_id']);

            if (!in_array($request['status'], ['pending', 'selected'], true) || $donation['status'] === 'completed') {
                throw ValidationException::field('form', 'This request can no longer be withdrawn.');
            }

            $this->donations->setRequestStatus($requestId, 'withdrawn');

            if ($request['status'] === 'selected') {
                $this->donations->setRecipient((int) $donation['id'], null);
                $this->notifications->push((int) $donation['donor_id'], 'donation_withdrawn', [
                    'title'  => 'Your chosen recipient withdrew: ' . $donation['title'],
                    'detail' => 'The donation is open again. Choose someone else.',
                    'icon'   => 'info',
                    'href'   => '/donations/' . $donation['id'],
                ]);
            }

            return (int) $donation['item_id'];
        });
    }

    /**
     * The donor cancels before the handover: open requests are declined and
     * the listing is archived.
     *
     * @throws ValidationException|RecordNotFoundException|AccessDeniedException
     */
    public function cancel(int $donationId, int $donorId): void
    {
        $this->inTransaction(function () use ($donationId, $donorId): void {
            $donation = $this->donorsOrFail($donationId, $donorId);

            if (!in_array($donation['status'], ['open', 'recipient_selected'], true)) {
                throw ValidationException::field('form', 'This donation is already finished.');
            }

            $this->donations->cancel($donationId);
            $this->items->updateOwnedStatus((int) $donation['item_id'], $donorId, 'archived');

            foreach ($this->donations->declineOthers($donationId, 0) as $requester) {
                $this->notifications->push($requester, 'donation_declined', [
                    'title'  => 'Donation withdrawn: ' . $donation['title'],
                    'detail' => 'The donor is no longer giving this item away.',
                    'icon'   => 'info',
                    'href'   => '/items/browse?type=donations',
                ]);
            }
        });
    }

    /**
     * @param array<string, mixed> $donation a lockOrFail() row
     */
    private function choose(array $donation, int $requestId, int $recipientId): void
    {
        $donationId = (int) $donation['id'];

        $this->donations->setRequestStatus($requestId, 'selected');
        $this->donations->setRecipient($donationId, $recipientId);

        $this->notifications->push($recipientId, 'donation_selected', [
            'title'  => 'You were chosen to receive ' . $donation['title'],
            'detail' => 'Arrange the handover with the donor, then confirm it.',
            'icon'   => 'gift',
            'href'   => '/donations/' . $donationId . '/handover',
        ]);

        foreach ($this->donations->declineOthers($donationId, $requestId) as $declined) {
            $this->notifications->push($declined, 'donation_declined', [
                'title'  => 'Another member was chosen for ' . $donation['title'],
                'detail' => 'Thank you for asking. Keep an eye on Browse for other donations.',
                'icon'   => 'info',
                'href'   => '/items/browse?type=donations',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException
     */
    private function lockOrFail(int $donationId): array
    {
        $donation = $this->donations->lockWithItem($donationId);

        if ($donation === null) {
            throw new RecordNotFoundException('No such donation.');
        }

        return $donation;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException|AccessDeniedException
     */
    private function donorsOrFail(int $donationId, int $donorId): array
    {
        $donation = $this->lockOrFail($donationId);

        if ((int) $donation['donor_id'] !== $donorId) {
            throw new AccessDeniedException('Only the donor can do that.');
        }

        return $donation;
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
