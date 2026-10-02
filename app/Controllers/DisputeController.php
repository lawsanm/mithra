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
        try {
            self::service($this->pdo)->raise($id, $this->userId(), (string) ($_POST['reason'] ?? ''));
            $this->flash('Dispute raised. The Admin will review the claim and rule on it.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->notice(404, 'Booking not found', 'Choose one of your bookings from My Bookings.');

            return;
        }

        $this->redirect('/bookings/' . $id . '#dispute');
    }

    /**
     * POST /disputes/{id} — change the reason.
     */
    public function update(int $id): void
    {
        $this->act(fn (DisputeService $disputes): int => $disputes->update($id, $this->userId(), (string) ($_POST['reason'] ?? '')), $id, 'Dispute updated.');
    }

    /**
     * POST /disputes/{id}/withdraw.
     */
    public function withdraw(int $id): void
    {
        $this->act(fn (DisputeService $disputes): int => $disputes->withdraw($id, $this->userId()), $id, 'Dispute withdrawn.');
    }

    /**
     * @param callable(DisputeService): int $action returns the booking to go back to
     */
    private function act(callable $action, int $id, string $message): void
    {
        $bookingId = (int) ((new Dispute($this->pdo))->find($id)['booking_id'] ?? 0);

        try {
            $bookingId = $action(self::service($this->pdo));
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Dispute not found', 'Choose one of the disputes you raised.');

            return;
        }

        $this->redirect('/bookings/' . $bookingId . '#dispute');
    }
}
