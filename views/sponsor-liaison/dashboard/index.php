<?php

declare(strict_types=1);

/**
 * Sponsor Liaison dashboard — Figma "Liaison Dashboard" (377:45). Sponsors
 * this liaison manages, aid grants waiting on their approval, and disaster
 * mode status for their coverage area.
 *
 * @var array $liaison   greeting and coverage line
 * @var array $stats     four figures for the stat row
 * @var array $sponsors  rows: name, meta, href
 * @var array $aidGrants rows: initials, title, meta, status, status_label, href
 * @var array $disaster  active flag and note under the Disaster Mode panel
 */

$pageTitle = 'Sponsor Liaison dashboard';
$navActive = 'dashboard';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-intro page-intro--dashboard">
    <h1 class="page-intro__title"><?= e($liaison['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($liaison['coverage']) ?></p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = !empty($stat['primary']) ? 'primary' : ''; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Sponsors</h2>
        <a class="link section__action" href="<?= base_url() ?>/sponsor-liaison/sponsors">View all</a>
    </div>

    <?php if ($sponsors === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">No sponsors yet</p>
            <p class="empty-state__body">Onboard a sponsor to start receiving contributions.</p>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($sponsors as $sponsor): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($sponsor['name']) ?></span>
                        <span class="list-row__meta"><?= e($sponsor['meta']) ?></span>
                    </div>
                    <a class="btn btn--ghost" href="<?= e($sponsor['href']) ?>">View</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="actions">
    <a class="btn btn--primary" href="<?= base_url() ?>/sponsor-liaison/sponsors/onboarding">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Add sponsor
    </a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/purchases/create">Record purchase</a>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Aid grants awaiting your approval</h2>
        <a class="link section__action" href="<?= base_url() ?>/sponsor-liaison/aid-grants">View all</a>
    </div>

    <?php if ($aidGrants === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">Nothing waiting on you</p>
            <p class="empty-state__body">Aid grants vouched by a moderator will appear here for your approval.</p>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($aidGrants as $grant): ?>
                <li class="list-row">
                    <span class="avatar avatar--md"><?= e($grant['initials']) ?></span>
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($grant['title']) ?></span>
                        <span class="list-row__meta"><?= e($grant['meta']) ?></span>
                    </div>
                    <span class="badge badge--<?= e($grant['status']) ?>"><?= e($grant['status_label']) ?></span>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/aid-grants/<?= e((string) $grant['id']) ?>">Review</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Disaster Mode</h2>
    </div>
    <p class="panel__note"><?= e($disaster['note']) ?></p>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
