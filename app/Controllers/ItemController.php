<?php

declare(strict_types=1);

/**
 * Item listings — the member's own CRUD over what they lend and give away.
 *
 * Each action reads validated input, calls one service method (or a plain model
 * read), then renders a view or redirects. No SQL, no business rules, no point
 * arithmetic (Rules/CONVENTIONS.md §6).
 *
 * The acting member is whoever is signed in; RbacMiddleware admits members and
 * moderators (a moderator is a member too).
 */
final class ItemController extends Controller
{
    /** Session key holding the create wizard's half-finished listing. */
    private const DRAFT_KEY = 'item_draft';

    private const WIZARD_STEPS = 4;

    private ItemService $service;
    private Item $items;
    private ItemCategory $categories;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->items      = new Item($pdo);
        $this->categories = new ItemCategory($pdo);
        $this->service    = new ItemService($this->items, $this->categories, new Booking($pdo), PhotoStore::uploads());
    }

    // ── Read ────────────────────────────────────────────────────────────────

    /**
     * GET /items — My Items.
     */
    public function index(): void
    {
        $me   = $this->userId();
        $type = $this->listingTypeFilter($this->queryValue('type'));

        $rows   = $this->items->ownedBy($me, $type);
        $counts = $this->items->ownedCounts($me);

        $filters = [
            ['label' => 'All (' . $counts['all'] . ')',            'slug' => '',          'active' => $type === null],
            ['label' => 'Rentals (' . $counts['rental'] . ')',     'slug' => 'rentals',   'active' => $type === 'rental'],
            ['label' => 'Donations (' . $counts['donation'] . ')', 'slug' => 'donations', 'active' => $type === 'donation'],
        ];

        $items = array_map(fn (array $row): array => $this->myItemRow($row), $rows);

        $this->render('items/index', compact('filters', 'items'));
    }

    /**
     * GET /items/browse — everyone else's listings in the member's division,
     * or in their active temporary community with ?community=temporary
     * (Plan §6.5).
     */
    public function browse(): void
    {
        $me        = $this->userId();
        $member    = (new User($this->pdo))->findWithDivision($me) ?? [];
        $temporary = (new UserDivision($this->pdo))->activeTemporary($me);
        $community = $this->queryValue('community') === 'temporary' && $temporary !== null ? 'temporary' : 'home';
        $homeName  = (string) ($member['division_name'] ?? 'Home');

        if ($community === 'temporary') {
            $member['division_id']   = $temporary['gn_division_id'];
            $member['division_name'] = $temporary['division_name'];
        }

        $division = (int) ($member['division_id'] ?? 0);
        $query    = $this->queryValue('q');
        $page     = $this->page();
        $type     = $this->listingTypeFilter($this->queryValue('type'));

        $allCategories = $this->categories->allActive();
        $categoryId    = $this->categoryIdFromSlug($this->queryValue('category'), $allCategories);

        $rows  = $this->items->browse($division, $me, $categoryId, $query, $page, $type);
        $total = $this->items->countBrowse($division, $me, $categoryId, $query, $type);

        $categories = [[
            'label'  => 'All',
            'slug'   => '',
            'active' => $categoryId === null,
        ]];

        foreach ($allCategories as $category) {
            $categories[] = [
                'label'  => (string) $category['name'],
                'slug'   => $this->slug((string) $category['name']),
                'active' => (int) $category['id'] === $categoryId,
            ];
        }

        $results = array_map(fn (array $row): array => $this->browseCard($row), $rows);

        $resultCount = $total === 0
            ? ($query === '' ? 'No items listed here yet' : 'No results for “' . $query . '”')
            : $total . ' item' . ($total === 1 ? '' : 's') . ' available in ' . (string) ($member['division_name'] ?? 'your community');

        $this->render('items/browse', [
            'query'       => $query,
            'typeSlug'    => $type === null ? '' : ($type === 'rental' ? 'rentals' : 'donations'),
            'categories'  => $categories,
            'results'     => $results,
            'resultCount' => $resultCount,
            'page'        => $page,
            'hasNextPage' => $page * Item::PER_PAGE < $total,
            'community'   => $community,
            'savedSearches' => (new SavedSearchService(new SavedSearch($this->pdo)))->forMember($me),
            'communities' => $temporary === null ? [] : [
                'home'      => $homeName,
                'temporary' => (string) $temporary['division_name'],
            ],
        ]);
    }

    /**
     * GET /items/{id} — Item Detail.
     */
    public function show(int $id): void
    {
        $row = $this->items->findForDetail($id);

        if ($row === null) {
            $this->notice(404, 'Listing not found', 'This listing does not exist, or it has been removed.');

            return;
        }

        $me      = $this->userId();
        $isOwner = (int) $row['owner_id'] === $me;

        // An unapproved or paused listing is only visible to the member who owns it.
        if (!$isOwner && !in_array($row['status'], ['active', 'borrowed'], true)) {
            $this->notice(404, 'Listing not available', 'This listing is not on the shelf right now.');

            return;
        }

        [$badge, $glyph, $label] = $this->statusBadge((string) $row['status'], $row['due_back']);

        // Borrowing is for active members of the item's division only (§6.5, I5).
        $canBorrow = !$isOwner && $row['status'] === 'active' && $row['listing_type'] === 'rental'
            && in_array((int) $row['gn_division_id'], (new UserDivision($this->pdo))->activeDivisionIds($me), true);

        [$quote, $quoteError] = $canBorrow ? $this->quoteFromQuery($row) : [null, ''];

        $this->render('items/show', [
            'item' => [
                'id'             => (int) $row['id'],
                'title'          => (string) $row['title'],
                'owner'          => (string) $row['owner_name'],
                'owner_meta'     => $row['owner_name'] . ' · Trust ' . $row['trust_score'],
                'category'       => (string) $row['category_name'],
                'category_slug'  => $this->slug((string) $row['category_name']),
                'rate'           => $this->rateLabel($row),
                'declared_value' => 'Declared value: ' . number_format((int) $row['declared_value']) . ' pts',
                'description'    => (string) ($row['description'] ?? ''),
                'listing_type'   => (string) $row['listing_type'],
                'photos'         => array_map(photo_url(...), PhotoStore::paths($row['photos'])),
                'status'         => $badge,
                'status_glyph'   => $glyph,
                'status_label'   => $label,
                'can_borrow'     => $canBorrow,
            ],
            'owner' => [
                'initials' => User::initials((string) $row['owner_name']),
                'name'     => (string) $row['owner_name'],
                'verified' => $row['owner_status'] === 'active',
                'meta'     => sprintf(
                    'Trust score %d / 100  ·  %d successful lends  ·  Member since %s',
                    (int) $row['trust_score'],
                    (int) $row['owner_lends'],
                    $row['owner_joined'] !== null ? date('Y', strtotime((string) $row['owner_joined'])) : '—'
                ),
                'href'     => base_url() . '/members/' . $row['owner_id'],
            ],
            'isOwner' => $isOwner,
            'donation' => $row['listing_type'] === 'donation' ? $this->donationPanel((int) $row['id'], $me) : null,
            'calendar' => $row['listing_type'] === 'rental' ? $this->calendar((int) $row['id']) : [],
            'quote'      => $quote,
            'quoteError' => $quoteError,
            'quoteInput' => [
                'from'  => $this->queryValue('from'),
                'to'    => $this->queryValue('to'),
                'basis' => $quote['basis'] ?? '',
            ],
            'modalOpen'  => $quote !== null && $this->queryValue('request') === '1',
            'pricing'    => $this->pricing($row, $quote),
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────────────

    /**
     * GET /items/create — the four-step wizard.
     */
    public function createForm(): void
    {
        $draft = $this->draft();
        $step  = min($this->clampStep((int) $this->queryValue('step')), (int) $draft['step']);

        $this->renderWizard($step, $draft, []);
    }

    /**
     * POST /items — one wizard step, and on the last one the insert itself.
     */
    public function store(): void
    {
        $draft = $this->draft();
        $step  = min($this->clampStep((int) $this->posted('step')), (int) $draft['step']);

        $validator = new Validator($_POST);

        try {
            switch ($step) {
                case 1:
                    $this->detailRules($validator);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['name']        = $validator->value('name');
                    $draft['category']    = $validator->value('category');
                    $draft['description'] = $validator->value('description');
                    $draft['photos']      = array_merge(
                        $draft['photos'],
                        $this->service->storePhotos(uploaded_files('photos'), count($draft['photos']))
                    );

                    if ($draft['photos'] === []) {
                        $this->renderWizard(
                            $step,
                            $draft,
                            ['photos' => 'Add at least one photo so borrowers can see the item.'],
                            $validator->values()
                        );

                        return;
                    }

                    break;

                case 2:
                    $this->valueRules($validator);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['declared_value']   = $validator->value('declared_value');
                    $draft['value_proof_type'] = $validator->value('value_proof_type') ?: null;

                    $proof = $this->service->storeProof(uploaded_files('value_proof'));

                    if ($proof !== null) {
                        if ($draft['value_proof_path'] !== null) {
                            PhotoStore::uploads()->delete((string) $draft['value_proof_path']);
                        }

                        $draft['value_proof_path'] = $proof;
                    }

                    // Keep what was accepted so far, then hold the member at this
                    // step until the proof matches the value's tier (Plan §9.1).
                    $_SESSION[self::DRAFT_KEY] = $draft;

                    $proofErrors = ItemService::proofErrors(
                        (int) $draft['declared_value'],
                        $draft['value_proof_type'],
                        $draft['value_proof_path']
                    );

                    if ($proofErrors !== []) {
                        $this->renderWizard($step, $draft, $proofErrors, $validator->values());

                        return;
                    }

                    break;

                case 3:
                    $this->typeRules($validator);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['listing_type'] = $validator->value('listing_type');

                    break;

                default:
                    $this->rateRules($validator);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['daily_rate']   = $validator->value('daily_rate');
                    $draft['monthly_rate'] = $validator->value('monthly_rate');
                    $draft['community']    = $validator->value('community', 'home');

                    $division = CommunityController::service($this->pdo)
                        ->listingDivision($this->userId(), (string) $draft['community']);
                    $this->service->create($this->userId(), $division, $draft, $draft['photos']);

                    unset($_SESSION[self::DRAFT_KEY]);
                    $this->flash('Listing submitted. Your moderator reviews it before it goes live.');
                    $this->redirect('/items');

                    return;
            }
        } catch (ValidationException $exception) {
            $this->renderWizard($step, $draft, $exception->errors(), $validator->values());

            return;
        }

        $draft['step'] = max((int) $draft['step'], $step + 1);
        $_SESSION[self::DRAFT_KEY] = $draft;

        $this->redirect('/items/create?step=' . ($step + 1));
    }

    // ── Update ──────────────────────────────────────────────────────────────

    /**
     * GET /items/{id}/edit.
     */
    public function editForm(int $id): void
    {
        try {
            $row = $this->service->ownedOrFail($id, $this->userId());
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->renderEdit($row, [], $this->rowAsInput($row));
    }

    /**
     * POST /items/{id}.
     */
    public function update(int $id): void
    {
        $me = $this->userId();

        try {
            $row = $this->service->ownedOrFail($id, $me);
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $validator = new Validator($_POST);
        $this->detailRules($validator);
        $this->valueRules($validator);
        $this->typeRules($validator);
        $this->rateRules($validator);

        if (!$validator->passes()) {
            $this->renderEdit($row, $validator->errors(), $validator->values());

            return;
        }

        // Only paths already on the row can be kept — the checkbox values are
        // client input and are never trusted as file names (§8).
        $kept = array_values(array_intersect(
            PhotoStore::paths($row['photos']),
            array_map('strval', (array) ($_POST['keep_photos'] ?? []))
        ));

        try {
            $photos = array_merge(
                $kept,
                $this->service->storePhotos(uploaded_files('photos'), count($kept))
            );

            $input = $validator->values();
            $input['value_proof_path'] = $this->service->storeProof(uploaded_files('value_proof'));

            $requeued = $this->service->update($id, $me, $input, $photos);
        } catch (ValidationException $exception) {
            $this->renderEdit($row, $exception->errors(), $validator->values());

            return;
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->flash($requeued
            ? 'Listing updated. It goes back to your moderator for approval.'
            : 'Listing updated.');
        $this->redirect('/items');
    }

    // ── Delete (soft) and shelf state ───────────────────────────────────────

    /**
     * POST /items/{id}/archive.
     */
    public function archive(int $id): void
    {
        $this->transition(
            fn (int $me): mixed => $this->service->archive($id, $me),
            'Listing removed. Its rental history stays on your record.'
        );
    }

    /**
     * POST /items/{id}/pause.
     */
    public function pause(int $id): void
    {
        $this->transition(
            fn (int $me): mixed => $this->service->pause($id, $me),
            'Listing paused — borrowers cannot request it for now.'
        );
    }

    /**
     * POST /items/{id}/resume.
     */
    public function resume(int $id): void
    {
        $this->transition(
            fn (int $me): mixed => $this->service->resume($id, $me),
            'Listing is back on the shelf.'
        );
    }

    /**
     * The three shelf changes differ only in which service method runs; the
     * outcome handling — flash, redirect, refusal page — is identical.
     */
    private function transition(callable $change, string $message): void
    {
        try {
            $change($this->userId());
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $this->redirect('/items');
    }

    // ── View data ───────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function myItemRow(array $row): array
    {
        [$badge, $glyph, $label] = $this->statusBadge((string) $row['status'], $row['due_back'] ?? null);

        $meta = [ucfirst((string) $row['listing_type'])];

        if ($row['listing_type'] === 'rental') {
            $meta[] = $this->rateLabel($row);
        } else {
            $meta[] = 'declared ' . number_format((int) $row['declared_value']) . ' pts';
        }

        if ((int) $row['request_count'] > 0) {
            $meta[] = plural((int) $row['request_count'], 'request');
        } else {
            $meta[] = 'listed ' . date('j M Y', strtotime((string) $row['created_at']));
        }

        return [
            'id'           => (int) $row['id'],
            'title'        => (string) $row['title'],
            'meta'         => implode('  ·  ', $meta),
            'photo'        => empty($row['photo']) ? null : photo_url((string) $row['photo']),
            'status'       => $badge,
            'status_glyph' => $glyph,
            'status_label' => $label,
            'href'         => base_url() . '/items/' . $row['id'],
            'edit_href'    => base_url() . '/items/' . $row['id'] . '/edit',
            'requests_href' => empty($row['donation_id']) ? null : base_url() . '/donations/' . $row['donation_id'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function browseCard(array $row): array
    {
        [$badge, $glyph, $label] = $this->statusBadge((string) $row['status'], $row['back_on'] ?? null);

        return [
            'title'        => (string) $row['title'],
            'rate'         => $this->rateLabel($row),
            'photo'        => empty($row['photo']) ? null : photo_url((string) $row['photo']),
            'owner_meta'   => sprintf('%s  ·  Trust %d', $row['owner_name'], (int) $row['trust_score']),
            'status'       => $badge,
            'status_glyph' => $glyph,
            'status_label' => $label,
            'href'         => base_url() . '/items/' . $row['id'],
        ];
    }

    /**
     * Badge triple — class, glyph, label — for one listing status.
     *
     * @return array{0:string, 1:string, 2:string}
     */
    private function statusBadge(string $status, mixed $dueBack): array
    {
        if ($status === 'borrowed') {
            $label = $dueBack !== null
                ? 'Lent out — due ' . date('j M', strtotime((string) $dueBack))
                : 'Lent out';

            return ['info', 'i', $label];
        }

        return match ($status) {
            'active'           => ['success', '✓', 'Approved — available'],
            'pending_approval' => ['warning', '!', 'Pending moderator approval'],
            'paused'           => ['neutral', '⏸', 'Paused'],
            'rejected'         => ['error', '✕', 'Rejected — edit and resubmit'],
            'donated'          => ['neutral', '✓', 'Donated'],
            'archived'         => ['neutral', '—', 'Removed'],
            default            => ['neutral', '—', ucfirst($status)],
        };
    }

    /**
     * The quote for the dates in the query string, worked out on the server
     * so the page needs no JavaScript (Plan §8.4).
     *
     * @param array<string, mixed> $row
     *
     * @return array{0: array<string, mixed>|null, 1: string} the quote, or why there is none
     */
    private function quoteFromQuery(array $row): array
    {
        $from  = $this->queryValue('from');
        $to    = $this->queryValue('to');
        $basis = $this->queryValue('basis');

        if ($from === '' && $to === '') {
            return [null, ''];
        }

        $errors = BookingService::datesErrors($from, $to, date('Y-m-d'));

        if ($errors !== []) {
            return [null, implode(' ', $errors)];
        }

        $quote = BookingService::quote(
            $from,
            $to,
            $row['daily_rate'] === null ? null : (int) $row['daily_rate'],
            $row['monthly_rate'] === null ? null : (int) $row['monthly_rate'],
            $basis
        );

        return [$quote + ['from' => $from, 'to' => $to], ''];
    }

    /**
     * The rate options, with this quote's totals when there is one.
     *
     * @param array<string, mixed>      $row
     * @param array<string, mixed>|null $quote
     *
     * @return list<array<string, mixed>>
     */
    private function pricing(array $row, ?array $quote): array
    {
        $options = [];

        foreach (['daily' => 'Daily rate', 'monthly' => 'Monthly rate'] as $basis => $title) {
            $rate = $row[$basis . '_rate'];

            if ($rate === null) {
                continue;
            }

            $total = $quote === null ? null : $quote[$basis . '_total'];

            $options[] = [
                'value'       => $basis,
                'title'       => $title . ' · ' . number_format((int) $rate) . ' pts / ' . ($basis === 'daily' ? 'day' : 'month'),
                'total'       => $total === null ? 'Choose dates for a total' : number_format((int) $total) . ' pts for ' . $quote['days'] . ' day' . ($quote['days'] === 1 ? '' : 's'),
                'selected'    => $quote !== null && $quote['basis'] === $basis,
                'recommended' => $quote !== null && $quote['cheaper'] === $basis && $quote['daily_total'] !== null && $quote['monthly_total'] !== null
                    ? 'Cheaper'
                    : null,
            ];
        }

        return $options;
    }

    /**
     * What the item page offers on a donation: the request form, or the
     * member's own request and its status (Plan §13.1).
     *
     * @return array<string, mixed>|null
     */
    private function donationPanel(int $itemId, int $me): ?array
    {
        $donations = new Donation($this->pdo);
        $donation  = $donations->latestForItem($itemId);

        if ($donation === null) {
            return null;
        }

        $mine = $donations->requestBy((int) $donation['id'], $me);

        return [
            'id'          => (int) $donation['id'],
            'open'        => $donation['status'] === 'open',
            'is_donor'    => (int) $donation['donor_id'] === $me,
            'request'     => $mine === null || $mine['status'] === 'withdrawn' ? null : [
                'id'     => (int) $mine['id'],
                'status' => (string) $mine['status'],
                'label'  => match ((string) $mine['status']) {
                    'pending'  => 'Your request is waiting for the donor.',
                    'selected' => 'You were chosen to receive it.',
                    default    => 'Another member was chosen.',
                },
            ],
            'handover'    => $donation['status'] === 'recipient_selected' && (int) ($donation['recipient_id'] ?? 0) === $me,
        ];
    }

    /**
     * The dates a borrower cannot have: the lender's blocks and the bookings
     * already holding the item, in date order.
     *
     * @return list<array{kind: string, label: string}>
     */
    private function calendar(int $itemId): array
    {
        $ranges = [];

        foreach ((new ItemAvailabilityBlock($this->pdo))->upcomingForItem($itemId) as $block) {
            $ranges[] = ['start' => (string) $block['start_date'], 'kind' => 'Unavailable',
                'label' => $this->rangeLabel((string) $block['start_date'], (string) $block['end_date'])];
        }

        foreach ((new Booking($this->pdo))->bookedRanges($itemId) as $booking) {
            $ranges[] = ['start' => (string) $booking['start_date'], 'kind' => 'Booked',
                'label' => $this->rangeLabel((string) $booking['start_date'], (string) $booking['end_date'])];
        }

        usort($ranges, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        return array_map(static fn (array $range): array => ['kind' => $range['kind'], 'label' => $range['label']], $ranges);
    }

    private function rangeLabel(string $start, string $end): string
    {
        return $start === $end
            ? date('j M Y', strtotime($start))
            : date('j M', strtotime($start)) . ' – ' . date('j M Y', strtotime($end));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rateLabel(array $row): string
    {
        return ($row['listing_type'] ?? 'rental') === 'donation'
            ? 'Free — donation'
            : rate_label($row['daily_rate'] ?? null, $row['monthly_rate'] ?? null);
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $draft
     * @param array<string, string> $errors
     * @param array<string, string> $submitted values to show back after a failure
     */
    private function renderWizard(int $step, array $draft, array $errors, array $submitted = []): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        // Only the text fields come back from a failed post; photos and the
        // step counter stay under the draft's control.
        $textFields = ['name', 'category', 'description', 'declared_value', 'value_proof_type', 'listing_type', 'daily_rate', 'monthly_rate', 'community'];

        $merged = array_merge($draft, array_intersect_key($submitted, array_flip($textFields)));

        $this->render('items/create', [
            'step'       => $step,
            'categories' => $this->categories->allActive(),
            'draft'      => $merged,
            'photos'     => array_map(photo_url(...), $draft['photos']),
            'errors'     => $errors,
            'summary'    => $this->draftSummary($merged),
            'proofTypes' => ItemService::PROOF_TYPES,
            'temporaryCommunity' => (new UserDivision($this->pdo))->activeTemporary($this->userId())['division_name'] ?? null,
        ]);
    }

    /**
     * The last wizard step recaps what is about to be submitted.
     *
     * @param array<string, mixed> $draft
     */
    private function draftSummary(array $draft): string
    {
        $parts = [];

        foreach ($this->categories->allActive() as $category) {
            if ((string) $category['id'] === (string) $draft['category']) {
                $parts[] = (string) $category['name'];
            }
        }

        $parts[] = $draft['listing_type'] === 'donation' ? 'Donation' : 'Rental';
        $parts[] = 'declared ' . number_format((int) $draft['declared_value']) . ' pts';

        if ($draft['listing_type'] !== 'donation') {
            $rates = [];

            if ((int) $draft['daily_rate'] > 0) {
                $rates[] = $draft['daily_rate'] . ' pts/day';
            }

            if ((int) $draft['monthly_rate'] > 0) {
                $rates[] = $draft['monthly_rate'] . ' pts/month';
            }

            $parts[] = $rates === [] ? 'no rate set yet' : implode(' or ', $rates);
        }

        $parts[] = plural(count($draft['photos']), 'photo');

        return implode('  ·  ', $parts);
    }

    /**
     * @param array<string, mixed>  $row
     * @param array<string, string> $errors
     * @param array<string, string> $input
     */
    private function renderEdit(array $row, array $errors, array $input): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $photos = [];

        foreach (PhotoStore::paths($row['photos']) as $path) {
            $photos[] = ['path' => $path, 'url' => photo_url($path)];
        }

        [$badge, $glyph, $label] = $this->statusBadge((string) $row['status'], null);

        $this->render('items/edit', [
            'item' => [
                'id'           => (int) $row['id'],
                'status'       => (string) $row['status'],
                'badge'        => $badge,
                'badge_glyph'  => $glyph,
                'badge_label'  => $label,
                'editable'     => $row['status'] !== 'borrowed' && $row['status'] !== 'archived',
                'can_pause'    => $row['status'] === 'active',
                'can_resume'   => $row['status'] === 'paused',
            ],
            'categories' => $this->categories->allActive(),
            'photos'     => $photos,
            'draft'      => $input,
            'errors'     => $errors,
            'proofTypes' => ItemService::PROOF_TYPES,
            'proofOnFile' => $row['value_proof_path'] !== null,
            'blocks'      => $row['listing_type'] === 'rental' && $row['status'] !== 'archived'
                ? array_map(fn (array $block): array => [
                    'id'    => (int) $block['id'],
                    'start' => (string) $block['start_date'],
                    'end'   => (string) $block['end_date'],
                    'note'  => (string) ($block['note'] ?? ''),
                    'label' => $this->rangeLabel((string) $block['start_date'], (string) $block['end_date']),
                ], (new ItemAvailabilityBlock($this->pdo))->upcomingForItem((int) $row['id']))
                : null,
        ]);
    }

    private function renderException(RuntimeException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->notice(403, 'Not your listing', 'You can only edit items you listed yourself.');

            return;
        }

        $this->notice(404, 'Listing not found', 'This listing does not exist, or it has been removed.');
    }

    // ── Field rules, shared by the wizard steps and the edit form ───────────

    private function detailRules(Validator $validator): void
    {
        $validator
            ->required('name', 'Item name')
            ->maxLength('name', 'Item name', ItemService::NAME_MAX)
            ->words('name', 'Item name')
            ->required('category', 'Category')
            ->maxLength('description', 'Description', ItemService::DESCRIPTION_MAX)
            ->words('description', 'Description');
    }

    private function valueRules(Validator $validator): void
    {
        $validator
            ->required('declared_value', 'Declared value')
            ->integer('declared_value', 'Declared value', 1, ItemService::MAX_DECLARED_VALUE)
            ->inList('value_proof_type', 'Kind of proof', array_merge([''], array_keys(ItemService::PROOF_TYPES)));
    }

    private function typeRules(Validator $validator): void
    {
        $validator->inList('listing_type', 'Listing type', ItemService::LISTING_TYPES);
    }

    private function rateRules(Validator $validator): void
    {
        $validator
            ->integer('daily_rate', 'Daily rate', 1, ItemService::MAX_RATE)
            ->integer('monthly_rate', 'Monthly rate', 1, ItemService::MAX_RATE)
            ->inList('community', 'Community', ['', 'home', 'temporary']);
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function draft(): array
    {
        $blank = [
            'name'             => '',
            'category'         => '',
            'description'      => '',
            'photos'           => [],
            'declared_value'   => '',
            'value_proof_type' => null,
            'value_proof_path' => null,
            'listing_type'     => 'rental',
            'daily_rate'       => '',
            'monthly_rate'     => '',
            'community'        => 'home',
            'step'             => 1,
        ];

        $draft = $_SESSION[self::DRAFT_KEY] ?? [];

        return is_array($draft) ? array_merge($blank, $draft) : $blank;
    }

    /**
     * Turn a stored row back into the flat shape the edit form posts.
     *
     * @param  array<string, mixed> $row
     * @return array<string, string>
     */
    private function rowAsInput(array $row): array
    {
        return [
            'name'           => (string) $row['title'],
            'category'       => (string) $row['category_id'],
            'description'    => (string) ($row['description'] ?? ''),
            'declared_value' => (string) $row['declared_value'],
            'value_proof_type' => (string) ($row['value_proof_type'] ?? ''),
            'listing_type'   => (string) $row['listing_type'],
            'daily_rate'     => (string) ($row['daily_rate'] ?? ''),
            'monthly_rate'   => (string) ($row['monthly_rate'] ?? ''),
        ];
    }

    private function clampStep(int $step): int
    {
        return max(1, min(self::WIZARD_STEPS, $step));
    }

    private function listingTypeFilter(string $slug): ?string
    {
        return match ($slug) {
            'rentals'   => 'rental',
            'donations' => 'donation',
            default     => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $categories
     */
    private function categoryIdFromSlug(string $slug, array $categories): ?int
    {
        if ($slug === '') {
            return null;
        }

        foreach ($categories as $category) {
            if ($this->slug((string) $category['name']) === $slug) {
                return (int) $category['id'];
            }
        }

        return null;
    }

    private function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
    }
}
