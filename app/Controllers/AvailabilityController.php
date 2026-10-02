<?php

declare(strict_types=1);

/**
 * Availability calendar (Plan 2.4) — the lender's blocked date ranges. The
 * blocks are read on the item's edit page; these actions change them and
 * send the lender back there.
 */
final class AvailabilityController extends Controller
{
    private AvailabilityService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->service = new AvailabilityService($pdo, new Item($pdo), new ItemAvailabilityBlock($pdo), new Booking($pdo));
    }

    /**
     * POST /items/{id}/availability — block a date range.
     */
    public function store(int $id): void
    {
        $this->change(
            $id,
            fn (Validator $input): int => $this->service->create(
                $id,
                $this->userId(),
                $input->value('start_date'),
                $input->value('end_date'),
                $input->value('note')
            ),
            'Dates blocked. Borrowers cannot request them.'
        );
    }

    /**
     * POST /availability-blocks/{id} — change a blocked range.
     */
    public function update(int $id): void
    {
        try {
            $itemId = $this->service->itemOf($id, $this->userId());
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->change(
            $itemId,
            fn (Validator $input): int => $this->service->update(
                $id,
                $this->userId(),
                $input->value('start_date'),
                $input->value('end_date'),
                $input->value('note')
            ),
            'Blocked dates updated.'
        );
    }

    /**
     * POST /availability-blocks/{id}/delete — open the dates again.
     */
    public function destroy(int $id): void
    {
        try {
            $itemId = $this->service->delete($id, $this->userId());
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->flash('Those dates are open for requests again.');
        $this->redirect('/items/' . $itemId . '/edit#availability');
    }

    /**
     * Create and update share their input rules and outcome handling.
     *
     * @param callable(Validator): int $save
     */
    private function change(int $itemId, callable $save, string $message): void
    {
        $input = new Validator($_POST);
        $input
            ->required('start_date', 'Start date')->date('start_date', 'Start date')
            ->required('end_date', 'End date')->date('end_date', 'End date')
            ->maxLength('note', 'Note', AvailabilityService::NOTE_MAX);

        try {
            if (!$input->passes()) {
                throw new ValidationException($input->errors());
            }

            $save($input);
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->redirect('/items/' . $itemId . '/edit#availability');
    }

    private function refuse(RuntimeException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->notice(403, 'Not your listing', 'You can only change the calendar of items you listed yourself.');

            return;
        }

        $this->notice(404, 'Not found', 'That listing or blocked range does not exist.');
    }
}
