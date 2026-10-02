<?php

declare(strict_types=1);

/**
 * Donations (Plan 2.5, §13) — the donor's request list, the handover page,
 * and every step between a request and a completed donation. The rules live
 * in DonationService.
 */
final class DonationController extends Controller
{
    private DonationService $service;
    private Donation $donations;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->donations = new Donation($pdo);
        $this->service   = new DonationService($pdo, $this->donations, new Item($pdo), new UserDivision($pdo), new Notification($pdo));
    }

    /**
     * GET /donations/{id} — the requests for one of my donations.
     */
    public function show(int $id): void
    {
        $row = $this->donations->forParticipant($id, $this->userId());

        if ($row === null || (int) $row['donor_id'] !== $this->userId()) {
            $this->notice(404, 'Donation not found', 'Choose one of your donations from My Items.');

            return;
        }

        $requests = $this->donations->requests($id, $this->userId());
        $open     = $row['status'] === 'open';

        $this->render('donations/index', [
            'donation' => [
                'id'            => $id,
                'item'          => (string) $row['title'],
                'status'        => (string) $row['status'],
                'status_label'  => $this->statusLabel((string) $row['status']),
                'request_count' => count($requests) . ' request' . (count($requests) === 1 ? '' : 's'),
                'first_come'    => $row['selection_mode'] === 'first_come',
                'open'          => $open,
                'cancellable'   => in_array($row['status'], ['open', 'recipient_selected'], true),
                'has_recipient' => $row['status'] === 'recipient_selected',
            ],
            'requests' => array_map(static fn (array $request): array => [
                'id'           => (int) $request['id'],
                'initials'     => User::initials((string) $request['full_name']),
                'name'         => (string) $request['full_name'],
                'meta'         => 'Trust ' . $request['trust_score'] . ' · ' . ($request['division_name'] ?? '') . ' · '
                    . date('j M Y', strtotime((string) $request['requested_at'])),
                'status'       => (string) $request['status'],
                'message'      => (string) ($request['message'] ?? ''),
                'profile_href' => base_url() . '/members/' . $request['requester_id'],
                'choosable'    => $open && $request['status'] === 'pending',
            ], $requests),
        ]);
    }

    /**
     * GET /donations/{id}/handover — for the donor and the chosen recipient.
     */
    public function handover(int $id): void
    {
        $row = $this->donations->forHandover($id);
        $me  = $this->userId();

        if ($row === null || !in_array($me, [(int) $row['donor_id'], (int) ($row['recipient_id'] ?? 0)], true)) {
            $this->notice(404, 'Donation not found', 'This handover is not available to your account.');

            return;
        }

        $isDonor   = $me === (int) $row['donor_id'];
        $mine      = $isDonor ? $row['donor_confirmed_at'] : $row['recipient_confirmed_at'];
        $theirs    = $isDonor ? $row['recipient_confirmed_at'] : $row['donor_confirmed_at'];
        $given     = $this->donations->countCompletedBy((int) $row['donor_id']);

        $this->render('donations/handover', [
            'donation'  => [
                'id'       => $id,
                'item'     => (string) $row['title'],
                'photo'    => empty($row['photo']) ? null : photo_url((string) $row['photo']),
                'meta'     => 'Donated by ' . $row['donor_name'] . ' · ' . $this->statusLabel((string) $row['status']),
                'is_donor' => $isDonor,
            ],
            'recipient' => [
                'initials' => User::initials((string) ($row['recipient_name'] ?? '')),
                'name'     => (string) ($row['recipient_name'] ?? 'No recipient chosen yet'),
                'meta'     => $row['recipient_name'] === null ? '' : 'Trust ' . $row['recipient_trust'],
            ],
            'state'     => [
                'waiting'   => $row['status'] === 'recipient_selected',
                'completed' => $row['status'] === 'completed',
                'mine'      => $mine !== null,
                'theirs'    => $theirs !== null,
            ],
            'badge'     => $isDonor
                ? 'Donor badge · ' . $given . ' completed donation' . ($given === 1 ? '' : 's')
                : '',
        ]);
    }

    /**
     * POST /donations/{id}/requests — ask for a donation.
     */
    public function request(int $id): void
    {
        $itemId = (int) (($this->donations->find($id) ?? [])['item_id'] ?? 0);

        try {
            $chosen = $this->service->request($id, $this->userId(), (string) ($_POST['message'] ?? ''));
            $this->flash($chosen
                ? 'You were first, so it is yours. Arrange the handover with the donor.'
                : 'Request sent. The donor chooses who receives it.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Donation not found', 'This donation is no longer available.');

            return;
        }

        $this->redirect($itemId > 0 ? '/items/' . $itemId : '/items/browse?type=donations');
    }

    /**
     * POST /donations/{id}/mode — first-come on or off.
     */
    public function mode(int $id): void
    {
        $mode = ($_POST['first_come'] ?? '') === '1' ? 'first_come' : 'donor_chooses';

        $this->donorAction($id, fn (): mixed => $this->service->setMode($id, $this->userId(), $mode),
            $mode === 'first_come' ? 'First-come is on: the first request is chosen automatically.' : 'First-come is off. You choose the recipient.');
    }

    /**
     * POST /donations/{id}/select — choose the recipient.
     */
    public function select(int $id): void
    {
        $requestId = (int) ($_POST['request_id'] ?? 0);

        $this->donorAction($id, fn (): mixed => $this->service->select($id, $this->userId(), $requestId),
            'Recipient chosen. Everyone else who asked has been told.');
    }

    /**
     * POST /donations/{id}/cancel — stop giving it away.
     */
    public function cancel(int $id): void
    {
        try {
            $this->service->cancel($id, $this->userId());
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/donations/' . $id);

            return;
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->flash('Donation cancelled and the listing removed.');
        $this->redirect('/items?type=donations');
    }

    /**
     * POST /donations/{id}/confirm — one side confirms the handover.
     */
    public function confirm(int $id): void
    {
        try {
            $completed = $this->service->confirm($id, $this->userId());
            $this->flash($completed
                ? 'Handover confirmed by both of you. The donation is complete.'
                : 'Confirmed. Waiting for the other side to confirm too.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->redirect('/donations/' . $id . '/handover');
    }

    /**
     * POST /donation-requests/{id}/withdraw.
     */
    public function withdraw(int $id): void
    {
        try {
            $itemId = $this->service->withdraw($id, $this->userId());
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            $this->redirect('/items/browse?type=donations');

            return;
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->flash('Request withdrawn.');
        $this->redirect('/items/' . $itemId);
    }

    private function donorAction(int $id, callable $action, string $message): void
    {
        try {
            $action();
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->redirect('/donations/' . $id);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'open'               => 'Taking requests',
            'recipient_selected' => 'Recipient chosen — handover pending',
            'completed'          => 'Completed',
            'cancelled'          => 'Cancelled',
            default              => ucfirst($status),
        };
    }

    private function refuse(RuntimeException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->notice(403, 'Not yours to change', 'Only the donor, or the member it concerns, can do that.');

            return;
        }

        $this->notice(404, 'Not found', 'This donation or request no longer exists.');
    }
}
