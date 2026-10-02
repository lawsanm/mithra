<?php

declare(strict_types=1);

/**
 * Damage claims (Plan 3.4) — raised from the booking page at return, and
 * answered, signed off or withdrawn there. The rules live in
 * DamageClaimService.
 */
final class DamageClaimController extends Controller
{
    public static function service(PDO $pdo): DamageClaimService
    {
        return new DamageClaimService(
            $pdo,
            new Booking($pdo),
            new DamageClaim($pdo),
            new ReturnRecord($pdo),
            BookingController::returns($pdo),
            new Dispute($pdo),
            new GnDivision($pdo),
            self::ledgerService($pdo),
            PhotoStore::uploads(),
            new Notification($pdo)
        );
    }

    /**
     * POST /bookings/{id}/claims — the lender raises a claim at return.
     */
    public function store(int $id): void
    {
        $input = new Validator($_POST);
        $input
            ->required('severity', 'Severity')->inList('severity', 'Severity', DamageClaimService::SEVERITIES)
            ->required('amount', 'Claim amount')->integer('amount', 'Claim amount', 1)
            ->required('description', 'What happened')->maxLength('description', 'What happened', DamageClaimService::DESCRIPTION_MAX);

        $this->attempt(function () use ($id, $input): string {
            if (!$input->passes()) {
                throw new ValidationException($input->errors());
            }

            $track = self::service($this->pdo)->raise(
                $id,
                $this->userId(),
                $input->value('severity'),
                (int) $input->value('amount'),
                $input->value('description'),
                uploaded_files('evidence')
            );

            return match ($track) {
                'simple' => 'Claim raised. The borrower has 48 hours to accept or contest it.',
                'admin'  => 'Claim raised. Because your moderator is part of this booking, the Admin handles it.',
                default  => 'Claim raised. Your moderator will arrange to meet you both.',
            };
        }, '/bookings/' . $id . '#claim', ['Booking not found', 'Choose one of your bookings from My Bookings.']);
    }

    /**
     * POST /damage-claims/{id}/accept — the borrower accepts the penalty.
     */
    public function accept(int $id): void
    {
        $this->act($id, function (DamageClaimService $claims) use ($id): string {
            return $claims->accept($id, $this->userId())
                ? 'You accepted the claim. The penalty was paid and the booking is complete.'
                : 'You accepted, but your balance cannot cover the penalty, so your moderator will settle it with you.';
        });
    }

    /**
     * POST /damage-claims/{id}/contest — the borrower disagrees.
     */
    public function contest(int $id): void
    {
        $this->act($id, function (DamageClaimService $claims) use ($id): string {
            $claims->contest($id, $this->userId());

            return 'Claim contested. Your moderator will meet you both.';
        });
    }

    /**
     * POST /damage-claims/{id}/sign-off — agree with the moderator's resolution.
     */
    public function signOff(int $id): void
    {
        $this->act($id, function (DamageClaimService $claims) use ($id): string {
            return $claims->signOff($id, $this->userId())
                ? 'Both of you signed. The resolution is settled and the booking is complete.'
                : 'Signed. Waiting for the other member to sign.';
        });
    }

    /**
     * POST /damage-claims/{id}/withdraw — the lender takes it back.
     */
    public function withdraw(int $id): void
    {
        $this->act($id, function (DamageClaimService $claims) use ($id): string {
            $claims->withdraw($id, $this->userId());

            return 'Claim withdrawn. The return is accepted and the booking is complete.';
        });
    }

    /**
     * @param callable(DamageClaimService): string $action returns the confirmation
     */
    private function act(int $id, callable $action): void
    {
        $bookingId = (int) ((new DamageClaim($this->pdo))->find($id)['booking_id'] ?? 0);

        $this->attempt(
            fn (): string => $action(self::service($this->pdo)),
            '/bookings/' . $bookingId . '#claim',
            ['Claim not found', 'This claim is not available to your account.']
        );
    }
}
