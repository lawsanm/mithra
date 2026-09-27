<?php

declare(strict_types=1);

/** Read-only sponsor and liaison screens share the same persisted contributions. */
final class SponsorScreenController extends Controller
{
    public function show(string $view, array $params = []): void
    {
        $data = match ($view) {
            'sponsor/dashboard/index' => $this->dashboard(),
            'sponsor/branding/edit' => $this->branding(),
            'sponsor/csr-reports/index' => $this->sponsorReport(),
            'sponsor/disasters/show' => $this->event((int) ($params['id'] ?? 0)),
            'sponsor-liaison/dashboard/index' => $this->liaisonDashboard(),
            'sponsor-liaison/points-pool/index' => $this->pools(),
            'sponsor-liaison/points-pool/reserve-topup' => $this->reserveTopUp(),
            'sponsor-liaison/csr-reports/index' => $this->impact(),
            'sponsor-liaison/csr-reports/quarterly' => $this->quarterly(),
            'sponsor-liaison/disasters/index' => $this->disasters(),
            'sponsor-liaison/disasters/connection' => $this->connection(),
            'sponsor-liaison/purchases/create' => ['sponsors' => $this->sponsorOptions()],
            default => null,
        };
        if ($data === null) {
            $this->notice(404, 'Record not found', 'Choose an existing record from the list.');
            return;
        }
        $this->render($view, $data);
    }

    private function ownContributions(): array
    {
        return (new SponsorContribution($this->pdo))->records($this->userId());
    }

    private function contributionStats(array $rows): array
    {
        return [
            ['label' => 'Total contributed', 'value' => 'LKR ' . number_format(array_sum(array_column($rows, 'cash_amount'))), 'note' => count($rows) . ' recorded contributions'],
            ['label' => 'General points', 'value' => number_format(array_sum(array_column($rows, 'general_points'))), 'note' => 'Allocated to the Sponsor Pool'],
            ['label' => 'Aid points', 'value' => number_format(array_sum(array_column($rows, 'aid_points'))), 'note' => 'Allocated to the Aid Pool'],
        ];
    }

    private function dashboard(): array
    {
        $profile = (new Sponsor($this->pdo))->forUser($this->userId());
        $rows = $this->ownContributions();
        $notices = (new Notification($this->pdo))->displayForMember($this->userId());
        return ['sponsor' => ['greeting' => $this->greeting((string) ($profile['company_name'] ?? 'Sponsor')),
                'standing' => $profile === null ? 'No sponsor profile linked' : ((int) $profile['active'] === 1 ? 'Active' : 'Inactive')
                    . ' sponsor · Agreement: ' . $profile['agreement_status']],
            'stats' => [...$this->contributionStats($rows), ['label' => 'Unread alerts',
                'value' => (string) (new Notification($this->pdo))->unreadCount($this->userId()),
                'note' => 'Your notifications', 'href' => base_url() . '/sponsor/notifications']],
            'callouts' => array_map(static fn (array $row): array => ['icon' => $row['icon'],
                'title' => $row['title'], 'meta' => $row['detail'] . ' · ' . $row['time'],
                'status' => $row['unread'] ? 'info' : 'neutral', 'status_label' => $row['unread'] ? 'Unread' : 'Read',
                'action_label' => 'View', 'action_href' => $row['href']], array_slice($notices, 0, 5)),
            'impact' => ['title' => 'Your CSR impact', 'note' => count($rows) . ' recorded contributions. See your CSR report for the General and Aid allocations.'],
            'activeEvents' => array_values(array_filter((new DisasterEvent($this->pdo))->records(), static fn (array $row): bool => $row['ended_at'] === null))];
    }

    private function branding(): array
    {
        $profile = (new Sponsor($this->pdo))->forUser($this->userId());
        $name = (string) ($profile['company_name'] ?? 'No sponsor profile linked');
        return ['draft' => ['display_name' => $name, 'tagline' => '', 'tag_bonuses' => false],
            'wall' => ['initials' => User::initials($name), 'name' => $name, 'tagline' => '',
                'meta' => 'LKR ' . number_format(array_sum(array_column($this->ownContributions(), 'cash_amount'))) . ' contributed'],
            'errors' => []];
    }

    private function sponsorReport(): array
    {
        $rows = $this->ownContributions();
        $groups = [];
        foreach ($rows as $row) {
            $date = new DateTimeImmutable($row['recorded_at']);
            $key = 'Q' . (intdiv((int) $date->format('n') - 1, 3) + 1) . ' ' . $date->format('Y');
            $groups[$key] ??= ['cash' => 0, 'general' => 0, 'aid' => 0];
            $groups[$key]['cash'] += (int) $row['cash_amount'];
            $groups[$key]['general'] += (int) $row['general_points'];
            $groups[$key]['aid'] += (int) $row['aid_points'];
        }
        $quarters = [];
        foreach ($groups as $label => $totals) {
            $quarters[] = ['label' => $label, 'meta' => 'General ' . number_format($totals['general']) . ' · Aid ' . number_format($totals['aid']),
                'amount' => 'LKR ' . number_format($totals['cash'])];
        }
        return ['stats' => $this->contributionStats($rows), 'quarters' => $quarters,
            'reconcileNote' => 'Totals are calculated from your recorded contributions. General and Aid points show the recorded allocation.'];
    }

    private function liaisonDashboard(): array
    {
        $sponsors = (new Sponsor($this->pdo))->summaries();
        $grants = array_values(array_filter((new AidGrant($this->pdo))->records(), static fn (array $row): bool => $row['status'] === 'vouched'));
        $disaster = $this->disasters()['disaster'];
        $users = new User($this->pdo);
        $me = $users->find($this->userId());
        return ['liaison' => ['greeting' => $this->greeting(User::shortName((string) $me['full_name'])),
                'coverage' => 'Sponsor Liaison · ' . (new GnDivision($this->pdo))->countAll() . ' GN divisions'],
            'stats' => [
                ['label' => 'Active sponsors', 'value' => (string) count(array_filter($sponsors, static fn (array $row): bool => (bool) $row['active'])), 'note' => 'Registered sponsors'],
                ['label' => 'Sponsor Pool', 'value' => number_format((new PointPool($this->pdo))->balance('sponsor')), 'note' => 'Current balance'],
                ['label' => 'Pending aid approvals', 'value' => (string) count($grants), 'note' => 'Vouched requests'],
                ['label' => 'Disaster Mode', 'value' => $disaster['active'] ? 'Active' : 'Inactive', 'note' => $disaster['note']],
            ], 'sponsors' => array_map(static fn (array $row): array => ['name' => $row['company_name'],
                'meta' => number_format((int) $row['cash']) . ' pts contributed · ' . $row['agreement_status'],
                'href' => base_url() . '/sponsor-liaison/sponsors/' . $row['id']], $sponsors),
            'aidGrants' => array_map(static fn (array $row): array => ['id' => $row['id'], 'initials' => User::initials($row['member_name']),
                'title' => $row['member_name'], 'meta' => $row['requested_amount'] . ' pts · ' . $row['purpose'] . ' · ' . $row['moderator_name'],
                'status' => 'info', 'status_label' => 'Awaiting approval'], $grants), 'disaster' => $disaster];
    }

    private function pools(): array
    {
        $pool = new PointPool($this->pdo);
        return ['stats' => [
            ['label' => 'Sponsor Pool', 'value' => number_format($pool->balance('sponsor')), 'note' => 'Current balance', 'class' => 'primary'],
            ['label' => 'Aid Pool', 'value' => number_format($pool->balance('aid')), 'note' => 'Current balance', 'class' => 'primary'],
        ], 'ledger' => array_map(static fn (array $row): array => [
            'date' => date('j M Y', strtotime($row['created_at'])),
            'title' => ucfirst(str_replace('_', ' ', $row['reason'])),
            'meta' => implode(' · ', array_filter([$row['company_name'], $row['receipt_number'], $row['from_name'], $row['to_name']])),
            'amount' => number_format((int) $row['amount']) . ' pts', 'amount_class' => 'primary',
            'balance_after' => ($row['from_pool_code'] ?? 'Member') . ' → ' . ($row['to_pool_code'] ?? 'Member'),
        ], (new PointLedger($this->pdo))->sponsorPoolActivity())];
    }

    /** Reserve top-up preview (Plan §7.7): live balances; saving is not built yet. */
    private function reserveTopUp(): array
    {
        $pool = new PointPool($this->pdo);
        $covers = new ShortfallCover($this->pdo);
        $year = $covers->yearStats();
        return ['stats' => [
            ['label' => 'Sponsor Pool', 'value' => number_format($pool->balance('sponsor')), 'note' => 'Available to move', 'class' => 'primary'],
            ['label' => 'Reserve Pool', 'value' => number_format($pool->balance('reserve')), 'note' => 'Current safety net', 'class' => 'primary'],
            ['label' => 'Shortfall covers this year', 'value' => number_format($year['total_pts']) . ' pts', 'note' => $year['covers'] . ' covers paid from the Reserve', 'class' => ''],
            ['label' => 'Top-ups this year', 'value' => number_format($covers->topUpsThisYear()) . ' pts', 'note' => 'Sponsor Pool → Reserve Pool', 'class' => ''],
        ]];
    }

    private function impact(): array
    {
        $rows = (new SponsorContribution($this->pdo))->records();
        return ['stats' => $this->contributionStats($rows),
            'sponsors' => array_map(static fn (array $row): array => ['name' => $row['company_name'],
                'meta' => 'General ' . number_format((int) $row['general']) . ' · Aid ' . number_format((int) $row['aid']),
                'contributed' => number_format((int) $row['cash']) . ' pts contributed'], (new Sponsor($this->pdo))->summaries())];
    }

    private function quarterly(): array
    {
        $quarter = max(1, min(4, (int) ($_GET['quarter'] ?? (intdiv((int) date('n') - 1, 3) + 1))));
        $year = max(2000, min(9999, (int) ($_GET['year'] ?? date('Y'))));
        $rows = array_values(array_filter((new SponsorContribution($this->pdo))->records(), static function (array $row) use ($quarter, $year): bool {
            $date = new DateTimeImmutable($row['recorded_at']);
            return (int) $date->format('Y') === $year && intdiv((int) $date->format('n') - 1, 3) + 1 === $quarter;
        }));
        $me = (new User($this->pdo))->find($this->userId());
        return ['report' => ['quarter' => 'Q' . $quarter . ' ' . $year, 'prepared_by' => $me['full_name'], 'prepared_at' => date('j M Y')],
            'stats' => $this->contributionStats($rows),
            'contributions' => array_map(static fn (array $row): array => ['sponsor' => $row['company_name'] . ' · ' . $row['receipt_number'],
                'amount' => 'LKR ' . number_format((int) $row['cash_amount']) . ' · General ' . $row['general_points'] . ' / Aid ' . $row['aid_points']], $rows),
            'footnote' => 'Based on contributions recorded during this quarter. 1 rupee = 1 point, with no deductions.'];
    }

    private function disasters(): array
    {
        $rows = (new DisasterEvent($this->pdo))->records();
        $active = count(array_filter($rows, static fn (array $row): bool => $row['ended_at'] === null));
        return ['disaster' => ['active' => $active > 0, 'status' => $active > 0 ? 'warning' : 'success',
                'status_label' => $active > 0 ? 'Disaster Mode active' : 'Currently inactive',
                'note' => $active . ' active disaster events'],
            'stats' => [['label' => 'Activations', 'value' => (string) count($rows), 'note' => 'All recorded events'],
                ['label' => 'Active events', 'value' => (string) $active, 'note' => 'Currently open']],
            'history' => array_map(static fn (array $row): array => [
                'period' => date('j M Y, H:i', strtotime($row['started_at'])) . ' → ' . ($row['ended_at'] === null ? 'Active' : date('j M Y, H:i', strtotime($row['ended_at']))),
                'meta' => $row['division_name'] . ' · ' . $row['reason'] . ' · Activated by ' . $row['started_by_name'],
                'duration' => $row['ended_at'] === null ? 'Ongoing' : (string) round((strtotime($row['ended_at']) - strtotime($row['started_at'])) / 3600) . ' hrs'], $rows)];
    }

    private function event(int $id): ?array
    {
        $row = null;
        foreach ((new DisasterEvent($this->pdo))->records() as $event) {
            if ((int) $event['id'] === $id) { $row = $event; break; }
        }
        if ($row === null) { return null; }
        return ['disaster' => ['title' => $row['reason'], 'status' => $row['ended_at'] === null ? 'warning' : 'neutral',
                'status_label' => $row['ended_at'] === null ? 'Active' : 'Ended',
                'meta' => $row['division_name'] . ' · Started ' . date('j M Y', strtotime($row['started_at']))],
            'moderator' => ['initials' => User::initials((string) $row['moderator_name']),
                'name' => ($row['moderator_name'] ?? 'No moderator assigned') . ' · ' . $row['division_name'],
                'quote' => $row['reason'], 'status_label' => $row['ended_at'] === null ? 'Current division moderator' : 'Event ended'],
            'activeAlert' => ['event_name' => $row['reason'], 'division' => $row['division_name'], 'affected' => 'Contact the liaison for current relief needs.'],
            'offerNote' => 'Arrange cash or goods with the liaison and moderator. Disaster relief does not create points.',
            'footerNote' => 'Relief distributions are recorded by the moderator.'];
    }

    private function sponsorOptions(): array
    {
        return array_map(static fn (array $row): array => ['id' => $row['id'], 'name' => $row['company_name']], (new Sponsor($this->pdo))->activeNames());
    }

    private function connection(): array
    {
        $events = (new DisasterEvent($this->pdo))->records();
        $eventId = (int) ($_GET['event'] ?? ($events[0]['id'] ?? 0));
        $event = null;
        foreach ($events as $row) {
            if ((int) $row['id'] === $eventId) { $event = $row; break; }
        }
        $contributions = array_values(array_filter((new DisasterContribution($this->pdo))->records(),
            static fn (array $row): bool => (int) $row['disaster_event_id'] === $eventId));
        return ['disaster' => ['title' => $event['reason'] ?? 'No disaster event selected',
                'status_label' => $event === null ? 'No event' : ($event['ended_at'] === null ? 'Active' : 'Ended'),
                'meta' => $event === null ? '' : $event['division_name'] . ' · Activated by ' . $event['started_by_name']],
            'sponsorSide' => ['initial' => '', 'name' => 'Contributing sponsors',
                'offer' => $contributions === [] ? 'No contributions recorded for this event.' : implode(', ', array_unique(array_column($contributions, 'company_name')))],
            'moderatorSide' => ['initials' => User::initials((string) ($event['moderator_name'] ?? '')),
                'name' => $event['moderator_name'] ?? 'Not assigned', 'note' => $event['division_name'] ?? ''],
            'connectedNote' => count($contributions) . ' contributions recorded',
            'log' => array_map(static fn (array $row): array => ['title' => $row['company_name'] . ' · ' . $row['description'],
                'date' => date('j M Y', strtotime($row['created_at']))], $contributions)];
    }
}
