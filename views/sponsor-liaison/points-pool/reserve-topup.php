<?php

declare(strict_types=1);

/**
 * Top up the Reserve Pool from the Sponsor Pool (Plan §7.7). No Figma frame
 * exists for this screen; it follows "Record Contribution" (382:103).
 *
 * The balances are live; the form is a preview until the pool-to-pool ledger
 * transfer (module 4.3) is built.
 *
 * @var array $stats four figures for the stat row
 */

$pageTitle = 'Top up Reserve';
$navActive = 'points-pool';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/points-pool">Points pool</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Top up Reserve</span>
</nav>

<h1 class="detail__title">Top up the Reserve Pool</h1>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = $stat['class']; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<div class="form-card" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

    <div class="field-row">
        <div class="field">
            <label class="field__label" for="from-pool">From</label>
            <input class="input input--half" type="text" id="from-pool" value="Sponsor Pool" disabled>
        </div>
        <div class="field">
            <label class="field__label" for="to-pool">To</label>
            <input class="input input--half" type="text" id="to-pool" value="Reserve Pool" disabled>
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="amount">Points to move</label>
        <input class="input input--half" type="number" id="amount" name="amount" placeholder="2,000" disabled>
    </div>

    <div class="field">
        <label class="field__label" for="note">Reason</label>
        <textarea class="textarea" id="note" name="note" rows="3" placeholder="e.g. Admin reported the Reserve is low after three shortfall covers this month" disabled></textarea>
    </div>

    <p class="notice notice--info notice--full">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        No new points are created: this moves General points already in the Sponsor
        Pool into the Reserve, so the six-pool total stays the same. The move is
        written to the ledger as a Reserve top-up and shown on the Transparency
        Dashboard.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/points-pool">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Top up Reserve</button>
    </div>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
