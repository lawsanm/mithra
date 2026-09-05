<?php

declare(strict_types=1);

/**
 * Item listings — the member's own CRUD over what they lend and give away.
 *
 * Each action reads validated input, calls one service method (or a plain model
 * read), then renders a view or redirects. No SQL, no business rules, no point
 * arithmetic (Rules/CONVENTIONS.md §6).
 *
 * Until Identity ships a login the acting member comes from the session, with
 * config's demo_member_id as the fallback. Authentication and role enforcement
 * still need to be implemented before this becomes a deployed application.
 */
final class ItemController
{
    /** Session key holding the create wizard's half-finished listing. */
    private const DRAFT_KEY = 'item_draft';

    private const WIZARD_STEPS = 4;

    private PDO $pdo;
    private ItemService $service;
    private Item $items;
    private ItemCategory $categories;

    public function __construct(PDO $pdo)
    {
        $this->pdo        = $pdo;
        $this->items      = new Item($pdo);
        $this->categories = new ItemCategory($pdo);
        $this->service    = new ItemService(
            $this->items,
            $this->categories,
            new Booking($pdo),
            new PhotoStore(dirname(__DIR__, 2) . '/storage/uploads')
        );
    }

    // ── Read ────────────────────────────────────────────────────────────────

    /**
     * GET /items — My Items.
     */
    public function index(): void
    {
        $me   = $this->memberId();
        $type = $this->listingTypeFilter((string) ($_GET['type'] ?? ''));

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
     * GET /items/browse — everyone else's listings in the member's division.
     */
    public function browse(): void
    {
        $me       = $this->memberId();
        $division = $this->divisionId();
        $query    = trim((string) ($_GET['q'] ?? ''));
        $page     = max(1, (int) ($_GET['page'] ?? 1));

        $allCategories = $this->categories->allActive();
        $categoryId    = $this->categoryIdFromSlug((string) ($_GET['category'] ?? ''), $allCategories);

        $rows  = $this->items->browse($division, $me, $categoryId, $query, $page);
        $total = $this->items->countBrowse($division, $me, $categoryId, $query);

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
            : $total . ' item' . ($total === 1 ? '' : 's') . ' available in ' . $this->divisionName();

        $this->render('items/browse', [
            'query'       => $query,
            'categories'  => $categories,
            'results'     => $results,
            'resultCount' => $resultCount,
            'page'        => $page,
            'hasNextPage' => $page * Item::PER_PAGE < $total,
        ]);
    }

    /**
     * GET /items/{id} — Item Detail.
     */
    public function show(int $id): void
    {
        $row = $this->items->findForDetail($id);

        if ($row === null) {
            $this->renderNotice(404, 'Listing not found', 'This listing does not exist, or it has been removed.');

            return;
        }

        $me      = $this->memberId();
        $isOwner = (int) $row['owner_id'] === $me;

        // An unapproved or paused listing is only visible to the member who owns it.
        if (!$isOwner && !in_array($row['status'], ['active', 'borrowed'], true)) {
            $this->renderNotice(404, 'Listing not available', 'This listing is not on the shelf right now.');

            return;
        }

        [$badge, $glyph, $label] = $this->statusBadge((string) $row['status'], $row['due_back'] ?? null);

        $this->render('items/show', [
            'item' => [
                'id'             => (int) $row['id'],
                'title'          => (string) $row['title'],
                'category'       => (string) $row['category_name'],
                'category_slug'  => $this->slug((string) $row['category_name']),
                'rate'           => $this->rateLabel($row),
                'declared_value' => 'Declared value: ' . number_format((int) $row['declared_value']) . ' pts',
                'description'    => (string) ($row['description'] ?? ''),
                'listing_type'   => (string) $row['listing_type'],
                'photos'         => $this->photoUrls($this->service->decodePhotos($row)),
                'status'         => $badge,
                'status_glyph'   => $glyph,
                'status_label'   => $label,
                'can_borrow'     => !$isOwner && $row['status'] === 'active' && $row['listing_type'] === 'rental',
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
            'quote'   => ['from' => '', 'to' => '', 'days_label' => 'Select dates  ·  Total', 'total' => '—'],
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────────────

    /**
     * GET /items/create — the four-step wizard.
     */
    public function createForm(): void
    {
        $draft = $this->draft();
        $step  = min($this->clampStep((int) ($_GET['step'] ?? 1)), (int) $draft['step']);

        $this->renderWizard($step, $draft, []);
    }

    /**
     * POST /items — one wizard step, and on the last one the insert itself.
     */
    public function store(): void
    {
        $draft = $this->draft();
        $step  = min($this->clampStep((int) ($_POST['step'] ?? 1)), (int) $draft['step']);

        $validator = new Validator($_POST);

        try {
            switch ($step) {
                case 1:
                    $validator
                        ->required('name', 'Item name')
                        ->maxLength('name', 'Item name', 150)
                        ->required('category', 'Category')
                        ->maxLength('description', 'Description', 2000);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['name']        = $validator->value('name');
                    $draft['category']    = $validator->value('category');
                    $draft['description'] = $validator->value('description');
                    $draft['photos']      = array_merge(
                        $draft['photos'],
                        $this->service->storePhotos($this->uploadedFiles('photos'), count($draft['photos']))
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
                    $validator
                        ->required('declared_value', 'Declared value')
                        ->integer('declared_value', 'Declared value', 1, 1000000);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['declared_value'] = $validator->value('declared_value');

                    $proof = $this->service->storePhotos($this->uploadedFiles('value_proof'), 0);

                    if ($proof !== []) {
                        if ($draft['value_proof_path'] !== null) {
                            $this->service->discardPhotos([(string) $draft['value_proof_path']]);
                        }

                        $draft['value_proof_path'] = $proof[0];
                        $draft['value_proof_type'] = 'photo';
                    }

                    break;

                case 3:
                    $validator->inList('listing_type', 'Listing type', ['rental', 'donation']);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['listing_type'] = $validator->value('listing_type');

                    break;

                default:
                    $validator
                        ->integer('daily_rate', 'Daily rate', 1, 1000000)
                        ->integer('monthly_rate', 'Monthly rate', 1, 1000000);

                    if (!$validator->passes()) {
                        $this->renderWizard($step, $draft, $validator->errors(), $validator->values());

                        return;
                    }

                    $draft['daily_rate']   = $validator->value('daily_rate');
                    $draft['monthly_rate'] = $validator->value('monthly_rate');

                    $this->service->create($this->memberId(), $this->divisionId(), $draft, $draft['photos']);

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
            $row = $this->service->ownedOrFail($id, $this->memberId());
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
        $me = $this->memberId();

        try {
            $row = $this->service->ownedOrFail($id, $me);
        } catch (RecordNotFoundException | AccessDeniedException $exception) {
            $this->renderException($exception);

            return;
        }

        $validator = new Validator($_POST);
        $validator
            ->required('name', 'Item name')
            ->maxLength('name', 'Item name', 150)
            ->required('category', 'Category')
            ->maxLength('description', 'Description', 2000)
            ->required('declared_value', 'Declared value')
            ->integer('declared_value', 'Declared value', 1, 1000000)
            ->inList('listing_type', 'Listing type', ['rental', 'donation'])
            ->integer('daily_rate', 'Daily rate', 1, 1000000)
            ->integer('monthly_rate', 'Monthly rate', 1, 1000000);

        if (!$validator->passes()) {
            $this->renderEdit($row, $validator->errors(), $validator->values());

            return;
        }

        // Only paths already on the row can be kept — the checkbox values are
        // client input and are never trusted as file names (§8).
        $kept = array_values(array_intersect(
            $this->service->decodePhotos($row),
            array_map('strval', (array) ($_POST['keep_photos'] ?? []))
        ));

        try {
            $photos = array_merge(
                $kept,
                $this->service->storePhotos($this->uploadedFiles('photos'), count($kept))
            );

            $requeued = $this->service->update($id, $me, $validator->values(), $photos);
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
            $change($this->memberId());
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
            $meta[] = $row['request_count'] . ' request' . ((int) $row['request_count'] === 1 ? '' : 's');
        } else {
            $meta[] = 'listed ' . date('j M Y', strtotime((string) $row['created_at']));
        }

        $photos = $this->photoUrls(array_filter([$row['photo'] ?? null]));

        return [
            'id'           => (int) $row['id'],
            'title'        => (string) $row['title'],
            'meta'         => implode('  ·  ', $meta),
            'photo'        => $photos[0] ?? null,
            'status'       => $badge,
            'status_glyph' => $glyph,
            'status_label' => $label,
            'href'         => base_url() . '/items/' . $row['id'],
            'edit_href'    => base_url() . '/items/' . $row['id'] . '/edit',
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
        $photos = $this->photoUrls(array_filter([$row['photo'] ?? null]));

        return [
            'title'        => (string) $row['title'],
            'rate'         => $this->rateLabel($row),
            'photo'        => $photos[0] ?? null,
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
     * @param array<string, mixed> $row
     */
    private function rateLabel(array $row): string
    {
        if (($row['listing_type'] ?? 'rental') === 'donation') {
            return 'Free — donation';
        }

        $parts = [];

        if (!empty($row['daily_rate'])) {
            $parts[] = $row['daily_rate'] . ' pts / day';
        }

        if (!empty($row['monthly_rate'])) {
            $parts[] = $row['monthly_rate'] . ' pts / month';
        }

        return $parts === [] ? 'Rate not set' : implode('  ·  ', $parts);
    }

    /**
     * Stored paths become proxy URLs — storage is outside the web root, so a
     * file is only ever reachable through public/photo.php (§7.5).
     *
     * @param  iterable<string> $paths
     * @return list<string>
     */
    private function photoUrls(iterable $paths): array
    {
        $urls = [];

        foreach ($paths as $path) {
            $urls[] = base_url() . '/photo.php?p=' . rawurlencode((string) $path);
        }

        return $urls;
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): void
    {
        $member  = (new User($this->pdo))->findWithDivision($this->memberId()) ?? [];
        $wallets = new Wallet($this->pdo);

        $data['currentMember'] = [
            'initials'       => User::initials((string) ($member['full_name'] ?? '')),
            'points_balance' => number_format($wallets->balance($this->memberId())) . ' pts',
        ];
        $data['flash'] = $this->takeFlash();

        extract($data, EXTR_SKIP);

        include dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

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
        $textFields = ['name', 'category', 'description', 'declared_value', 'listing_type', 'daily_rate', 'monthly_rate'];

        $merged = array_merge($draft, array_intersect_key($submitted, array_flip($textFields)));

        $this->render('items/create', [
            'step'       => $step,
            'categories' => $this->categories->allActive(),
            'draft'      => $merged,
            'photos'     => $this->photoUrls($draft['photos']),
            'errors'     => $errors,
            'summary'    => $this->draftSummary($merged),
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

        $parts[] = count($draft['photos']) . ' photo' . (count($draft['photos']) === 1 ? '' : 's');

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

        $paths  = $this->service->decodePhotos($row);
        $urls   = $this->photoUrls($paths);
        $photos = [];

        foreach ($paths as $index => $path) {
            $photos[] = ['path' => $path, 'url' => $urls[$index]];
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
        ]);
    }

    private function renderException(RuntimeException $exception): void
    {
        if ($exception instanceof AccessDeniedException) {
            $this->renderNotice(403, 'Not your listing', 'You can only edit items you listed yourself.');

            return;
        }

        $this->renderNotice(404, 'Listing not found', 'This listing does not exist, or it has been removed.');
    }

    private function renderNotice(int $status, string $title, string $body): void
    {
        http_response_code($status);

        $this->render('errors/notice', ['noticeTitle' => $title, 'noticeBody' => $body]);
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    /**
     * $_FILES arrives transposed for multi-file inputs; hand services a plain
     * list of one array per file so nothing downstream knows about the shape.
     *
     * @return list<array{name: string, tmp_name: string, error: int, size: int}>
     */
    private function uploadedFiles(string $field): array
    {
        $raw = $_FILES[$field] ?? null;

        if (!is_array($raw) || !isset($raw['name'])) {
            return [];
        }

        if (!is_array($raw['name'])) {
            return [[
                'name'     => (string) $raw['name'],
                'tmp_name' => (string) ($raw['tmp_name'] ?? ''),
                'error'    => (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($raw['size'] ?? 0),
            ]];
        }

        $files = [];

        foreach (array_keys($raw['name']) as $index) {
            $files[] = [
                'name'     => (string) $raw['name'][$index],
                'tmp_name' => (string) ($raw['tmp_name'][$index] ?? ''),
                'error'    => (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($raw['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

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

    private function memberId(): int
    {
        return (int) ($_SESSION['user_id'] ?? Config::get('demo_member_id', 4));
    }

    private function divisionId(): int
    {
        $member = (new User($this->pdo))->findWithDivision($this->memberId());

        return (int) ($member['division_id'] ?? 0);
    }

    private function divisionName(): string
    {
        $member = (new User($this->pdo))->findWithDivision($this->memberId());

        return (string) ($member['division_name'] ?? 'your community');
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? ['type' => (string) $flash['type'], 'message' => (string) $flash['message']] : null;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . base_url() . $path, true, 303);
    }
}
