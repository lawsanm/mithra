<?php

declare(strict_types=1);

/** Navigation for the liaison's sample records; no contributions are written. */
final class SponsorLiaisonController
{
    private const SPONSORS = [
    ['id' => 1, 'name' => 'Northwind Co', 'email' => 'contact@northwind.lk', 'points' => '16,000 pts'],
    ['id' => 2, 'name' => 'ACM Corp',     'email' => 'hello@acm.lk',         'points' => '6,500 pts'],
    ['id' => 3, 'name' => 'Texa',         'email' => 'team@texa.lk',         'points' => '7,500 pts'],
    ['id' => 4, 'name' => 'MNM',          'email' => 'contact@mnm.lk',       'points' => '7,000 pts'],
];

    private const PURCHASES = [
    ['id' => 1, 'date' => '15 Jul', 'sponsor' => 'Northwind Co', 'receipt' => 'INV-0312', 'allocation' => 'allocation 70% Sponsor · 30% Aid', 'amount' => 'LKR 10,000'],
    ['id' => 2, 'date' => '05 Jul', 'sponsor' => 'ACM Corp',     'receipt' => 'INV-0306', 'allocation' => 'allocation 50% Sponsor · 50% Aid', 'amount' => 'LKR 7,500'],
    ['id' => 3, 'date' => '28 Jun', 'sponsor' => 'MNM',          'receipt' => 'INV-0298', 'allocation' => 'allocation 100% Aid',              'amount' => 'LKR 6,500'],
    ['id' => 4, 'date' => '19 Jun', 'sponsor' => 'Global Ltd',   'receipt' => 'INV-0265', 'allocation' => 'allocation 70% Sponsor · 30% Aid', 'amount' => 'LKR 5,000'],
    ['id' => 5, 'date' => '10 Jun', 'sponsor' => 'Texa',         'receipt' => 'INV-0276', 'allocation' => 'allocation 60% Sponsor · 40% Aid', 'amount' => 'LKR 5,000'],
];

    private const GRANTS = [
    ['id' => 1, 'initials' => 'ML', 'name' => 'M. Lawsan',       'meta' => '300 pts · school supplies · vouched by Mod. J. Kavipriya · 15 Jul', 'status' => 'info',    'status_label' => 'Awaiting approval', 'action' => 'review'],
    ['id' => 2, 'initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => '200 pts · medical costs · vouched by Mod. J. Kavipriya · 14 Jul',    'status' => 'info',    'status_label' => 'Awaiting approval', 'action' => 'review'],
    ['id' => 3, 'initials' => 'JK', 'name' => 'J. Kavipriya',    'meta' => '450 pts · roof repair · awaiting moderator vouch · 16 Jul',         'status' => 'warning', 'status_label' => 'Awaiting vouch',    'action' => 'view'],
    ['id' => 4, 'initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => '500 pts · flood recovery · approved 01 Jul · funded by Northwind Co', 'status' => 'success', 'status_label' => 'Approved',          'action' => 'view'],
    ['id' => 5, 'initials' => 'AA', 'name' => 'J. Kavipriya',    'meta' => '400 pts · declined 28 Jun · insufficient evidence, may re-apply',   'status' => 'error',   'status_label' => 'Declined',          'action' => 'view'],
];

    public function __construct(private PDO $pdo)
    {
    }

    public function sponsors(): void
    {
        $search = $this->queryValue('q');
        $sort = $this->queryValue('sort');
        $status = $this->queryValue('status');
        $sponsors = array_values(array_filter(self::SPONSORS, static fn (array $row): bool =>
            str_contains(strtolower($row['name'] . ' ' . $row['email']), strtolower($search))
            && ($status === '' || $status === 'signed')));
        if ($sort === 'name') {
            usort($sponsors, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        } elseif ($sort === 'recently_added') {
            $sponsors = array_reverse($sponsors);
        } else {
            usort($sponsors, static fn (array $a, array $b): int =>
                (int) str_replace(',', '', $b['points']) <=> (int) str_replace(',', '', $a['points']));
        }
        $this->render('sponsors/index', compact('sponsors', 'search', 'sort', 'status'));
    }

    public function sponsor(int $id): void
    {
        $this->record('sponsors/show', self::SPONSORS, $id);
    }

    public function purchases(): void
    {
        $search = $this->queryValue('q');
        $sponsor = $this->queryValue('sponsor');
        $dateRange = $this->queryValue('date_range');
        $purchases = array_values(array_filter(self::PURCHASES, static fn (array $row): bool =>
            str_contains(strtolower($row['receipt']), strtolower($search))
            && ($sponsor === '' || $row['sponsor'] === $sponsor)
            && ($dateRange === '' || str_contains(strtolower($row['date']), strtolower($dateRange)))));
        $this->render('purchases/index', compact('purchases', 'search', 'sponsor', 'dateRange'));
    }

    public function purchase(int $id): void
    {
        $this->record('purchases/show', self::PURCHASES, $id);
    }

    public function grants(): void
    {
        $status = $this->queryValue('status');
        $labels = ['awaiting_vouch' => 'Awaiting vouch', 'awaiting_approval' => 'Awaiting approval',
            'approved' => 'Approved', 'declined' => 'Declined'];
        $grants = array_values(array_filter(self::GRANTS, static fn (array $row): bool =>
            $status === '' || $row['status_label'] === ($labels[$status] ?? '')));
        $this->render('aid-grants/index', compact('grants', 'status'));
    }

    public function grant(int $id): void
    {
        $row = $this->find(self::GRANTS, $id);
        if ($row === null) {
            $this->missing();
            return;
        }
        $parts = explode(' · ', $row['meta']);
        $grant = $row + ['grant_number' => '#A-' . (1041 + $id)];
        $request = ['purpose' => ucfirst($parts[1] ?? 'Aid request'), 'amount_requested' => $parts[0],
            'pool_balance' => 'Sample balance', 'prior_grants' => 'Not shown in this preview',
            'note' => $row['meta']];
        $vouch = ['initials' => 'JK', 'name' => 'Moderator review', 'note' => $parts[2] ?? 'See request status'];
        $draft = ['approved_amount' => (string) (int) $parts[0], 'reason' => ''];
        $this->render('aid-grants/show', compact('grant', 'request', 'vouch', 'draft'));
    }

    public function exportGrants(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="approved-grants-demo.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Demo grant ID', 'Member', 'Details', 'Status'], ',', '"', '');
        foreach (self::GRANTS as $grant) {
            if ($grant['status_label'] === 'Approved') {
                fputcsv($output, [$grant['id'], $grant['name'], $grant['meta'], $grant['status_label']], ',', '"', '');
            }
        }
        fclose($output);
    }

    private function record(string $view, array $rows, int $id): void
    {
        $record = $this->find($rows, $id);
        if ($record === null) {
            $this->missing();
            return;
        }
        $this->render($view, compact('record'));
    }

    private function find(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ($row['id'] === $id) return $row;
        }
        return null;
    }

    private function queryValue(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
    }

    private function missing(): void
    {
        http_response_code(404);
        $noticeTitle = 'Record not found';
        $noticeBody = 'Return to the list to select a sponsor, contribution or aid grant.';
        require dirname(__DIR__, 2) . '/views/errors/notice.php';
    }

    private function render(string $view, array $data): void
    {
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/views/sponsor-liaison/' . $view . '.php';
    }
}
