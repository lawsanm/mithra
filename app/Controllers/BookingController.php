<?php

declare(strict_types=1);

/**
 * Bookings (Plan 3.1) — My Bookings, the booking page, and the request,
 * accept, decline and cancel steps. The rules live in BookingService.
 */
final class BookingController extends Controller
{
    private const NOT_FOUND = ['Booking not found', 'Choose one of your bookings from My Bookings.'];

    private Booking $bookings;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->bookings = new Booking($pdo);
    }

    /** Shared with the scheduled jobs, which call the same rules. */
    public static function service(PDO $pdo): BookingService
    {
        return new BookingService(
            $pdo,
            new Booking($pdo),
            new Item($pdo),
            new UserDivision($pdo),
            new GnDivision($pdo),
            new Wallet($pdo),
            self::ledgerService($pdo),
            new AvailabilityService($pdo, new Item($pdo), new ItemAvailabilityBlock($pdo), new Booking($pdo)),
            new Notification($pdo)
        );
    }

    public static function handovers(PDO $pdo): HandoverService
    {
        return new HandoverService(
            $pdo,
            new Booking($pdo),
            new HandoverRecord($pdo),
            new Item($pdo),
            self::ledgerService($pdo),
            self::service($pdo),
            PhotoStore::uploads(),
            new Notification($pdo)
        );
    }

    public static function returns(PDO $pdo): ReturnService
    {
        return new ReturnService(
            $pdo,
            new Booking($pdo),
            new ReturnRecord($pdo),
            new Item($pdo),
            new DamageClaim($pdo),
            new GnDivision($pdo),
            self::ledgerService($pdo),
            PhotoStore::uploads(),
            new Notification($pdo),
            TrustController::service($pdo),
            new Dispute($pdo),
            new User($pdo)
        );
    }

    /**
     * GET /bookings — current bookings by role, or past ones with ?state=past.
     */
    public function index(): void
    {
        $me   = $this->userId();
        $role = $this->queryValue('role') === 'lender' ? 'lender' : 'borrower';
        $past = $this->queryValue('state') === 'past';
        $page = $this->page();

        $tabs = [];
        foreach (['borrower', 'lender'] as $tabRole) {
            $tabs[] = [
                'label'  => 'As ' . ucfirst($tabRole) . ' (' . $this->bookings->countForMember($me, $tabRole) . ')',
                'role'   => $tabRole,
                'active' => $role === $tabRole,
            ];
        }

        $total = $past ? $this->bookings->countPastForMember($me, $role) : $this->bookings->countForMember($me, $role);
        $rows  = $this->bookings->pageForMember($me, $role, $past, $page);

        $this->render('bookings/index', [
            'tabs'        => $tabs,
            'role'        => $role,
            'past'        => $past,
            'page'        => $page,
            'hasNextPage' => $page * Booking::PER_PAGE < $total,
            'bookings'    => array_map(fn (array $row): array => $this->listRow($row), $rows),
        ]);
    }

    /**
     * GET /bookings/{id} — only the borrower and the lender may open it.
     */
    public function show(int $id): void
    {
        $booking = $this->bookings->findForDetail($id);
        $me      = $this->userId();

        if ($booking === null || !in_array($me, [(int) $booking['borrower_id'], (int) $booking['lender_id']], true)) {
            $this->notice(404, ...self::NOT_FOUND);

            return;
        }

        $isLender = $me === (int) $booking['lender_id'];
        $side     = $isLender ? 'lender' : 'borrower';
        $state    = (string) $booking['status'];

        $this->render('bookings/detail', [
            'booking' => $booking,
            'role'    => $side,
            'status'  => $this->status($state),
            'actions' => [
                'accept'  => BookingService::mayAct('accept', $state, $isLender),
                'decline' => BookingService::mayAct('decline', $state, $isLender),
                'cancel'  => BookingService::mayAct('cancel', $state, $isLender),
            ],
            'answerBy' => $state === 'requested'
                ? date('j M Y, H:i', strtotime((string) $booking['requested_at']) + BookingService::ANSWER_HOURS * 3600)
                : '',
            'endedBy'  => $this->endedBy($booking, $me),
            'handover' => $this->handoverView($id, $side, $state),
            'return'   => $this->returnView($id, $side, $state),
            'claim'    => $this->claimView($id, $side),
            'claimItem' => [
                'title'          => (string) $booking['item_title'],
                'party'          => 'Borrowed by ' . $booking['borrower_name'],
                'photo'          => empty($booking['item_photo']) ? null : photo_url((string) $booking['item_photo']),
                'booking_id'     => $id,
                'declared_value' => (int) $booking['declared_value'],
                'simple_cap'     => DamageClaimService::simpleCap((int) $booking['declared_value']),
            ],
            'claimOpen' => $this->queryValue('claim') === '1',
            'dispute'   => $this->disputeView($id, $me),
            'rateHref'  => in_array($state, ['completed', 'cancelled', 'auto_cancelled'], true)
                && (new Rating($this->pdo))->byRater($me, 'booking', $id) === null
                ? base_url() . '/ratings?rate=booking-' . $id . '#rate-review'
                : null,
        ]);
    }

    /**
     * POST /items/{id}/borrow — send a request.
     */
    public function store(int $id): void
    {
        $input = new Validator($_POST);
        $input
            ->required('from_date', 'Start date')->date('from_date', 'Start date')
            ->required('to_date', 'End date')->date('to_date', 'End date')
            ->inList('basis', 'Rate', ['', 'daily', 'monthly'])
            ->maxLength('message', 'Message', BookingService::MESSAGE_MAX);

        try {
            if (!$input->passes()) {
                throw new ValidationException($input->errors());
            }

            $bookingId = self::service($this->pdo)->request(
                $id,
                $this->userId(),
                $input->value('from_date'),
                $input->value('to_date'),
                $input->value('basis'),
                $input->value('message')
            );
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/items/' . $id . '?' . http_build_query([
                'from'  => $input->value('from_date'),
                'to'    => $input->value('to_date'),
                'basis' => $input->value('basis'),
            ]));

            return;
        } catch (RecordNotFoundException) {
            $this->notice(404, 'Listing not found', 'This listing does not exist, or it has been removed.');

            return;
        }

        $this->flash('Request sent. The lender has 24 hours to answer; no points move until they accept.');
        $this->redirect('/bookings/' . $bookingId);
    }

    /**
     * POST /bookings/{id}/accept.
     */
    public function accept(int $id): void
    {
        $this->attempt(function () use ($id): string {
            self::service($this->pdo)->accept($id, $this->userId());

            return 'Request accepted. The charge and the late buffer are held in escrow until the handover.';
        }, '/bookings/' . $id, self::NOT_FOUND);
    }

    /**
     * POST /bookings/{id}/decline.
     */
    public function decline(int $id): void
    {
        $this->attempt(function () use ($id): string {
            self::service($this->pdo)->decline($id, $this->userId(), $this->posted('reason'));

            return 'Request declined. The borrower has been told.';
        }, '/bookings/' . $id, self::NOT_FOUND);
    }

    /**
     * POST /bookings/{id}/cancel.
     */
    public function cancel(int $id): void
    {
        $this->attempt(function () use ($id): string {
            self::service($this->pdo)->cancel($id, $this->userId());

            return 'Booking cancelled. Anything held in escrow went back to the borrower.';
        }, '/bookings/' . $id, self::NOT_FOUND);
    }

    /**
     * POST /bookings/{id}/handover/photos — this side's photos and note.
     */
    public function handoverPhotos(int $id): void
    {
        $this->attempt(function () use ($id): string {
            self::handovers($this->pdo)->savePhotos($id, $this->userId(), uploaded_files('photos'), $this->posted('note'));

            return 'Photos saved. Accept the handover when you are happy with the item’s condition.';
        }, '/bookings/' . $id, self::NOT_FOUND);
    }

    /**
     * POST /bookings/{id}/handover/accept.
     */
    public function handoverAccept(int $id): void
    {
        $this->attempt(fn (): string => self::handovers($this->pdo)->accept($id, $this->userId())
            ? 'Handover complete. The condition photos are now the baseline for the return.'
            : 'You accepted. Waiting for the other side to accept too.', '/bookings/' . $id . '#handover', self::NOT_FOUND);
    }

    /**
     * GET /bookings/{id}/handover-status — JSON for public/js/polling.js, so
     * each side sees the other accept without reloading (§21.1).
     */
    public function handoverStatus(int $id): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        try {
            $status = self::handovers($this->pdo)->status($id, $this->userId());
        } catch (RecordNotFoundException | AccessDeniedException) {
            http_response_code(404);
            $status = ['error' => 'not_found'];
        }

        print json_encode($status, JSON_THROW_ON_ERROR);
    }

    /**
     * POST /bookings/{id}/return/photos — this side's return photos and note.
     */
    public function returnPhotos(int $id): void
    {
        $this->attempt(function () use ($id): string {
            self::returns($this->pdo)->savePhotos($id, $this->userId(), uploaded_files('photos'), $this->posted('note'));

            return 'Return photos saved.';
        }, '/bookings/' . $id, self::NOT_FOUND);
    }

    /**
     * POST /bookings/{id}/return/accept — the lender accepts the condition.
     */
    public function returnAccept(int $id): void
    {
        $this->attempt(function () use ($id): string {
            $hoursLate = self::returns($this->pdo)->accept($id, $this->userId())['hours_late'];

            return $hoursLate === 0
                ? 'Return accepted. The booking is complete and the buffer went back to the borrower.'
                : sprintf('Return accepted. It came back %s late, so the late fee went to you.', plural($hoursLate, 'hour'));
        }, '/bookings/' . $id . '#return', self::NOT_FOUND);
    }

    /**
     * Both sides' photos, notes and acceptance, for the booking page.
     *
     * @return array<string, mixed>|null null before the booking is accepted
     */
    private function handoverView(int $id, string $me, string $state): ?array
    {
        if (in_array($state, ['requested', 'rejected'], true)) {
            return null;
        }

        $record = (new HandoverRecord($this->pdo))->forBooking($id);

        if ($record === null && $state !== 'awaiting_handover') {
            return null;
        }

        $sides = [];

        foreach (HandoverRecord::SIDES as $side) {
            $sides[$side] = [
                'label'    => $side === $me ? 'Your photos' : ucfirst($side) . '’s photos',
                'photos'   => photo_list(PhotoStore::paths($record[$side . '_photos'] ?? null), 'Photo'),
                'note'     => (string) ($record[$side . '_notes'] ?? ''),
                'accepted' => ($record[$side . '_accepted_at'] ?? null) !== null,
            ];
        }

        $open = HandoverService::sideOpen($record, $me, $state);

        return [
            'me'         => $me,
            'sides'      => $sides,
            'locked'     => ($record['handover_at'] ?? null) !== null,
            'at'         => empty($record['handover_at']) ? '' : date('j M Y, H:i', strtotime((string) $record['handover_at'])),
            'can_edit'   => $open,
            'can_accept' => $open && $sides[$me]['photos'] !== [],
            'waiting'    => $state === 'awaiting_handover',
        ];
    }

    /**
     * The return photos beside the handover baseline, for the booking page.
     *
     * @return array<string, mixed>|null null until the item is out
     */
    private function returnView(int $id, string $me, string $state): ?array
    {
        if (!in_array($state, ['in_progress', 'awaiting_return', 'pending_moderator', 'escalated', 'completed'], true)) {
            return null;
        }

        $record = (new ReturnRecord($this->pdo))->forBooking($id);

        if ($record === null && !in_array($state, ['in_progress', 'awaiting_return'], true)) {
            return null;
        }

        $sides = [];

        foreach (HandoverRecord::SIDES as $side) {
            $sides[$side] = [
                'label'  => $side === $me ? 'Your return photos' : ucfirst($side) . '’s return photos',
                'photos' => photo_list(PhotoStore::paths($record[$side . '_photos'] ?? null), 'Return'),
                'note'   => (string) ($record[$side . '_notes'] ?? ''),
            ];
        }

        $open     = ReturnService::photosOpen($record, $state);
        $decision = $record['lender_decision'] ?? null;

        return [
            'me'         => $me,
            'sides'      => $sides,
            'returned'   => empty($record['return_at']) ? '' : date('j M Y, H:i', strtotime((string) $record['return_at'])),
            'decision'   => $decision === null ? '' : ($decision === 'accepted' ? 'The lender accepted the item’s condition.' : 'The lender raised a damage claim.'),
            'can_edit'   => $open,
            'can_accept' => $open && $me === 'lender' && $state === 'awaiting_return' && $sides['lender']['photos'] !== [],
            'can_claim'  => $open && $me === 'lender' && $state === 'awaiting_return',
        ];
    }

    /**
     * The claim on this booking, if any, and what this member can do about it.
     *
     * @return array<string, mixed>|null
     */
    private function claimView(int $bookingId, string $me): ?array
    {
        $claim = (new DamageClaim($this->pdo))->latestForBooking($bookingId);

        if ($claim === null) {
            return null;
        }

        $paths = PhotoStore::paths($claim['evidence_photos']);

        if ($paths === [] && !empty($claim['evidence_path'])) {
            $paths = [(string) $claim['evidence_path']];
        }

        $status   = (string) $claim['status'];
        $recorded = $claim['met_at'] !== null && $claim['resolution_closed_at'] === null;

        return [
            'id'          => (int) $claim['id'],
            'severity'    => ucfirst(str_replace('_', ' ', (string) $claim['severity'])),
            'penalty'     => (int) $claim['proposed_penalty'],
            'description' => (string) ($claim['description'] ?? ''),
            'track'       => $claim['track'] === 'simple' ? 'Simple path' : 'Moderator path',
            'status'      => match ($status) {
                'awaiting_borrower' => 'Waiting for the borrower, until ' . date('j M Y, H:i', strtotime((string) $claim['created_at']) + DamageClaimService::ANSWER_HOURS * 3600),
                'pending_moderator' => $recorded ? 'Moderator’s resolution recorded — waiting for both signatures' : 'With the moderator',
                'escalated'         => 'With the Admin',
                'resolved'          => 'Resolved',
                'closed'            => $claim['borrower_response'] === 'accepted' ? 'Accepted by the borrower' : 'Withdrawn',
                default             => ucfirst($status),
            },
            'photos'      => photo_list($paths, 'Evidence'),
            'resolution'  => $claim['met_at'] === null ? null : [
                'moderator' => (string) ($claim['moderator_name'] ?? ''),
                'penalty'   => (int) $claim['penalty_points'],
                'notes'     => (string) ($claim['resolution_notes'] ?? ''),
                'lender'    => $claim['lender_signoff_at'] !== null,
                'borrower'  => $claim['borrower_signoff_at'] !== null,
            ],
            'can_answer'   => $me === 'borrower' && $status === 'awaiting_borrower',
            'can_withdraw' => $me === 'lender' && $status === 'awaiting_borrower',
            'can_sign'     => $status === 'pending_moderator' && $recorded && $claim[$me . '_signoff_at'] === null,
        ];
    }

    /**
     * The dispute on this booking, or whether one can be raised now.
     *
     * @return array<string, mixed>|null null when there is nothing to show
     */
    private function disputeView(int $bookingId, int $me): ?array
    {
        $disputes = new Dispute($this->pdo);
        $dispute  = $disputes->latestForBooking($bookingId);
        $claim    = (new DamageClaim($this->pdo))->latestForBooking($bookingId);
        $route    = DisputeService::route(
            $claim,
            $claim === null ? 0 : $disputes->countOpenForClaim((int) $claim['id']),
            new DateTimeImmutable()
        );
        $canRaise = in_array($route, ['accepted', 'resolution'], true);

        if ($dispute === null && !$canRaise) {
            return null;
        }

        return [
            'can_raise' => $canRaise,
            'route'     => $route,
            'record'    => $dispute === null ? null : [
                'id'        => (int) $dispute['id'],
                'reason'    => (string) $dispute['reason'],
                'status'    => match ((string) $dispute['status']) {
                    'open'  => $dispute['admin_id'] === null ? 'Waiting for the Admin' : 'The Admin is reviewing it',
                    'ruled' => 'Ruled ' . date('j M Y', strtotime((string) $dispute['ruling_at'])),
                    default => 'Closed',
                },
                'ruling'    => (string) ($dispute['resolution'] ?? ''),
                'raised_by' => (string) $dispute['raised_by_name'],
                'mine'      => (int) $dispute['raised_by'] === $me,
                'editable'  => (int) $dispute['raised_by'] === $me && $dispute['status'] === 'open' && $dispute['admin_id'] === null,
                'open'      => $dispute['status'] === 'open',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function listRow(array $row): array
    {
        [$badge, $glyph, $label] = $this->status((string) $row['status']);

        return [
            'title'        => (string) $row['item_title'],
            'photo'        => empty($row['photo']) ? null : photo_url((string) $row['photo']),
            'meta'         => sprintf(
                '%s · %s – %s · %s pts rental charge',
                $row['counterparty'],
                date('j M Y', strtotime((string) $row['start_date'])),
                date('j M Y', strtotime((string) $row['end_date'])),
                $row['rental_charge']
            ),
            'status'       => $badge,
            'status_glyph' => $glyph,
            'status_label' => $label,
            'href'         => base_url() . '/bookings/' . $row['id'],
        ];
    }

    /**
     * Who ended a booking that did not run, in words for the booking page.
     *
     * @param array<string, mixed> $booking
     */
    private function endedBy(array $booking, int $me): string
    {
        return match ((string) $booking['status']) {
            'rejected'       => 'Declined by the lender' . ($booking['decline_reason'] ? ': ' . $booking['decline_reason'] : '.'),
            'auto_cancelled' => 'Cancelled automatically: nobody acted in time. Anything held in escrow went back to the borrower.',
            'cancelled'      => (int) ($booking['cancelled_by'] ?? 0) === $me ? 'You cancelled this booking.' : 'The other member cancelled this booking.',
            default          => '',
        };
    }

    /**
     * @return array{0: string, 1: string, 2: string} badge class, glyph, label
     */
    private function status(string $state): array
    {
        return match ($state) {
            'requested'                     => ['info', 'i', 'Awaiting lender response'],
            'accepted', 'awaiting_handover' => ['warning', '!', 'Handover pending'],
            'in_progress'                   => ['success', '✓', 'In progress'],
            'awaiting_return'               => ['warning', '!', 'Return pending'],
            'pending_moderator'             => ['info', 'i', 'Pending moderator'],
            'escalated'                     => ['info', 'i', 'With the Admin'],
            'completed'                     => ['success', '✓', 'Completed'],
            'rejected'                      => ['neutral', '—', 'Declined'],
            'cancelled'                     => ['neutral', '—', 'Cancelled'],
            'auto_cancelled'                => ['neutral', '—', 'Auto-cancelled'],
            default                         => ['neutral', 'i', ucfirst(str_replace('_', ' ', $state))],
        };
    }
}
