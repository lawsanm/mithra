<?php

declare(strict_types=1);

/**
 * Listing approvals — the reviewer's queue and decision on declared values
 * (Plan §9.2). Moderators work it under /moderator/listing-approvals; the
 * Admin works the listings moderators may not decide (their own, and those of
 * divisions without a moderator) under /admin/listing-approvals.
 *
 * HTTP plumbing only (Rules/CONVENTIONS.md §6): who may decide what, and what a
 * decision writes, live in ListingReviewService. RbacMiddleware has already
 * limited each path to its role.
 */
final class ListingApprovalController extends Controller
{
    private ListingReviewService $reviews;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->reviews = new ListingReviewService(
            $pdo,
            new Item($pdo),
            new ItemValueReview($pdo),
            new GnDivision($pdo),
            new Notification($pdo),
            new Donation($pdo)
        );
    }

    /**
     * GET /moderator/listing-approvals, GET /admin/listing-approvals.
     */
    public function index(): void
    {
        $filter = $this->queryValue('status');
        $filter = in_array($filter, ListingReviewService::FILTERS, true) ? $filter : '';

        try {
            $rows    = $this->reviews->queue($this->userId(), $this->role(), $filter);
            $pending = $filter === 'pending'
                ? $rows
                : $this->reviews->queue($this->userId(), $this->role(), 'pending');
        } catch (AccessDeniedException $exception) {
            $this->notice(403, 'No queue here', 'This account does not moderate a division.');

            return;
        }

        $this->render('moderator/listing-approvals/index', [
            'filters'       => $this->filterPills($filter),
            'filterSummary' => count($pending) . ' pending',
            'listings'      => array_map(fn (array $row): array => $this->queueRow($row), $rows),
        ]);
    }

    /**
     * GET …/listing-approvals/{id}.
     */
    public function show(int $id): void
    {
        $this->renderReview($id, []);
    }

    /**
     * POST …/listing-approvals/{id}.
     */
    public function decide(int $id): void
    {
        $validator = new Validator($_POST);
        $validator
            ->inList('decision', 'Decision', ListingReviewService::DECISIONS)
            ->integer('declared_value', 'Corrected declared value', 1, ItemService::MAX_DECLARED_VALUE)
            ->maxLength('reason', 'Reason', 255)
            ->words('reason', 'Reason');

        if (!$validator->passes()) {
            $this->renderReview($id, $validator->errors());

            return;
        }

        $adjusted = $validator->value('declared_value');

        try {
            $title = $this->reviews->decide(
                $id,
                $this->userId(),
                $this->role(),
                $validator->value('decision'),
                $adjusted === '' ? null : (int) $adjusted,
                $validator->value('reason')
            );
        } catch (ValidationException $exception) {
            $this->renderReview($id, $exception->errors());

            return;
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $verb = match ($validator->value('decision')) {
            'approve' => 'approved',
            'adjust'  => 'approved with the corrected value',
            default   => 'rejected',
        };
        $this->flash(sprintf('“%s” was %s. The lender has been notified.', $title, $verb));
        $this->redirect($this->basePath());
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $errors
     */
    private function renderReview(int $id, array $errors): void
    {
        try {
            $record = $this->reviews->review($id, $this->userId(), $this->role());
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        if ($errors !== []) {
            http_response_code(422);
        }

        $listing = $record['listing'];
        $photos  = json_decode((string) ($listing['photos'] ?? '[]'), true);

        $this->render('moderator/listing-approvals/show', [
            'listing' => [
                'id'           => (int) $listing['id'],
                'title'        => (string) $listing['title'],
                'photo'        => empty($photos[0]) ? null : photo_url((string) $photos[0]),
                'status'       => $this->badgeFor($listing),
                'status_label' => $this->labelFor($listing),
                'meta'         => sprintf(
                    'Listed by %s  ·  trust %d  ·  submitted %s  ·  %s',
                    (string) $listing['owner_name'],
                    (int) $listing['trust_score'],
                    date('j M Y', strtotime((string) $listing['created_at'])),
                    (string) $listing['division_name']
                ),
                'description'    => (string) ($listing['description'] ?? ''),
                'declared_value' => (int) $listing['declared_value'],
                'decided'        => $listing['status'] !== 'pending_approval',
            ],
            'facts'       => $this->facts($listing),
            'requirement' => ItemService::proofRequirement((int) $listing['declared_value']),
            'proofGaps'   => ItemService::proofErrors(
                (int) $listing['declared_value'],
                $listing['value_proof_type'] === null ? null : (string) $listing['value_proof_type'],
                $listing['value_proof_path'] === null ? null : (string) $listing['value_proof_path']
            ),
            'photos'  => $this->photoLinks(is_array($photos) ? $photos : [], $listing['value_proof_path']),
            'trail'   => array_map(fn (array $row): array => $this->trailRow($row), $record['trail']),
            'errors'  => $errors,
            'old'     => [
                'decision'       => $this->posted('decision'),
                'declared_value' => $this->posted('declared_value'),
                'reason'         => $this->posted('reason'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $listing
     *
     * @return list<array{label: string, value: string}>
     */
    private function facts(array $listing): array
    {
        $proofType = $listing['value_proof_type'] === null
            ? 'None'
            : (ItemService::PROOF_TYPES[(string) $listing['value_proof_type']] ?? (string) $listing['value_proof_type']);

        $rates = [];
        if ($listing['daily_rate'] !== null) {
            $rates[] = number_format((int) $listing['daily_rate']) . ' pts / day';
        }
        if ($listing['monthly_rate'] !== null) {
            $rates[] = number_format((int) $listing['monthly_rate']) . ' pts / month';
        }

        return [
            ['label' => 'Category',       'value' => (string) $listing['category_name']],
            ['label' => 'Listing type',   'value' => $listing['listing_type'] === 'donation' ? 'Donation' : 'Rental'],
            ['label' => 'Declared value', 'value' => number_format((int) $listing['declared_value']) . ' pts'],
            ['label' => 'Proof offered',  'value' => $proofType],
            ['label' => 'Lender rate',    'value' => $rates === [] ? '—' : implode(' or ', $rates)],
        ];
    }

    /**
     * @param list<mixed> $photos
     *
     * @return list<array{label: string, url: string}>
     */
    private function photoLinks(array $photos, mixed $proofPath): array
    {
        $links = [];

        if (is_string($proofPath) && $proofPath !== '') {
            $links[] = ['label' => 'Proof of value', 'url' => photo_url($proofPath)];
        }

        foreach ($photos as $index => $path) {
            if (is_string($path)) {
                $links[] = ['label' => 'Photo ' . ((int) $index + 1), 'url' => photo_url($path)];
            }
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function queueRow(array $row): array
    {
        $proof = $row['value_proof_type'] === null
            ? 'no proof'
            : 'proof: ' . mb_strtolower(ItemService::PROOF_TYPES[(string) $row['value_proof_type']] ?? (string) $row['value_proof_type']);

        return [
            'title'        => (string) $row['title'],
            'photo'        => empty($row['photo']) ? null : photo_url((string) $row['photo']),
            'meta'         => sprintf(
                '%s  ·  %s  ·  declared %s pts  ·  %s  ·  %s',
                (string) $row['owner_name'],
                (string) $row['division_name'],
                number_format((int) $row['declared_value']),
                $proof,
                date('j M Y', strtotime((string) $row['updated_at']))
            ),
            'status'       => $this->badgeFor($row),
            'status_label' => $this->labelFor($row),
            'href'         => base_url() . $this->basePath() . '/' . (string) $row['id'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function trailRow(array $row): array
    {
        $change = (int) $row['previous_value'] === (int) $row['new_value']
            ? number_format((int) $row['new_value']) . ' pts'
            : number_format((int) $row['previous_value']) . ' → ' . number_format((int) $row['new_value']) . ' pts';

        return [
            'line' => sprintf(
                '%s by %s  ·  %s  ·  %s',
                ucfirst((string) $row['decision']),
                (string) $row['reviewer_name'],
                $change,
                date('j M Y, H:i', strtotime((string) $row['created_at']))
            ),
            'reason' => (string) ($row['reason'] ?? ''),
        ];
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function filterPills(string $active): array
    {
        $labels = ['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
        $pills  = [];

        foreach ($labels as $state => $label) {
            $pills[] = [
                'label'  => $label,
                'href'   => base_url() . $this->basePath() . ($state === '' ? '' : '?status=' . rawurlencode($state)),
                'active' => $state === $active,
            ];
        }

        return $pills;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function badgeFor(array $row): string
    {
        if ($row['status'] === 'pending_approval') {
            return $row['value_proof_type'] === 'inspection' ? 'info' : 'warning';
        }

        return $row['status'] === 'rejected' ? 'error' : 'success';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function labelFor(array $row): string
    {
        if ($row['status'] === 'pending_approval') {
            return $row['value_proof_type'] === 'inspection' ? 'Inspection requested' : 'Awaiting review';
        }

        return match ((string) $row['value_status']) {
            'adjusted' => 'Approved · value adjusted',
            'rejected' => 'Rejected',
            default    => 'Approved',
        };
    }

    /**
     * Moderator and Admin share these screens, each in its own navigation.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): void
    {
        $data['chrome']   = chrome_for($this->role());
        $data['basePath'] = base_url() . $this->basePath();

        parent::render($view, $data);
    }

    private function renderException(RecordNotFoundException|AccessDeniedException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->notice(403, 'Not yours to review', 'This listing is reviewed by its own division moderator, or by the Admin when the lister is a moderator.');

            return;
        }

        $this->notice(404, 'Record not found', 'This review is no longer available. Return to the queue to choose a record.');
    }

    private function basePath(): string
    {
        return $this->role() === 'admin' ? '/admin/listing-approvals' : '/moderator/listing-approvals';
    }
}
