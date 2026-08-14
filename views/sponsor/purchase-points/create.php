<?php

declare(strict_types=1);

/**
 * Purchase a points package. Figma: "Purchase Points Package" (385:81).
 *
 * No in-app payment — submitting sends a request to the Sponsor Liaison, who
 * records the cash-to-points conversion offline with a receipt number.
 *
 * @var array $packages    radio options: value, label, price, note, selected
 * @var array $split       sponsor_pct, aid_pct, sponsor_pts, aid_pts — the selected allocation
 * @var array $allocations radio options: value, label, selected
 * @var string $liaisonNote
 */

// Sample view data — replaced by the controller once SponsorController lands.
$packages ??= [
    ['value' => 'starter',   'label' => 'Starter',   'price' => 'LKR 25,000',  'note' => '= 25,000 points'],
    ['value' => 'community', 'label' => 'Community', 'price' => 'LKR 50,000',  'note' => '= 50,000 points', 'selected' => true],
    ['value' => 'impact',    'label' => 'Impact',    'price' => 'LKR 100,000', 'note' => '= 100,000 points'],
    ['value' => 'custom',    'label' => 'Custom',    'price' => 'Any amount',  'note' => '1 : 1, your choice'],
];

$split ??= [
    'sponsor_pct' => 70,
    'aid_pct'     => 30,
    'sponsor_pts' => '35,000 pts',
    'aid_pts'     => '15,000 pts',
];

$allocations ??= [
    ['value' => '100-0', 'label' => '100 / 0'],
    ['value' => '70-30', 'label' => '70 / 30', 'selected' => true],
    ['value' => '50-50', 'label' => '50 / 50'],
    ['value' => '0-100', 'label' => '0 / 100'],
];

$liaisonNote ??= 'No in-app payment. Submitting sends a purchase request to your Sponsor Liaison '
                . '(A. Akalvily), who coordinates payment offline under your written agreement and '
                . 'records the contribution with a receipt number.';

$pageTitle = 'Purchase points';
$navActive = 'purchase-points';

include __DIR__ . '/../../../partials/header-sponsor.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title">Purchase a points package</h1>
    <p class="page-intro__meta">
        Every rupee becomes exactly one community point — no deductions. Choose a package or a custom
        amount, then decide your allocation.
    </p>
</header>

<form class="stack" method="post" action="/sponsor/purchase-points">
    <?= csrf_field() ?>

    <div class="stat-grid">
        <?php foreach ($packages as $package): ?>
            <label class="choice">
                <input
                    class="visually-hidden"
                    type="radio"
                    name="package"
                    value="<?= e($package['value']) ?>"
                    <?= !empty($package['selected']) ? 'checked' : '' ?>
                >
                <span class="choice__body">
                    <span class="stat-card__label"><?= e($package['label']) ?></span>
                    <strong class="stat-card__value"><?= e($package['price']) ?></strong>
                    <span class="stat-card__note"><?= e($package['note']) ?></span>
                </span>
            </label>
        <?php endforeach; ?>
    </div>

    <section class="panel">
        <h2 class="panel__title">Allocation — entirely your choice</h2>

        <div class="split-bar">
            <span class="split-bar__fill--sponsor" style="flex-grow: <?= (int) $split['sponsor_pct'] ?>"></span>
            <span class="split-bar__fill--aid" style="flex-grow: <?= (int) $split['aid_pct'] ?>"></span>
        </div>

        <div class="split-legend">
            <span class="split-legend__row">
                <span class="split-legend__dot split-legend__dot--sponsor"></span>
                Sponsor Pool · <?= (int) $split['sponsor_pct'] ?>% = <?= e($split['sponsor_pts']) ?> —
                welcome bonuses, stipends, community rewards
            </span>
            <span class="split-legend__row">
                <span class="split-legend__dot split-legend__dot--aid"></span>
                Aid Pool · <?= (int) $split['aid_pct'] ?>% = <?= e($split['aid_pts']) ?> —
                reserved exclusively for approved aid grants
            </span>
        </div>

        <div class="filter-pills">
            <?php foreach ($allocations as $allocation): ?>
                <label class="pill">
                    <input
                        class="visually-hidden"
                        type="radio"
                        name="allocation"
                        value="<?= e($allocation['value']) ?>"
                        <?= !empty($allocation['selected']) ? 'checked' : '' ?>
                    >
                    <?= e($allocation['label']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="notice notice--info notice--full"><?= e($liaisonNote) ?></div>

    <div class="actions">
        <a class="btn btn--ghost" href="/sponsor/dashboard">Cancel</a>
        <button class="btn btn--primary" type="submit">Send purchase request</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
