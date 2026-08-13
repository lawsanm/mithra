<?php

declare(strict_types=1);

/**
 * Aid grant approvals. Figma "Aid Grants — Approval Queue" (378:295).
 *
 * @var array  $filters filter pills: label, slug, count, active
 * @var array  $grants  rows: id, initials, name, meta, status, status_label, action
 * @var string $status  current filter slug
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$grants ??= [
    ['id' => 1, 'initials' => 'ML', 'name' => 'M. Lawsan',       'meta' => '300 pts · school supplies · vouched by Mod. J. Kavipriya · 15 Jul', 'status' => 'info',    'status_label' => 'Awaiting approval', 'action' => 'review'],
    ['id' => 2, 'initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => '200 pts · medical costs · vouched by Mod. J. Kavipriya · 14 Jul',    'status' => 'info',    'status_label' => 'Awaiting approval', 'action' => 'review'],
    ['id' => 3, 'initials' => 'JK', 'name' => 'J. Kavipriya',    'meta' => '450 pts · roof repair · awaiting moderator vouch · 16 Jul',         'status' => 'warning', 'status_label' => 'Awaiting vouch',    'action' => 'view'],
    ['id' => 4, 'initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => '500 pts · flood recovery · approved 01 Jul · funded by Northwind Co', 'status' => 'success', 'status_label' => 'Approved',          'action' => 'view'],
    ['id' => 5, 'initials' => 'AA', 'name' => 'J. Kavipriya',    'meta' => '400 pts · declined 28 Jun · insufficient evidence, may re-apply',   'status' => 'error',   'status_label' => 'Declined',          'action' => 'view'],
];

$status ??= '';

$filters = [
    ['label' => 'All (5)',               'slug' => ''],
    ['label' => 'Awaiting vouch (1)',    'slug' => 'awaiting_vouch'],
    ['label' => 'Awaiting approval (2)', 'slug' => 'awaiting_approval'],
    ['label' => 'Approved (1)',          'slug' => 'approved'],
    ['label' => 'Declined (1)',          'slug' => 'declined'],
];

$pageTitle = 'Aid grant approvals';
$navActive = 'aid-grants';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Aid grant approvals</h1>
    <button class="btn btn--ghost page-header__action" type="button">Export approved grants</button>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= $status === $filter['slug'] ? ' pill--active' : '' ?>"
                href="/sponsor-liaison/aid-grants?status=<?= e(rawurlencode($filter['slug'])) ?>"
                <?= $status === $filter['slug'] ? 'aria-current="true"' : '' ?>
            ><?= e($filter['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($grants === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">Nothing here</p>
        <p class="empty-state__body">Aid grants in this state will appear here.</p>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($grants as $grant): ?>
            <li class="list-row">
                <span class="avatar avatar--md"><?= e($grant['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($grant['name']) ?></span>
                    <span class="list-row__meta"><?= e($grant['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($grant['status']) ?>"><?= e($grant['status_label']) ?></span>
                <?php
                $label   = $grant['action'] === 'review' ? 'Review' : 'View';
                $variant = $grant['action'] === 'review' ? 'btn--primary' : 'btn--ghost';
                ?>
                <?php if ($grant['id'] === 1): ?>
                    <a class="btn <?= e($variant) ?>" href="/sponsor-liaison/aid-grants/1"><?= e($label) ?></a>
                <?php else: ?>
                    <button class="btn <?= e($variant) ?>" type="button"><?= e($label) ?></button>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="notice notice--info notice--full">
    When reviewing, you can approve (and adjust the amount), reject with a reason, or request more information from the moderator.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
