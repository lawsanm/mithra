<?php

declare(strict_types=1);

$pageTitle = (string) $booking['item_title'];
$navActive = 'bookings';
include __DIR__ . '/../../partials/header.php';
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/bookings?role=<?= e($role) ?>">My Bookings</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($pageTitle) ?></span>
</nav>
<header class="record-head">
    <h1 class="record-head__title"><?= e($pageTitle) ?></h1>
    <span class="badge badge--<?= e($status[0]) ?>"><?= e($status[1] . ' ' . $status[2]) ?></span>
</header>
<p class="record-meta">Booking #<?= e((string) $booking['id']) ?> · As <?= e(ucfirst($role)) ?> · Requested <?= e(date('j M Y', strtotime($booking['requested_at']))) ?></p>
<div class="record-card">
    <div class="record-card__body">
        <span class="record-card__party"><?= e($role === 'borrower' ? 'Lender: ' . $booking['lender_name'] : 'Borrower: ' . $booking['borrower_name']) ?></span>
        <span class="record-card__terms"><?= e(date('j M Y', strtotime($booking['start_date']))) ?> – <?= e(date('j M Y', strtotime($booking['end_date']))) ?> · <?= e((string) $booking['days']) ?> days</span>
    </div>
    <span class="record-card__amount"><strong><?= e((string) $booking['rental_charge']) ?> pts</strong><span>rental charge</span></span>
</div>
<?php if ((int) $booking['days_overdue'] > 0 && in_array($booking['status'], ['in_progress', 'awaiting_return'], true)): ?>
    <p class="notice notice--warning notice--full">The scheduled return date has passed by <?= e((string) $booking['days_overdue']) ?> days.</p>
<?php endif; ?>
<section class="panel">
    <h2 class="panel__title">Booking details</h2>
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Lender</dt><dd class="fact__value"><?= e($booking['lender_name']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Borrower</dt><dd class="fact__value"><?= e($booking['borrower_name']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Agreed rate</dt><dd class="fact__value"><?= e((string) $booking['agreed_rate']) ?> pts · <?= e($booking['rate_basis']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Declared item value</dt><dd class="fact__value"><?= e((string) $booking['declared_value']) ?></dd></div>
    </dl>
</section>
<section class="panel">
    <h2 class="panel__title">Handover record</h2>
    <p class="panel__note"><?= e($booking['lender_notes'] ?: 'No lender condition notes recorded.') ?></p>
    <p class="panel__note"><?= e($booking['borrower_notes'] ?: 'No borrower condition notes recorded.') ?></p>
    <p class="panel__note"><?= e((string) $booking['lender_photo_count']) ?> lender photos · <?= e((string) $booking['borrower_photo_count']) ?> borrower photos on record.</p>
</section>
<p class="notice notice--info notice--full">Booking changes, messages and handover actions are not available in this demo. This page shows the saved booking record.</p>
<div class="actions">
    <a class="btn btn--ghost" href="<?= base_url() ?>/bookings?role=<?= e($role) ?>">Back to My Bookings</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/members/<?= e((string) ($role === 'borrower' ? $booking['lender_id'] : $booking['borrower_id'])) ?>">View <?= e($role === 'borrower' ? 'lender' : 'borrower') ?> profile</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
