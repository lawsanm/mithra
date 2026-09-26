<?php

declare(strict_types=1);

/**
 * Sponsor dashboard. Figma: "Sponsor Dashboard" (385:11).
 *
 * @var array $sponsor       greeting and standing line
 * @var array $stats         four figures for the stat row
 * @var array $callouts      rows: icon, title, meta, status, status_label, action_label, action_href
 * @var array $impact        CSR impact panel note
 */

$pageTitle = 'Sponsor dashboard';
$navActive = 'dashboard';

$chrome = 'sponsor';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-intro page-intro--dashboard">
    <h1 class="page-intro__title"><?= e($sponsor['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($sponsor['standing']) ?></p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = ''; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <?php if ($activeEvents !== []): ?>
        <h2 class="section__title">Active disaster relief</h2>
        <ul class="row-list">
            <?php foreach ($activeEvents as $event): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($event['reason']) ?></span>
                        <span class="list-row__meta"><?= e($event['division_name']) ?></span>
                    </div>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor/disasters/<?= e((string) $event['id']) ?>">View relief</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div class="section__head">
        <h2 class="section__title">Recent notifications</h2>
        <a class="link section__action" href="<?= base_url() ?>/sponsor/notifications">View all</a>
    </div>

    <?php if ($callouts === []): ?>
        <p class="empty-state">No notifications to show.</p>
    <?php endif; ?>

    <ul class="row-list">
        <?php foreach ($callouts as $callout): ?>
            <li class="list-row">
                <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-<?= e($callout['icon']) ?>"></use></svg>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($callout['title']) ?></span>
                    <span class="list-row__meta"><?= e($callout['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($callout['status']) ?>"><?= e($callout['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($callout['action_href']) ?>"><?= e($callout['action_label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <h2 class="panel__title"><?= e($impact['title']) ?></h2>
    <p class="panel__note"><?= e($impact['note']) ?></p>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
