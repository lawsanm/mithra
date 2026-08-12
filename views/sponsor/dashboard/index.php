<?php

declare(strict_types=1);

/**
 * Sponsor dashboard. Figma: "Sponsor Dashboard" (385:11).
 *
 * @var array $sponsor       greeting and standing line
 * @var array $stats         four figures for the stat row
 * @var array $callouts      rows: icon, title, meta, status, status_label, action_label, action_href
 * @var array $impact        CSR impact panel note
 * @var array $activeAlert   the disaster this sponsor is being connected to, or null
 */

// Sample view data — replaced by the controller once SponsorController lands.
$sponsor ??= [
    'greeting'  => 'Good morning, Northwind Co',
    'standing'  => 'Active sponsor since 2024  ·  written agreement on file  ·  liaison: A. Akalvily',
];

$stats ??= [
    ['label' => 'Total contributed', 'value' => 'LKR 16,000', 'note' => '2 purchases this year', 'primary' => true],
    ['label' => 'Points generated',  'value' => '16,000',     'note' => '1 rupee = 1 point, no deductions'],
    ['label' => 'Aid grants funded', 'value' => '9',          'note' => 'From your Aid Pool share', 'href' => '/sponsor/csr-reports'],
    ['label' => 'Unread alerts',     'value' => '1',          'note' => 'Disaster Mode active', 'href' => '/sponsor/notifications'],
];

$callouts ??= [
    [
        'icon'          => 'alert-triangle',
        'title'         => 'Disaster Mode active — Wellawatte flooding',
        'meta'          => 'Regional flood event declared. Moderators are coordinating relief on the ground.  ·  2 hrs ago',
        'status'        => 'error',
        'status_label'  => 'Disaster Mode',
        'action_label'  => 'Connect',
        'action_href'   => '/sponsor/disasters/1',
    ],
    [
        'icon'          => 'heart',
        'title'         => 'Aid request pending your response',
        'meta'          => 'Your liaison shared a relief request matching your CSR focus.  ·  1 day ago',
        'status'        => 'warning',
        'status_label'  => 'Awaiting response',
        'action_label'  => 'Review',
        'action_href'   => '/sponsor/disasters/1',
    ],
];

$impact ??= [
    'title' => 'Your CSR impact',
    'note'  => 'LKR 16,000 contributed  ·  funded 14 welcome bonuses and 9 aid grants  ·  '
             . 'featured on the sponsor wall & monthly newsletter',
];

$activeAlert ??= [
    'id'       => '1',
    'division' => 'Wellawatte GN Division',
    'reason'   => 'Regional flood event',
    'affected' => '60 households affected',
    'liaison'  => 'A. Akalvily',
];

$pageTitle = 'Sponsor dashboard';
$navActive = 'dashboard';

include __DIR__ . '/../../../partials/header-sponsor.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title"><?= e($sponsor['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($sponsor['standing']) ?></p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php if (isset($stat['href'])): ?>
            <a class="stat-card" href="<?= e($stat['href']) ?>">
                <span class="stat-card__label"><?= e($stat['label']) ?></span>
                <strong class="stat-card__value<?= !empty($stat['primary']) ? ' stat-card__value--primary' : '' ?>"><?= e($stat['value']) ?></strong>
                <span class="stat-card__note"><?= e($stat['note']) ?></span>
            </a>
        <?php else: ?>
            <div class="stat-card">
                <span class="stat-card__label"><?= e($stat['label']) ?></span>
                <strong class="stat-card__value"><?= e($stat['value']) ?></strong>
                <span class="stat-card__note"><?= e($stat['note']) ?></span>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Recent notifications</h2>
        <a class="link section__action" href="/sponsor/notifications">View all</a>
    </div>

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

<?php if ($activeAlert !== null): ?>
    <?php include __DIR__ . '/../../../partials/modal-sponsor-disaster-alert.php'; ?>
    <?php $pageScripts = ['modal.js', 'sponsor-disaster-alert.js']; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
