<?php

declare(strict_types=1);

/**
 * My bookings. Figma: "My Bookings — List" (71:58).
 *
 * @var array $tabs     role tabs: label, role, active
 * @var array $bookings rows: title, meta, status, status_glyph, status_label, href
 */

// Sample view data — replaced by the controller once BookingController lands.
$tabs ??= [
    ['label' => 'As Borrower (2)', 'role' => 'borrower', 'active' => true],
    ['label' => 'As Lender (3)',   'role' => 'lender'],
];

$bookings ??= [
    [
        'title'        => 'Bosch Cordless Drill',
        'meta'         => 'T.H.K. Madushan  ·  12–17 Jul  ·  75 pts in escrow',
        'status'       => 'warning',
        'status_glyph' => '!',
        'status_label' => 'Return due tomorrow',
        'href'         => '/bookings/1',
    ],
    [
        'title'        => 'Camping Tent (4-person)',
        'meta'         => 'J. Kavipriya  ·  14–21 Jul  ·  126 pts in escrow',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'In progress',
        'href'         => '/bookings/2',
    ],
    [
        'title'        => 'Projector (Full HD)',
        'meta'         => 'A. Akalvily  ·  requested 20–22 Jul',
        'status'       => 'info',
        'status_glyph' => 'i',
        'status_label' => 'Awaiting lender response',
        'href'         => '/bookings/3',
    ],
];

$pageTitle = 'My bookings';
$navActive = 'bookings';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">My Bookings</h1>

<nav class="tabs" aria-label="Booking role">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="tabs__link<?= !empty($tab['active']) ? ' tabs__link--active' : '' ?>"
            href="/bookings?role=<?= e(rawurlencode($tab['role'])) ?>"
            <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
        ><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

<ul class="row-list">
    <?php foreach ($bookings as $booking): ?>
        <li class="list-row">
            <span class="thumb thumb--sm"></span>
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

<?php include __DIR__ . '/../../partials/footer.php'; ?>
