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

// Sample view data — replaced by the controller once ModerationController lands.
$moderator ??= [
    'greeting'   => 'Good morning, Kavipriya',
    'membership' => 'Wellawatte GN Division moderator  ·  Conduct bond: 500 pts held',
];

$stats ??= [
    [
        'label'   => 'Pending verifications',
        'value'   => '7',
        'note'    => '2 temporary membership',
        'primary' => true,
        'href'    => '/verifications',
    ],
    [
        'label' => 'Listings to approve',
        'value' => '4',
        'note'  => '1 with value proof',
        'href'  => '/listing-approvals',
    ],
    [
        'label' => 'Active damage cases',
        'value' => '2',
        'note'  => '1 awaiting sign-off',
        'href'  => '/damage-cases',
    ],
    [
        'label' => 'Resolved this month',
        'value' => '31',
        'note'  => 'Avg 1.8 days to close',
    ],
];

$verifications ??= [
    [
        'initials'     => 'AA',
        'title'        => 'New member application',
        'meta'         => 'A. Akalvily  ·  applied 15 Jul  ·  Wellawatte',
        'status'       => 'info',
        'status_label' => 'New member',
        'href'         => '/moderator/verifications/akalvily-a',
    ],
    [
        'initials'     => 'ML',
        'title'        => 'Temporary membership proof',
        'meta'         => 'M. Lawsan  ·  submitted 16 Jul  ·  from Kollupitiya',
        'status'       => 'warning',
        'status_label' => 'Proof to review',
        'href'         => '/moderator/verifications/lawsan-m',
    ],
];

$approvals ??= [
    [
        'title'        => 'Pressure Washer',
        'meta'         => 'Listed by T.H.K. Madushan  ·  value proof attached  ·  submitted 15 Jul',
        'status'       => 'warning',
        'status_label' => 'Pending approval',
        'href'         => '/moderator/listing-approvals/pressure-washer',
    ],
];

$cases ??= [
    [
        'title'        => 'Grinding Drill',
        'meta'         => 'Reported by T.H.K. Madushan  ·  14 Jul  ·  Case #CD-0142',
        'status'       => 'warning',
        'status_label' => 'Mediating',
        'href'         => '/moderator/cases/grinding-drill',
    ],
    [
        'title'        => 'Camping Tent (4-person)',
        'meta'         => 'M. Lawsan & J. Kavipriya  ·  repair confirmed',
        'status'       => 'info',
        'status_label' => 'Awaiting sign-off',
        'href'         => '/moderator/cases/camping-tent-4p',
    ],
];

$relief ??= '1 active disaster in your division  ·  6 aid requests pending your vouch';

$pageTitle = 'Moderator dashboard';
$navActive = 'dashboard';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title"><?= e($moderator['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($moderator['membership']) ?></p>
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
        <h2 class="section__title">Pending verifications</h2>
        <a class="link section__action" href="/moderator/verifications">View all</a>
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
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Listing &amp; value-proof approvals</h2>
        <a class="link section__action" href="/moderator/listing-approvals">View all</a>
    </div>

    <ul class="row-list">
        <?php foreach ($approvals as $approval): ?>
            <li class="list-row">
                <span class="thumb thumb--sm">Photo</span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($approval['title']) ?></span>
                    <span class="list-row__meta"><?= e($approval['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($approval['status']) ?>"><?= e($approval['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($approval['href']) ?>">Review</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Active damage cases</h2>
        <a class="link section__action" href="/moderator/cases">View all</a>
    </div>

    <ul class="row-list">
        <?php foreach ($cases as $case): ?>
            <li class="list-row">
                <span class="thumb thumb--sm">Photo</span>
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
            <a class="btn btn--ghost" href="/moderator/disasters/report">Report disaster</a>
            <a class="btn btn--ghost" href="/moderator/disasters/relief">Record relief given</a>
            <a class="btn btn--primary" href="/moderator/aid-vouching">Vouch aid requests</a>
        </div>
    </div>
    <p class="panel__note"><?= e($relief) ?></p>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
