<?php

declare(strict_types=1);

/**
 * Disputes, the member's side (Plan 3.6) — raised, edited and withdrawn from
 * the booking page. The ruling is the Admin's (admin portal).
 */
final class DisputeController extends Controller
{
    public static function service(PDO $pdo): DisputeService
    {
        return new DisputeService($pdo, new Dispute($pdo), new DamageClaim($pdo), new Booking($pdo), new User($pdo), new Notification($pdo));
    }

    /**
     * POST /bookings/{id}/disputes.
     */
    public function store(int $id): void
    {
        $this->attempt(function () use ($id): string {
            self::service($this->pdo)->raise($id, $this->userId(), $this->posted('reason'));

            return 'Dispute raised. The Admin will review the claim and rule on it.';
        }, '/bookings/' . $id . '#dispute', ['Booking not found', 'Choose one of your bookings from My Bookings.']);
    }

    /**
     * POST /disputes/{id} — change the reason.
     */
    public function update(int $id): void
    {
        $this->act($id, fn (DisputeService $disputes): int => $disputes->update($id, $this->userId(), $this->posted('reason')), 'Dispute updated.');
    }

    /**
     * POST /disputes/{id}/withdraw.
     */
    public function withdraw(int $id): void
    {
        $this->act($id, fn (DisputeService $disputes): int => $disputes->withdraw($id, $this->userId()), 'Dispute withdrawn.');
    }

    /**
     * Run one change to a dispute and go back to its booking.
     *
     * @param callable(DisputeService): mixed $action
     */
    private function act(int $id, callable $action, string $message): void
    {
        $bookingId = (int) ((new Dispute($this->pdo))->find($id)['booking_id'] ?? 0);

        $this->attempt(function () use ($action, $message): string {
            $action(self::service($this->pdo));

            return $message;
        }, '/bookings/' . $bookingId . '#dispute', ['Dispute not found', 'Choose one of the disputes you raised.']);
    }
}
