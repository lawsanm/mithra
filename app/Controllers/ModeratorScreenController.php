<?php

declare(strict_types=1);

final class ModeratorScreenController extends Controller
{
    public function show(string $view, array $params = []): void
    {
        $division = (new GnDivision($this->pdo))->moderatedBy($this->userId());
        if ($division === null) {
            $this->notice(403, 'No division assigned', 'This account does not moderate a division.');
            return;
        }
        $data = match ($view) {
            'moderator/dashboard/index' => $this->dashboard($division),
            'moderator/cases/index' => $this->cases($division),
            'moderator/cases/show' => $this->caseDetail($division, (int) ($params['id'] ?? 0)),
            'moderator/aid-vouching/index' => $this->aid($division),
            default => null,
        };
        if ($data === null) {
            $this->notice(404, 'Record not found', 'Choose an existing record in your division.');
            return;
        }
        $this->render($view, $data);
    }

    private function dashboard(int $division): array
    {
        $user = (new User($this->pdo))->findWithDivision($this->userId());
        $verifications = (new UserDivision($this->pdo))->queueForDivision($division, 'pending');
        $approvals = array_values(array_filter((new Item($this->pdo))->reviewQueue([$division], 'pending'),
            fn (array $row): bool => (int) $row['owner_id'] !== $this->userId()));
        $cases = $this->caseRows($division);
        $open = array_values(array_filter($cases, static fn (array $row): bool => $row['state'] === 'open'));
        $aid = $this->aid($division);
        return ['moderator' => ['greeting' => $this->greeting(User::shortName((string) $user['full_name'])),
                'membership' => $user['division_name'] . ' GN Division moderator · Conduct bond: '
                    . number_format((new Wallet($this->pdo))->bondLocked($this->userId())) . ' pts'],
            'stats' => [
                ['label' => 'Pending verifications', 'value' => (string) count($verifications), 'note' => 'Home membership applications', 'href' => base_url() . '/moderator/verifications'],
                ['label' => 'Listings to approve', 'value' => (string) count($approvals), 'note' => 'Your review queue', 'href' => base_url() . '/moderator/listing-approvals'],
                ['label' => 'Active damage cases', 'value' => (string) count($open), 'note' => 'In your division', 'href' => base_url() . '/moderator/cases'],
                ['label' => 'Aid requests', 'value' => (string) count($aid['requests']), 'note' => 'In your division', 'href' => base_url() . '/moderator/aid-vouching'],
            ],
            'verifications' => array_map(static fn (array $row): array => ['initials' => User::initials($row['full_name']),
                'title' => $row['full_name'], 'meta' => $row['division_name'] . ' · ' . date('j M Y', strtotime($row['created_at'])),
                'status' => 'warning', 'status_label' => 'Pending', 'href' => base_url() . '/moderator/verifications/' . $row['id']], $verifications),
            'approvals' => array_map(static fn (array $row): array => ['title' => $row['title'],
                'photo' => empty($row['photo']) ? null : photo_url((string) $row['photo']),
                'meta' => 'Listed by ' . $row['owner_name'] . ' · ' . date('j M Y', strtotime($row['created_at'])),
                'status' => 'warning', 'status_label' => 'Pending approval', 'href' => base_url() . '/moderator/listing-approvals/' . $row['id']], $approvals),
            'cases' => $open,
            'relief' => ((new DisasterEvent($this->pdo))->activeForDivision($division) === null ? 'No active disaster' : 'Disaster Mode active')
                . ' · ' . $aid['filterSummary']];
    }

    private function caseRows(int $division): array
    {
        return array_map(static function (array $row): array {
            $state = match ($row['status']) { 'resolved', 'closed' => 'resolved', 'escalated' => 'escalated', default => 'open' };
            return ['id' => (int) $row['id'], 'state' => $state, 'title' => 'Case #' . $row['id'] . ' — ' . $row['item_title'],
                'photo' => empty($row['photo']) ? null : photo_url((string) $row['photo']),
                'meta' => $row['lender_name'] . ' ↔ ' . $row['borrower_name'] . ' · ' . $row['severity'],
                'status' => $state === 'resolved' ? 'success' : 'warning',
                'status_label' => ucfirst(str_replace('_', ' ', $row['status'])),
                'href' => base_url() . '/moderator/cases/' . $row['id'], 'record' => $row];
        }, (new DamageClaim($this->pdo))->forDivision($division));
    }

    private function cases(int $division): array
    {
        $state = $this->queryValue('status');
        $rows = $this->caseRows($division);
        return ['filters' => $this->filters(['' => 'All', 'open' => 'Open', 'resolved' => 'Resolved', 'escalated' => 'Escalated'], $state),
            'cases' => array_values(array_filter($rows, static fn (array $row): bool => $state === '' || $row['state'] === $state)),
            'filterSummary' => count(array_filter($rows, static fn (array $row): bool => $row['state'] === 'open')) . ' open'];
    }

    private function caseDetail(int $division, int $id): ?array
    {
        $case = null;
        foreach ($this->caseRows($division) as $row) {
            if ($row['id'] === $id) { $case = $row; break; }
        }
        if ($case === null) { return null; }
        $row = $case['record'];
        $parties = [];
        $signoffs = [];
        foreach (['lender' => 'Lender', 'borrower' => 'Borrower'] as $key => $label) {
            $parties[] = ['initials' => User::initials($row[$key . '_name']), 'name' => $row[$key . '_name'], 'meta' => $label];
            $signed = $row[$key . '_signoff_at'] !== null;
            $signoffs[] = ['name' => $label . ' — ' . $row[$key . '_name'], 'status' => $signed ? 'success' : 'warning', 'status_label' => $signed ? 'Signed' : 'Pending'];
        }
        return ['case' => $case + ['moderator_is_party' => in_array($this->userId(), [(int) $row['lender_id'], (int) $row['borrower_id']], true),
                'escalated' => $row['status'] === 'escalated'], 'parties' => $parties,
            'report' => (string) $row['description'], 'evidence' => [],
            'timeline' => [['title' => 'Damage reported', 'time' => date('j M Y, H:i', strtotime($row['created_at']))]],
            'signoffs' => $signoffs];
    }

    private function aid(int $division): array
    {
        $state = $this->queryValue('status');
        $rows = array_map(static function (array $row): array {
            $state = match ($row['status']) { 'requested', 'info_requested' => 'awaiting', 'rejected_moderator', 'rejected_liaison' => 'rejected', default => 'vouched' };
            return ['id' => (string) $row['id'], 'state' => $state, 'title' => $row['member_name'] . ' — ' . $row['requested_amount'] . ' pts',
                'meta' => $row['purpose'] . ' · ' . date('j M Y', strtotime($row['created_at'])), 'status' => 'info',
                'status_label' => ucfirst(str_replace('_', ' ', $row['status'])), 'vouchable' => $state === 'awaiting'];
        }, (new AidGrant($this->pdo))->records(null, $division));
        return ['filters' => $this->filters(['' => 'All', 'awaiting' => 'Awaiting vouch', 'vouched' => 'Vouched', 'rejected' => 'Rejected'], $state),
            'requests' => array_values(array_filter($rows, static fn (array $row): bool => $state === '' || $row['state'] === $state)),
            'filterSummary' => count(array_filter($rows, static fn (array $row): bool => $row['state'] === 'awaiting')) . ' awaiting vouch'];
    }

    private function filters(array $labels, string $selected): array
    {
        $filters = [];
        foreach ($labels as $state => $label) {
            $filters[] = ['state' => $state, 'label' => $label, 'active' => $state === $selected];
        }
        return $filters;
    }
}
