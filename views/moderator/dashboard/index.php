<?php

declare(strict_types=1);

/**
 * Moderator dashboard — the queues waiting on this moderator, plus the disaster
 * relief callout. Every row links to the detail screen that owns the decision.
 *
 * @var array $moderator     greeting, division and conduct bond line
 * @var array $stats         four figures for the stat row
 * @var array $verifications rows: initials, title, meta, status, status_label, href
 * @var array $approvals     rows: title, meta, status, status_label, href
 * @var array $cases         rows: title, meta, status, status_label, href
 * @var array $relief        note under the disaster callout
 */

$pageTitle = 'Moderator dashboard';
$navActive = 'dashboard';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-intro page-intro--dashboard">
    <h1 class="page-intro__title"><?= e($moderator['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($moderator['membership']) ?></p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Pending verifications</h2>
        <a class="link section__action" href="<?= base_url() ?>/moderator/verifications">View all</a>
    </div>

    <?php if ($verifications === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon">
                <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-users"></use></svg>
            </span>
            <p class="empty-state__title">No verifications waiting</p>
            <p class="empty-state__body">
                New member applications and temporary membership proofs appear here as they are submitted.
            </p>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($verifications as $verification): ?>
                <li class="list-row">
                    <span class="avatar avatar--md"><?= e($verification['initials']) ?></span>
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($verification['title']) ?></span>
                        <span class="list-row__meta"><?= e($verification['meta']) ?></span>
                    </div>
                    <span class="badge badge--<?= e($verification['status']) ?>"><?= e($verification['status_label']) ?></span>
                    <a class="btn btn--ghost" href="<?= e($verification['href']) ?>">Review</a>
                    <a class="btn btn--primary" href="<?= e($verification['href']) ?>#decision">Approve</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Listing &amp; value-proof approvals</h2>
        <a class="link section__action" href="<?= base_url() ?>/moderator/listing-approvals">View all</a>
    </div>

    <ul class="row-list">
        <?php foreach ($approvals as $approval): ?>
            <li class="list-row">
                <?php $photoUrl = $approval['photo']; $photoTitle = $approval['title']; $photoClass = 'thumb--sm'; include __DIR__ . '/../../../partials/item-photo.php'; ?>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($approval['title']) ?></span>
                    <span class="list-row__meta"><?= e($approval['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($approval['status']) ?>"><?= e($approval['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($approval['href']) ?>">Review</a>
                <a class="btn btn--primary" href="<?= e($approval['href']) ?>#decision">Approve</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Active damage cases</h2>
        <a class="link section__action" href="<?= base_url() ?>/moderator/cases">View all</a>
    </div>

    <ul class="row-list">
        <?php foreach ($cases as $case): ?>
            <li class="list-row">
                <?php $photoUrl = $case['photo']; $photoTitle = $case['title']; $photoClass = 'thumb--sm'; include __DIR__ . '/../../../partials/item-photo.php'; ?>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($case['title']) ?></span>
                    <span class="list-row__meta"><?= e($case['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($case['status']) ?>"><?= e($case['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($case['href']) ?>">Review</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Disaster relief &amp; aid</h2>
        <div class="actions panel__actions">
            <span class="preview-action"><button type="button" disabled class="btn btn--ghost">Report disaster</button><span class="demo-note">Not available in this demo</span></span>
            <span class="preview-action"><button type="button" disabled class="btn btn--ghost">Record relief given</button><span class="demo-note">Not available in this demo</span></span>
            <a class="btn btn--primary" href="<?= base_url() ?>/moderator/aid-vouching">Vouch aid requests</a>
        </div>
    </div>
    <p class="panel__note"><?= e($relief) ?></p>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
