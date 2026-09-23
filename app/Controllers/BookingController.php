<?php

declare(strict_types=1);

/**
 * My Bookings — the member's bookings as borrower or lender, read-only until
 * the Bookings module adds requests, handovers and returns.
 */
final class BookingController extends Controller
{
    /**
     * GET /bookings.
     */
    public function index(): void
    {
        $model = new Booking($this->pdo);
        $me    = $this->userId();
        $role  = ($_GET['role'] ?? '') === 'lender' ? 'lender' : 'borrower';

        $tabs = [];
        foreach (['borrower', 'lender'] as $tabRole) {
            $tabs[] = [
                'label'  => 'As ' . ucfirst($tabRole) . ' (' . $model->countForMember($me, $tabRole) . ')',
                'role'   => $tabRole,
                'active' => $role === $tabRole,
            ];
        }

        $bookings = array_map(fn (array $row): array => $this->listRow($row), $model->forMember($me, $role));

        $this->render('bookings/index', compact('tabs', 'bookings'));
    }

    /**
     * GET /bookings/{id} — only the borrower and the lender may open it.
     */
    public function show(int $id): void
    {
        $booking = (new Booking($this->pdo))->findForDetail($id);
        $me      = $this->userId();

        if ($booking === null || !in_array($me, [(int) $booking['borrower_id'], (int) $booking['lender_id']], true)) {
            $this->notice(404, 'Booking not found', 'Choose one of your bookings from My Bookings.');

            return;
        }

        $role   = $me === (int) $booking['lender_id'] ? 'lender' : 'borrower';
        $status = $this->status((string) $booking['status']);

        $this->render('bookings/detail', compact('booking', 'role', 'status'));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function listRow(array $row): array
    {
        [$badge, $glyph, $label] = $this->status((string) $row['status']);

        return [
            'title'        => (string) $row['item_title'],
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
     * @return array{0: string, 1: string, 2: string} badge class, glyph, label
     */
    private function status(string $state): array
    {
        return match ($state) {
            'requested'                               => ['info', 'i', 'Awaiting lender response'],
            'accepted', 'awaiting_handover'           => ['warning', '!', 'Handover pending'],
            'in_progress'                             => ['success', '✓', 'In progress'],
            'awaiting_return'                         => ['warning', '!', 'Return pending'],
            'pending_moderator'                       => ['info', 'i', 'Pending moderator'],
            'completed'                               => ['success', '✓', 'Completed'],
            'cancelled', 'auto_cancelled', 'declined' => ['neutral', '—', ucfirst(str_replace('_', ' ', $state))],
            default                                   => ['neutral', 'i', ucfirst(str_replace('_', ' ', $state))],
        };
    }
}
