<?php

declare(strict_types=1);

/**
 * My bookings. Figma: "My Bookings — List" (71:58).
 *
 * @var array  $tabs        role tabs: label, role, active
 * @var string $role        borrower | lender
 * @var bool   $past        showing finished, declined and cancelled bookings
 * @var int    $page        1-based page number
 * @var bool   $hasNextPage
 * @var array  $bookings    rows: title, photo, meta, status, status_glyph, status_label, href
 */

$pageTitle = 'My bookings';
$navActive = 'bookings';

$listUrl = static fn (array $changes = []): string => base_url() . '/bookings?' . http_build_query(array_filter(array_replace([
    'role'  => $role,
    'state' => $past ? 'past' : '',
], $changes), static fn ($value): bool => $value !== '' && $value !== 1));

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">My Bookings</h1>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<nav class="tabs" aria-label="Booking role">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="tabs__link<?= !empty($tab['active']) ? ' tabs__link--active' : '' ?>"
            href="<?= e($listUrl(['role' => $tab['role'], 'page' => ''])) ?>"
            <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
        ><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

<ul class="filter-pills" aria-label="Booking state">
    <li><a class="pill<?= !$past ? ' pill--active' : '' ?>" href="<?= e($listUrl(['state' => '', 'page' => ''])) ?>">Current</a></li>
    <li><a class="pill<?= $past ? ' pill--active' : '' ?>" href="<?= e($listUrl(['state' => 'past', 'page' => ''])) ?>">Past</a></li>
</ul>

<?php if ($bookings === []): ?>
    <div class="empty-state">
        <p class="empty-state__title"><?= $past ? 'No past bookings in this role' : 'No active bookings in this role' ?></p>
        <p class="empty-state__body">Your bookings will appear here when a request is made.</p>
        <a class="btn btn--ghost" href="<?= base_url() ?>/items/browse">Browse items</a>
    </div>
<?php endif; ?>
<ul class="row-list">
    <?php foreach ($bookings as $booking): ?>
        <li class="list-row">
            <?php $photoUrl = $booking['photo']; $photoTitle = $booking['title']; $photoClass = 'thumb--sm'; include __DIR__ . '/../../partials/item-photo.php'; ?>
            <div class="list-row__body">
                <span class="list-row__title"><?= e($booking['title']) ?></span>
                <span class="list-row__meta"><?= e($booking['meta']) ?></span>
            </div>
            <span class="badge badge--<?= e($booking['status']) ?>">
                <span aria-hidden="true"><?= e($booking['status_glyph']) ?></span>
                <?= e($booking['status_label']) ?>
            </span>
            <a class="btn btn--ghost" href="<?= e($booking['href']) ?>">View</a>
        </li>
    <?php endforeach; ?>
</ul>

<?php
$pageUrl = static fn (int $target): string => $listUrl(['page' => $target]);
include __DIR__ . '/../../partials/pager.php';
?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
