<?php

declare(strict_types=1);

/**
 * Purchase a points package. Figma: "Purchase Points Package" (385:81).
 *
 * No in-app payment — submitting sends a request to the Sponsor Liaison, who
 * records the cash-to-points conversion offline with a receipt number.
 *
 * @var array $packages    radio options: value, title, note, selected
 * @var array $allocations radio options: value, title, note, selected
 * @var string $liaisonNote
 */

// Sample view data — replaced by the controller once SponsorController lands.
$packages ??= [
    ['value' => 'starter',   'title' => 'Starter — LKR 25,000',    'note' => '= 25,000 points'],
    ['value' => 'community', 'title' => 'Community — LKR 50,000',  'note' => '= 50,000 points', 'selected' => true],
    ['value' => 'impact',    'title' => 'Impact — LKR 100,000',    'note' => '= 100,000 points'],
    ['value' => 'custom',    'title' => 'Custom amount',           'note' => '1 : 1, your choice'],
];

$allocations ??= [
    ['value' => '100-0', 'title' => '100% Sponsor / 0% Aid', 'note' => 'All funds go to welcome bonuses, stipends and community rewards'],
    ['value' => '70-30', 'title' => '70% Sponsor / 30% Aid', 'note' => 'Mostly sponsor-directed rewards, 30% reserved for aid grants', 'selected' => true],
    ['value' => '50-50', 'title' => '50% Sponsor / 50% Aid', 'note' => 'An even split between rewards and aid'],
    ['value' => '0-100', 'title' => '0% Sponsor / 100% Aid', 'note' => 'Entirely reserved for approved aid grants'],
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

    <fieldset class="stack">
        <legend class="field__label">Package</legend>
        <div class="panel-row">
            <?php foreach ($packages as $package): ?>
                <label class="choice">
                    <input
                        class="choice__input"
                        type="radio"
                        name="package"
                        value="<?= e($package['value']) ?>"
                        <?= !empty($package['selected']) ? 'checked' : '' ?>
                    >
                    <span class="choice__body">
                        <span class="choice__title"><?= e($package['title']) ?></span>
                        <span class="choice__note"><?= e($package['note']) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="field">
            <label class="field__label" for="custom_amount">Custom amount (LKR)</label>
            <input class="input input--narrow" type="number" id="custom_amount" name="custom_amount" min="1" step="1">
            <span class="field__hint">Only used if you select Custom above.</span>
        </div>
    </fieldset>

    <fieldset class="stack">
        <legend class="field__label">Allocation — entirely your choice</legend>
        <div class="panel-row">
            <?php foreach ($allocations as $allocation): ?>
                <label class="choice">
                    <input
                        class="choice__input"
                        type="radio"
                        name="allocation"
                        value="<?= e($allocation['value']) ?>"
                        <?= !empty($allocation['selected']) ? 'checked' : '' ?>
                    >
                    <span class="choice__body">
                        <span class="choice__title"><?= e($allocation['title']) ?></span>
                        <span class="choice__note"><?= e($allocation['note']) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="notice notice--info notice--full"><?= e($liaisonNote) ?></div>

    <div class="actions">
        <a class="btn btn--ghost" href="/sponsor/dashboard">Cancel</a>
        <button class="btn btn--primary" type="submit">Send purchase request</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
