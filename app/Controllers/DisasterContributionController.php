<?php

declare(strict_types=1);

/** Read-only contribution records. Submission/confirmation actions remain disabled. */
final class DisasterContributionController extends Controller
{
    private const LABELS = ['awaiting' => 'Awaiting Moderator', 'ready' => 'Ready to verify', 'queried' => 'Queried', 'verified' => 'Verified', 'rejected' => 'Rejected'];

    public function show(string $view, array $params = []): void
    {
        $division = str_starts_with($view, 'moderator/') ? (new GnDivision($this->pdo))->moderatedBy($this->userId()) : null;
        if (str_starts_with($view, 'moderator/') && $division === null) {
            $this->notice(403, 'No division assigned', 'This account does not moderate a division.');
            return;
        }
        $rows = (new DisasterContribution($this->pdo))->records($division);
        $id = (int) ($params['id'] ?? 0);
        $row = null;
        foreach ($rows as $record) {
            if ((int) $record['id'] === $id) { $row = $record; break; }
        }
        $data = match ($view) {
            'sponsor-liaison/disasters/contributions/index' => $this->indexData($rows),
            'sponsor-liaison/disasters/contributions/create' => $this->formData(null),
            'sponsor-liaison/disasters/contributions/edit' => $row === null ? null : $this->formData($row),
            'sponsor-liaison/disasters/contributions/show' => $row === null ? null : $this->detail($row),
            'moderator/disasters/contributions/index' => $this->moderatorList($rows),
            'moderator/disasters/contributions/confirm' => $row === null ? null : $this->confirmation($row),
            default => null,
        };
        if ($data === null) {
            $this->notice(404, 'Record not found', 'Choose an existing contribution from the list.');
            return;
        }
        $this->render($view, $data + ['id' => $id]);
    }

    private function indexData(array $rows): array
    {
        $selected = $this->queryValue('status');
        $filters = [];
        foreach (['' => 'All'] + self::LABELS as $state => $label) {
            $filters[] = ['state' => $state, 'label' => $label, 'active' => $selected === $state];
        }
        $selectedRows = array_values(array_filter($rows, static fn (array $row): bool => $selected === '' || $row['status'] === $selected));
        return ['stats' => [['label' => 'Recorded contributions', 'value' => (string) count($rows), 'note' => 'Cash and goods for disaster relief']],
            'filters' => $filters, 'filterSummary' => count($selectedRows) . ' contributions',
            'contributions' => array_map(fn (array $row): array => $this->summary($row), $selectedRows)];
    }

    private function summary(array $row): array
    {
        return ['id' => (int) $row['id'], 'reference' => 'DC-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT),
            'sponsor' => $row['company_name'], 'disaster' => $row['event_name'], 'division' => $row['division_name'],
            'moderator' => $row['moderator_name'] ?? 'Not assigned', 'kind' => ucfirst($row['contribution_kind']),
            'description' => $row['description'], 'value' => $row['estimated_value'] === null ? 'Not valued' : 'LKR ' . number_format((int) $row['estimated_value']),
            'sponsor_proof' => $row['receipt_reference'] ?? 'No reference recorded',
            'moderator_check' => $row['ack_reference'] ?? 'Not confirmed',
            'status' => $row['status'] === 'verified' ? 'success' : 'info', 'status_label' => self::LABELS[$row['status']],
            'meta' => $row['division_name'] . ' · Recorded ' . date('j M Y', strtotime($row['created_at'])) . ' · ' . $row['recorder_name'],
            'editable' => $row['status'] !== 'verified'];
    }

    private function formData(?array $row): array
    {
        return ['disasters' => array_map(static fn (array $event): array => ['id' => $event['id'],
                'title' => $event['division_name'] . ' · ' . $event['reason'] . ' · ' . ($event['ended_at'] === null ? 'Active' : 'Ended')], (new DisasterEvent($this->pdo))->records()),
            'sponsors' => array_map(static fn (array $sponsor): array => ['id' => $sponsor['id'], 'name' => $sponsor['company_name']], (new Sponsor($this->pdo))->summaries()),
            'draft' => $row === null ? ['disaster_event_id' => '', 'sponsor_id' => '', 'contribution_kind' => 'goods',
                'description' => '', 'estimated_value' => '', 'handed_over_on' => '', 'receipt_reference' => '', 'notes' => '']
                : array_map(static fn (mixed $value): string => (string) $value, $row) + ['reference' => $this->summary($row)['reference']],
            'errors' => []];
    }

    private function detail(array $row): array
    {
        return ['contribution' => $this->summary($row), 'comparison' => [
                ['label' => 'What was given', 'sponsor' => $row['description'], 'moderator' => $row['received_description'] ?? 'Not confirmed', 'match' => $row['received_description'] === $row['description']],
                ['label' => 'Value', 'sponsor' => (string) ($row['estimated_value'] ?? 'Not valued'), 'moderator' => (string) ($row['received_value'] ?? 'Not valued'), 'match' => $row['received_value'] !== null && $row['received_value'] === $row['estimated_value']],
                ['label' => 'Receipt', 'sponsor' => $row['receipt_reference'] ?? 'Not recorded', 'moderator' => $row['ack_reference'] ?? 'Not recorded', 'match' => $row['confirmed_at'] !== null],
            ], 'evidence' => [], 'reliefRecords' => [], 'reliefTotal' => 'No relief records linked to this contribution.',
            'checks' => [], 'log' => [['title' => 'Recorded by ' . $row['recorder_name'], 'date' => date('j M Y, H:i', strtotime($row['created_at']))]],
            'draft' => ['verified_value' => (string) ($row['verified_value'] ?? ''), 'note' => (string) ($row['notes'] ?? '')]];
    }

    private function moderatorList(array $rows): array
    {
        $pending = [];
        $confirmed = [];
        foreach ($rows as $row) {
            $summary = $this->summary($row);
            if ($row['confirmed_at'] === null) {
                $pending[] = $summary + ['claim' => $row['description'] . ' · ' . $summary['value'], 'recorded' => $summary['meta']];
            } else {
                $confirmed[] = $summary + ['received' => $row['received_description'] ?? '', 'ack' => $row['ack_reference'] ?? ''];
            }
        }
        return compact('pending', 'confirmed');
    }

    private function confirmation(array $row): array
    {
        return ['claim' => $this->summary($row) + ['date' => $row['handed_over_on'] ?? 'Not recorded', 'receipt' => $row['receipt_reference'] ?? 'Not recorded'],
            'reliefRecords' => [], 'draft' => ['outcome' => '', 'received_description' => (string) ($row['received_description'] ?? ''),
                'received_value' => (string) ($row['received_value'] ?? ''), 'received_on' => (string) ($row['received_on'] ?? ''),
                'ack_reference' => (string) ($row['ack_reference'] ?? ''), 'note' => '']];
    }
}
