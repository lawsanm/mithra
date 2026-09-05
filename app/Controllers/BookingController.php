<?php

declare(strict_types=1);

/** Read-only booking navigation; decisions remain unavailable in the demo. */
final class BookingController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        $model = new Booking($this->pdo);
        $role = ($_GET['role'] ?? '') === 'lender' ? 'lender' : 'borrower';
        $tabs = [];
        foreach (['borrower', 'lender'] as $tabRole) {
            $tabs[] = ['label' => 'As ' . ucfirst($tabRole) . ' (' . $model->countForMember($this->memberId(), $tabRole) . ')',
                'role' => $tabRole, 'active' => $role === $tabRole];
        }
        $bookings = array_map(fn (array $row): array => $this->listRow($row), $model->forMember($this->memberId(), $role));
        $this->render('bookings/index', compact('tabs', 'bookings'));
    }

    public function show(int $id): void
    {
        $booking = (new Booking($this->pdo))->findForDetail($id);
        if ($booking === null || !in_array($this->memberId(), [(int) $booking['borrower_id'], (int) $booking['lender_id']], true)) {
            http_response_code(404);
            $this->render('errors/notice', ['noticeTitle' => 'Booking not found',
                'noticeBody' => 'Choose one of your bookings from My Bookings.']);
            return;
        }
        $role = $this->memberId() === (int) $booking['lender_id'] ? 'lender' : 'borrower';
        $status = $this->status($booking['status']);
        $this->render('bookings/detail', compact('booking', 'role', 'status'));
    }

    private function listRow(array $row): array
    {
        $status = $this->status((string) $row['status']);
        return ['title' => (string) $row['item_title'],
            'meta' => $row['counterparty'] . ' · ' . date('j M Y', strtotime($row['start_date']))
                . ' – ' . date('j M Y', strtotime($row['end_date'])) . ' · ' . $row['rental_charge'] . ' pts rental charge',
            'status' => $status[0], 'status_glyph' => $status[1], 'status_label' => $status[2],
            'href' => base_url() . '/bookings/' . $row['id']];
    }

    private function status(string $state): array
    {
        return match ($state) {
            'requested' => ['info', 'i', 'Awaiting lender response'],
            'accepted', 'awaiting_handover' => ['warning', '!', 'Handover pending'],
            'in_progress' => ['success', '✓', 'In progress'],
            'awaiting_return' => ['warning', '!', 'Return pending'],
            'pending_moderator' => ['info', 'i', 'Pending moderator'],
            'completed' => ['success', '✓', 'Completed'],
            'cancelled', 'auto_cancelled', 'declined' => ['neutral', '—', ucfirst(str_replace('_', ' ', $state))],
            default => ['neutral', 'i', ucfirst(str_replace('_', ' ', $state))],
        };
    }

    private function memberId(): int
    {
        return (int) ($_SESSION['user_id'] ?? Config::get('demo_member_id', 4));
    }

    private function render(string $view, array $data): void
    {
        $member = (new User($this->pdo))->find($this->memberId());
        $currentMember = ['initials' => User::initials((string) ($member['full_name'] ?? '')),
            'points_balance' => number_format((new Wallet($this->pdo))->balance($this->memberId())) . ' pts'];
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }
}
