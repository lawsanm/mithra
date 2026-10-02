<?php

declare(strict_types=1);

/**
 * Bookings (Plan 3.1) — My Bookings, the booking page, and the request,
 * accept, decline and cancel steps. The rules live in BookingService.
 */
final class BookingController extends Controller
{
    private Booking $bookings;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->bookings = new Booking($pdo);
    }

    /** Shared with the scheduled jobs, which call the same rules. */
    public static function service(PDO $pdo): BookingService
    {
        $wallets = new Wallet($pdo);

        return new BookingService(
            $pdo,
            new Booking($pdo),
            new Item($pdo),
            new UserDivision($pdo),
            new GnDivision($pdo),
            $wallets,
            new LedgerService($pdo, new PointLedger($pdo), new PointPool($pdo), $wallets),
            new AvailabilityService(new Item($pdo), new ItemAvailabilityBlock($pdo), new Booking($pdo)),
            new Notification($pdo)
        );
    }

    public static function handovers(PDO $pdo, PhotoStore $photos): HandoverService
    {
        $wallets = new Wallet($pdo);

        return new HandoverService(
            $pdo,
            new Booking($pdo),
            new HandoverRecord($pdo),
            new Item($pdo),
            new LedgerService($pdo, new PointLedger($pdo), new PointPool($pdo), $wallets),
            self::service($pdo),
            $photos,
            new Notification($pdo)
        );
    }

    public static function returns(PDO $pdo, PhotoStore $photos): ReturnService
    {
        $wallets = new Wallet($pdo);

        return new ReturnService(
            $pdo,
            new Booking($pdo),
            new ReturnRecord($pdo),
            new Item($pdo),
            new DamageClaim($pdo),
            new GnDivision($pdo),
            new LedgerService($pdo, new PointLedger($pdo), new PointPool($pdo), $wallets),
            $photos,
            new Notification($pdo)
        );
    }

    /**
     * GET /bookings — current bookings by role, or past ones with ?state=past.
     */
    public function index(): void
    {
        $me   = $this->userId();
        $role = ($_GET['role'] ?? '') === 'lender' ? 'lender' : 'borrower';
        $past = ($_GET['state'] ?? '') === 'past';
        $page = max(1, (int) ($_GET['page'] ?? 1));

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
            $this->notice(404, 'Booking not found', 'Choose one of your bookings from My Bookings.');

            return;
        }

        $isLender = $me === (int) $booking['lender_id'];
        $state    = (string) $booking['status'];

        $this->render('bookings/detail', [
            'booking' => $booking,
            'role'    => $isLender ? 'lender' : 'borrower',
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
            'handover' => $this->handoverView($id, $isLender ? 'lender' : 'borrower', $state),
            'return'   => $this->returnView($id, $isLender ? 'lender' : 'borrower', $state),
        ]);
    }

    /**
     * POST /bookings/{id}/return/photos — this side's return photos and note.
     */
    public function returnPhotos(int $id): void
    {
        $this->act($id, fn (): mixed => self::returns($this->pdo, $this->uploads())->savePhotos(
            $id,
            $this->userId(),
            uploaded_files('photos'),
            (string) ($_POST['note'] ?? '')
        ), 'Return photos saved.');
    }

    /**
     * POST /bookings/{id}/return/accept — the lender accepts the condition.
     */
    public function returnAccept(int $id): void
    {
        try {
            $outcome = self::returns($this->pdo, $this->uploads())->accept($id, $this->userId());
            $this->flash($outcome['hours_late'] === 0
                ? 'Return accepted. The booking is complete and the buffer went back to the borrower.'
                : sprintf('Return accepted. It came back %d hours late, so the late fee went to you.', $outcome['hours_late']));
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->notice(404, 'Booking not found', 'Choose one of your bookings from My Bookings.');

            return;
        }

        $this->redirect('/bookings/' . $id . '#return');
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
            $paths = HandoverService::decode($record[$side . '_photos'] ?? null);

            $sides[$side] = [
                'label'  => $side === $me ? 'Your return photos' : ucfirst($side) . '’s return photos',
                'photos' => array_map(static fn (string $path, int $index): array => [
                    'url'   => photo_url($path),
                    'label' => 'Return ' . ($index + 1),
                ], $paths, array_keys($paths)),
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
        ];
    }

    /**
     * POST /bookings/{id}/handover/photos — this side's photos and note.
     */
    public function handoverPhotos(int $id): void
    {
        $this->act($id, fn (): mixed => self::handovers($this->pdo, $this->uploads())->savePhotos(
            $id,
            $this->userId(),
            uploaded_files('photos'),
            (string) ($_POST['note'] ?? '')
        ), 'Photos saved. Accept the handover when you are happy with the item’s condition.');
    }

    /**
     * POST /bookings/{id}/handover/accept.
     */
    public function handoverAccept(int $id): void
    {
        try {
            $completed = self::handovers($this->pdo, $this->uploads())->accept($id, $this->userId());
            $this->flash($completed
                ? 'Handover complete. The condition photos are now the baseline for the return.'
                : 'You accepted. Waiting for the other side to accept too.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->notice(404, 'Booking not found', 'Choose one of your bookings from My Bookings.');

            return;
        }

        $this->redirect('/bookings/' . $id . '#handover');
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
            $status = self::handovers($this->pdo, $this->uploads())->status($id, $this->userId());
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            http_response_code(404);
            $status = ['error' => 'not_found'];
        }

        print json_encode($status, JSON_THROW_ON_ERROR);
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
            $paths = HandoverService::decode($record[$side . '_photos'] ?? null);

            $sides[$side] = [
                'label'    => $side === $me ? 'Your photos' : ucfirst($side) . '’s photos',
                'photos'   => array_map(static fn (string $path, int $index): array => [
                    'url'   => photo_url($path),
                    'label' => 'Photo ' . ($index + 1),
                ], $paths, array_keys($paths)),
                'note'     => (string) ($record[$side . '_notes'] ?? ''),
                'accepted' => ($record[$side . '_accepted_at'] ?? null) !== null,
            ];
        }

        $open = HandoverService::sideOpen($record, $me, $state);

        return [
            'me'        => $me,
            'sides'     => $sides,
            'locked'    => ($record['handover_at'] ?? null) !== null,
            'at'        => empty($record['handover_at']) ? '' : date('j M Y, H:i', strtotime((string) $record['handover_at'])),
            'can_edit'  => $open,
            'can_accept' => $open && $sides[$me]['photos'] !== [],
            'waiting'   => $state === 'awaiting_handover',
        ];
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
        } catch (RecordNotFoundException $exception) {
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
        $this->act($id, fn (BookingService $service): mixed => $service->accept($id, $this->userId()),
            'Request accepted. The charge and the late buffer are held in escrow until the handover.');
    }

    /**
     * POST /bookings/{id}/decline.
     */
    public function decline(int $id): void
    {
        $this->act($id, fn (BookingService $service): mixed => $service->decline($id, $this->userId(), (string) ($_POST['reason'] ?? '')),
            'Request declined. The borrower has been told.');
    }

    /**
     * POST /bookings/{id}/cancel.
     */
    public function cancel(int $id): void
    {
        $this->act($id, fn (BookingService $service): mixed => $service->cancel($id, $this->userId()),
            'Booking cancelled. Anything held in escrow went back to the borrower.');
    }

    /**
     * @param callable(BookingService): mixed $action receives the booking rules
     */
    private function act(int $id, callable $action, string $message): void
    {
        try {
            $action(self::service($this->pdo));
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->notice(404, 'Booking not found', 'Choose one of your bookings from My Bookings.');

            return;
        }

        $this->redirect('/bookings/' . $id);
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
