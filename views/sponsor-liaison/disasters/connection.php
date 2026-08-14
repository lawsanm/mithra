<?php

declare(strict_types=1);

/**
 * Disaster — sponsor ↔ moderator connection. Figma
 * "Disaster — Sponsor Connection" (383:184).
 *
 * @var array $disaster    title, status_label, meta
 * @var array $sponsorSide  initial, name, offer
 * @var array $moderatorSide initials, name, note
 * @var string $connectedNote
 * @var array $log          rows: title, date
 * @var array $draft        cash_amount, receipt_number
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$disaster ??= [
    'title'        => 'Wellawatte flooding – relief coordination',
    'status_label' => 'Disaster Mode active',
    'meta'         => 'Activated 15 Jul, 09:20 by Mod. J. Kavipriya  ·  13 sponsors notified',
];

$sponsorSide ??= [
    'initial' => 'N',
    'name'    => 'Northwind Co',
    'offer'   => 'Offering: 40 dry ration packs + LKR 25,000 relief contribution',
];

$moderatorSide ??= [
    'initials' => 'JK',
    'name'     => 'Mod. J. Kavipriya  on the ground',
    'note'     => 'Confirms need: 60 households affected, low-lying lanes worst hit',
];

$connectedNote ??= 'Connected 15 Jul, 14:30  delivery arranged for 17 Jul';

$log ??= [
    ['title' => 'Sponsor notified and responded',        'date' => '15 Jul, 10:05'],
    ['title' => 'Moderator confirmed need on the ground', 'date' => '15 Jul, 13:40'],
    ['title' => 'Delivery of ration packs arranged',      'date' => '15 Jul, 14:30'],
];

$draft ??= [
    'cash_amount'     => '',
    'receipt_number'  => '',
];

$pageTitle = $disaster['title'];
$navActive = 'disasters';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/sponsor-liaison/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Wellawatte flooding · connection</span>
</nav>

<div style="display: flex; align-items: center; gap: var(--space-4);">
    <h1 class="detail__title"><?= e($disaster['title']) ?></h1>
    <span class="badge badge--error"><?= e($disaster['status_label']) ?></span>
</div>
<p class="page-intro__meta"><?= e($disaster['meta']) ?></p>

<div class="two-col">
    <div class="stack" style="display: flex; flex-direction: column; gap: var(--space-5);">
        <div class="form-card" style="width: 100%;">
            <h2 class="form-card__legend" style="font-size: var(--text-lede);">Sponsor ↔ Moderator connection</h2>

            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: var(--radius-md); background-color: var(--color-warm-100); color: var(--color-text-muted); font-weight: var(--weight-bold); font-size: var(--text-lede);"><?= e($sponsorSide['initial']) ?></span>
                <div>
                    <p class="list-row__title"><?= e($sponsorSide['name']) ?></p>
                    <p class="list-row__meta"><?= e($sponsorSide['offer']) ?></p>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <span class="avatar avatar--md"><?= e($moderatorSide['initials']) ?></span>
                <div>
                    <p class="list-row__title"><?= e($moderatorSide['name']) ?></p>
                    <p class="list-row__meta"><?= e($moderatorSide['note']) ?></p>
                </div>
            </div>

            <span class="badge badge--info"><?= e($connectedNote) ?></span>
        </div>

        <div class="form-card" style="width: 100%;">
            <h2 class="form-card__legend" style="font-size: var(--text-lede);">Coordination log</h2>
            <div class="timeline">
                <?php foreach ($log as $entry): ?>
                    <div class="timeline__item">
                        <p class="timeline__title"><?= e($entry['title']) ?></p>
                        <span class="timeline__date"><?= e($entry['date']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div>
        <form class="form-card" style="width: 100%;" method="post" action="/sponsor-liaison/disasters/connection/verify">
            <?= csrf_field() ?>
            <h2 class="form-card__legend" style="font-size: var(--text-lede);">Verify &amp; record relief contribution</h2>

            <div class="field">
                <label class="field__label" for="cash-amount">Cash portion (LKR)</label>
                <input class="input" type="number" id="cash-amount" name="cash_amount" value="<?= e($draft['cash_amount']) ?>" placeholder="25,000" min="0" step="1">
            </div>

            <div class="field">
                <label class="field__label" for="receipt-number">Receipt number</label>
                <input class="input" type="text" id="receipt-number" name="receipt_number" value="<?= e($draft['receipt_number']) ?>" placeholder="INV-0319">
            </div>

            <p class="page-intro__meta">Allocation: 100% Aid Pool (Disaster Mode default)</p>

            <div class="actions">
                <button class="btn btn--primary" type="submit">Verify &amp; record</button>
            </div>
        </form>
    </div>
</div>

<div class="notice notice--info notice--full">
    Disaster mode contributions follow the same rules: recorded with a receipt, logged in the append-only ledger, visible on the Transparency Dashboard.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
