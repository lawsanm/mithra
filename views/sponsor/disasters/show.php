<?php

declare(strict_types=1);

/**
 * Disaster — connect with the moderator on the ground. Figma:
 * "Disaster — Connect with Moderator" (387:137).
 *
 * @var array $disaster    title, meta, badge status/label
 * @var array $moderator   initials, name, quote, status_label
 * @var array $activeAlert modal content: event_name, division, affected
 * @var string $offerNote
 */

// Sample view data — replaced by the controller once SponsorController lands.
$disaster ??= [
    'title'        => 'Wellawatte flooding — How you can help',
    'status'       => 'error',
    'status_label' => 'Disaster Mode active',
    'meta'         => 'Declared 15 Jul  ·  60 households affected  ·  coordinated by your liaison A. Akalvily',
];

$moderator ??= [
    'initials'     => 'JK',
    'name'         => 'Mod. J. Kavipriya — Wellawatte GN Division',
    'quote'        => '"Low-lying lanes are worst hit. Most urgent: dry rations, drinking water, and tarpaulins for roof damage."',
    'status_label' => 'Verified need — confirmed 15 Jul, 13:40',
];

$activeAlert ??= [
    'event_name' => 'Wellawatte flooding',
    'division'   => 'Wellawatte GN Division',
    'affected'   => '60 households affected',
];

$offerNote ??= 'Disaster contributions default to 100% Aid Pool. Your liaison verifies and records '
             . 'everything with a receipt.';

$footerNote ??= 'Relief reaches members through the moderator — sponsors never handle member data. '
              . 'Your contribution is logged and appears on the Transparency Dashboard.';

$pageTitle = 'Disaster relief';
$navActive = 'dashboard';

include __DIR__ . '/../../../partials/header-sponsor.php';

?>

<header class="record-head">
    <h1 class="record-head__title"><?= e($disaster['title']) ?></h1>
    <span class="badge badge--<?= e($disaster['status']) ?>"><?= e($disaster['status_label']) ?></span>
</header>

<p class="record-meta"><?= e($disaster['meta']) ?></p>

<div class="panel-row">
    <button class="panel panel--half" type="button" data-modal-open="modal-sponsor-disaster-alert">
        <h2 class="panel__title">Moderator on the ground</h2>
        <div class="media">
            <span class="avatar avatar--md"><?= e($moderator['initials']) ?></span>
            <span class="media__body">
                <span class="media__title media__title--sm"><?= e($moderator['name']) ?></span>
                <span class="media__meta"><?= e($moderator['quote']) ?></span>
            </span>
        </div>
        <span class="badge badge--info"><?= e($moderator['status_label']) ?></span>
    </button>

    <section class="panel panel--half">
        <h2 class="panel__title">Make an offer</h2>
        <form class="stack" method="post" action="/sponsor/disasters/1/offer">
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="offer_amount">Amount (LKR)</label>
                <input class="input" type="number" id="offer_amount" name="amount" min="1" step="1" placeholder="25,000" required>
            </div>

            <p class="field__hint"><?= e($offerNote) ?></p>

            <button class="btn btn--primary" type="submit">Send offer to liaison</button>
        </form>
    </section>
</div>

<p class="page-intro__meta"><?= e($footerNote) ?></p>

<?php include __DIR__ . '/../../../partials/modal-sponsor-disaster-alert.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../../partials/footer.php';
?>
