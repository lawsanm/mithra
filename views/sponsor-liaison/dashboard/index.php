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

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$liaison ??= [
    'greeting' => 'Good morning, Akalvily',
    'coverage' => 'Sponsor Liaison  ·  Colombo District  ·  6 GN divisions covered',
];

$stats ??= [
    [
        'label'   => 'Active sponsors',
        'value'   => '3',
        'note'    => '3 written agreements',
        'primary' => true,
        'href'    => '/sponsor-liaison/sponsors',
    ],
    [
        'label'   => 'Points pool balance',
        'value'   => '42,300',
        'note'    => 'Across Sponsor & Aid pools',
        'primary' => true,
        'href'    => '/sponsor-liaison/points-pool',
    ],
    [
        'label'   => 'Pending aid grant approvals',
        'value'   => '2',
        'note'    => 'Vouched by moderator, awaiting your approval',
        'primary' => true,
        'href'    => '/sponsor-liaison/aid-grants',
    ],
    [
        'label' => 'Disaster Mode',
        'value' => 'Inactive',
        'note'  => 'Payouts operating normally',
        'href'  => '/sponsor-liaison/disasters',
    ],
];

$sponsors ??= [
    [
        'name' => 'Northwind Co',
        'meta' => 'contact@northwind.lk  ·  16,000 pts injected',
        'href' => '/sponsor-liaison/sponsors/1',
    ],
    [
        'name' => 'ACM Corp',
        'meta' => 'hello@acm.lk  ·  6,500 pts injected',
        'href' => '/sponsor-liaison/sponsors/2',
    ],
    [
        'name' => 'Texa',
        'meta' => 'team@texa.lk  ·  7,500 pts injected',
        'href' => '/sponsor-liaison/sponsors/3',
    ],
];

$aidGrants ??= [
    [
        'id'           => 1,
        'initials'     => 'ML',
        'title'        => 'M. Lawsan',
        'meta'         => '300 pts  ·  school supplies  ·  vouched by Mod. J. Kavipriya',
        'status'       => 'info',
        'status_label' => 'Awaiting approval',
    ],
    [
        'id'           => 2,
        'initials'     => 'TM',
        'title'        => 'T.H.K. Madushan',
        'meta'         => '200 pts  ·  medical costs  ·  vouched by Mod. J. Kavipriya',
        'status'       => 'info',
        'status_label' => 'Awaiting approval',
    ],
];

$disaster ??= [
    'active' => false,
    'note'   => 'Currently inactive. Activating fast-tracks aid and alerts all sponsors.',
];

$pageTitle = 'Sponsor Liaison dashboard';
$navActive = 'dashboard';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title"><?= e($liaison['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($liaison['coverage']) ?></p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <a class="stat-card" href="<?= e($stat['href']) ?>">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value<?= !empty($stat['primary']) ? ' stat-card__value--primary' : '' ?>"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Sponsors</h2>
        <a class="link section__action" href="/sponsor-liaison/sponsors">View all</a>
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
    <a class="btn btn--primary" href="/sponsor-liaison/sponsors/onboarding">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Add sponsor
    </a>
    <a class="btn btn--ghost" href="/sponsor-liaison/purchases/create">Record purchase</a>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Aid grants awaiting your approval</h2>
        <a class="link section__action" href="/sponsor-liaison/aid-grants">View all</a>
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
                    <?php if ($grant['id'] === 1): ?>
                        <a class="btn btn--ghost" href="/sponsor-liaison/aid-grants/1">Review</a>
                    <?php else: ?>
                        <button class="btn btn--ghost" type="button">Review</button>
                    <?php endif; ?>
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
