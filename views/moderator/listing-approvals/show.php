<?php

declare(strict_types=1);

/**
 * Listing approval detail — one listing and its value proof, and the
 * moderator's decision on it. Checklist, notes and the three decisions are one
 * POST form (§7.3, §11).
 *
 * @var array $listing   title, status, status_label, meta, description
 * @var array $facts     label/value pairs describing the listing
 * @var array $photos    value proof and condition photos: label
 * @var array $checklist review items: id, label, checked
 */

// Sample view data — replaced by the controller once ModerationController lands.
$sampleChecklist = [
    ['id' => 'value-matches', 'label' => 'Declared value matches receipt'],
    ['id' => 'photos-match',  'label' => 'Photos show item in described condition'],
    ['id' => 'rate-in-range', 'label' => 'Lend rate falls within category guidance'],
];

$sampleListings = [
    'pressure-washer' => [
        'listing' => [
            'title'        => 'Pressure Washer',
            'status'       => 'warning',
            'status_label' => 'Pending approval',
            'meta'         => 'Listed by T.H.K. Madushan  ·  submitted 15 Jul 2026  ·  Wellawatte',
            'description'  => 'Karcher K4 electric pressure washer, includes hose and two nozzle '
                            . 'attachments. Stored indoors, serviced this year.',
        ],
        'facts' => [
            ['label' => 'Category',            'value' => 'Tools & Equipment'],
            ['label' => 'Declared value',      'value' => '320 pts (receipt Rs 32,000)'],
            ['label' => 'Suggested lend rate', 'value' => '20 pts / day'],
            ['label' => 'Condition',           'value' => 'Good · lightly used'],
        ],
        'photos'  => ['Receipt', 'Photo', 'Photo', 'Photo'],
        'checked' => ['value-matches', 'photos-match'],
    ],
    'cordless-drill' => [
        'listing' => [
            'title'        => 'Cordless Drill — Bosch 18V',
            'status'       => 'warning',
            'status_label' => 'Pending approval',
            'meta'         => 'Listed by R. Fernando  ·  submitted 1 day ago',
            'description'  => 'Bosch 18V cordless drill with two batteries, charger and carry case.',
        ],
        'facts' => [
            ['label' => 'Category',            'value' => 'Tools & Equipment'],
            ['label' => 'Declared value',      'value' => '120 pts (receipt Rs 12,000)'],
            ['label' => 'Suggested lend rate', 'value' => '8 pts / day'],
            ['label' => 'Condition',           'value' => 'Good · lightly used'],
        ],
        'photos'  => ['Receipt', 'Photo', 'Photo', 'Photo'],
        'checked' => ['value-matches'],
    ],
    'folding-table' => [
        'listing' => [
            'title'        => 'Folding Table (6ft)',
            'status'       => 'warning',
            'status_label' => 'Pending approval',
            'meta'         => 'Listed by N. Silva  ·  submitted 2 days ago',
            'description'  => '6ft folding table with foldable legs, seats up to six.',
        ],
        'facts' => [
            ['label' => 'Category',            'value' => 'Furniture'],
            ['label' => 'Declared value',      'value' => '85 pts (photo proof)'],
            ['label' => 'Suggested lend rate', 'value' => '5 pts / day'],
            ['label' => 'Condition',           'value' => 'Good'],
        ],
        'photos'  => ['Photo', 'Photo', 'Photo', 'Photo'],
        'checked' => ['photos-match'],
    ],
    'pressure-washer-kb' => [
        'listing' => [
            'title'        => 'Pressure Washer',
            'status'       => 'info',
            'status_label' => 'Inspection requested',
            'meta'         => 'Listed by K. Bandara  ·  submitted 2 days ago  ·  inspection requested',
            'description'  => 'Petrol pressure washer. Condition to be confirmed via in-person '
                            . 'inspection before approval.',
        ],
        'facts' => [
            ['label' => 'Category',            'value' => 'Tools & Equipment'],
            ['label' => 'Declared value',      'value' => '220 pts (inspection requested)'],
            ['label' => 'Suggested lend rate', 'value' => '18 pts / day'],
            ['label' => 'Condition',           'value' => 'Pending inspection'],
        ],
        'photos'  => ['Photo', 'Photo', 'Photo', 'Photo'],
        'checked' => [],
    ],
    'sewing-machine' => [
        'listing' => [
            'title'        => 'Sewing Machine — Singer',
            'status'       => 'warning',
            'status_label' => 'Pending approval',
            'meta'         => 'Listed by P. Mendis  ·  submitted 3 days ago',
            'description'  => 'Singer sewing machine, works well, includes accessories case.',
        ],
        'facts' => [
            ['label' => 'Category',            'value' => 'Household & Appliances'],
            ['label' => 'Declared value',      'value' => '150 pts (receipt Rs 15,000)'],
            ['label' => 'Suggested lend rate', 'value' => '10 pts / day'],
            ['label' => 'Condition',           'value' => 'Good · works well'],
        ],
        'photos'  => ['Receipt', 'Photo', 'Photo', 'Photo'],
        'checked' => ['value-matches', 'photos-match', 'rate-in-range'],
    ],
    'petrol-generator' => [
        'listing' => [
            'title'        => 'Petrol Generator',
            'status'       => 'error',
            'status_label' => 'Rejected',
            'meta'         => 'Listed by S. Perera  ·  rejected 12 Jul 2026',
            'description'  => '2.5 kVA petrol generator. Fuel-powered items are not lendable under the '
                            . 'division’s safety policy, so this listing was rejected.',
        ],
        'facts' => [
            ['label' => 'Category',            'value' => 'Tools & Equipment'],
            ['label' => 'Declared value',      'value' => '450 pts (receipt Rs 45,000)'],
            ['label' => 'Suggested lend rate', 'value' => '30 pts / day'],
            ['label' => 'Condition',           'value' => 'Good'],
        ],
        'photos'  => ['Receipt', 'Photo', 'Photo'],
        'checked' => ['value-matches'],
    ],
];

$listingId = (string) ($_GET['id'] ?? '');
$sample    = $sampleListings[$listingId] ?? reset($sampleListings);

$listing   ??= $sample['listing'];
$facts     ??= $sample['facts'];
$photos    ??= $sample['photos'];
$checklist ??= array_map(
    static fn (array $check): array => $check + ['checked' => in_array($check['id'], $sample['checked'], true)],
    $sampleChecklist
);

$pageTitle = $listing['title'];
$navActive = 'listing-approvals';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="<?= base_url() ?>/moderator/listing-approvals">Listing approvals</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($listing['title']) ?></span>
</nav>

<header class="record-head">
    <span class="thumb thumb--sm">Photo</span>
    <h1 class="record-head__title"><?= e($listing['title']) ?></h1>
    <span class="badge badge--<?= e($listing['status']) ?>"><?= e($listing['status_label']) ?></span>
</header>

<p class="record-meta"><?= e($listing['meta']) ?></p>

<form class="stack stack--loose" method="post" action="<?= base_url() ?>/moderator/listing-approvals/<?= rawurlencode($listingId) ?>/decision">
    <?= csrf_field() ?>

    <div class="two-col two-col--wide-main">
        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Listing details</h2>
                <div class="facts">
                    <?php foreach ($facts as $fact): ?>
                        <span class="fact">
                            <span class="fact__label"><?= e($fact['label']) ?></span>
                            <span class="fact__value"><?= e($fact['value']) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="panel__prose"><?= e($listing['description']) ?></p>
            </section>

            <section class="panel">
                <h2 class="panel__title">Value proof &amp; condition photos</h2>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <span class="thumb thumb--photo"><?= e($photo) ?></span>
                    <?php endforeach; ?>
                </div>
            </section>

        </div>

        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Approval checklist</h2>
                <ul class="checklist">
                    <?php foreach ($checklist as $check): ?>
                        <li>
                            <label class="checklist__item" for="check-<?= e($check['id']) ?>">
                                <input
                                    class="checklist__input"
                                    type="checkbox"
                                    id="check-<?= e($check['id']) ?>"
                                    name="checks[]"
                                    value="<?= e($check['id']) ?>"
                                    <?= $check['checked'] ? 'checked' : '' ?>
                                >
                                <span class="checklist__label"><?= e($check['label']) ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <div class="field">
                <label class="field__label" for="approval-notes">Notes (optional)</label>
                <textarea
                    class="textarea"
                    id="approval-notes"
                    name="notes"
                    placeholder="Add any notes for the record"
                ></textarea>
            </div>

        </div>
    </div>

    <div class="actions">
        <button class="btn btn--ghost" type="submit" name="decision" value="reject">Reject listing</button>
        <button class="btn btn--ghost" type="submit" name="decision" value="request-changes">Request changes</button>
        <button class="btn btn--primary" type="submit" name="decision" value="approve">Approve listing</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
