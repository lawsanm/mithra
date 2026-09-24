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

$pageTitle = 'Disaster relief';
$navActive = 'dashboard';

$chrome = 'sponsor';
include __DIR__ . '/../../../partials/header.php';

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
        <div class="stack" data-demo-form>
            <p class="demo-note">Preview only. Saving is not available yet.</p>
            <div class="field">
                <label class="field__label" for="offer_amount">Amount (LKR)</label>
                <input class="input" type="number" id="offer_amount" name="amount" placeholder="25,000" disabled>
            </div>

            <p class="field__hint"><?= e($offerNote) ?></p>

            <button class="btn btn--primary" type="submit" disabled>Send offer to liaison</button>
        </div>
    </section>
</div>

<p class="page-intro__meta"><?= e($footerNote) ?></p>

<?php include __DIR__ . '/../../../partials/modal-sponsor-disaster-alert.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../../partials/footer.php';
?>
