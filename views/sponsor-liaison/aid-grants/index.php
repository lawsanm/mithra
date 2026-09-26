<?php

declare(strict_types=1);

/**
 * Aid grant approvals. Figma "Aid Grants — Approval Queue" (378:295).
 *
 * @var array  $filters filter pills: label, slug, count, active
 * @var array  $grants  rows: id, initials, name, meta, status, status_label, action
 * @var string $status  current filter slug
 */

$status ??= '';

$pageTitle = 'Aid grant approvals';
$navActive = 'aid-grants';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Aid grant approvals</h1>
    <a class="btn btn--ghost page-header__action" href="<?= base_url() ?>/sponsor-liaison/aid-grants/export">Export approved grants</a>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= $status === $filter['slug'] ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/sponsor-liaison/aid-grants?status=<?= e(rawurlencode($filter['slug'])) ?>"
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
                <a class="btn <?= e($variant) ?>" href="<?= base_url() ?>/sponsor-liaison/aid-grants/<?= e((string) $grant['id']) ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="notice notice--info notice--full">
    When reviewing, you can approve (and adjust the amount), reject with a reason, or request more information from the moderator.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
