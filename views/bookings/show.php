<?php

declare(strict_types=1);

/**
 * Booking detail. One view per controller action; $booking['state'] selects the
 * panel, matching the seven Figma frames:
 *   awaiting        71:113   handover        71:156   in-progress   71:229
 *   return          72:74    overdue         72:134   auto-cancelled 72:181
 *   pending-moderator 72:215
 *
 * @var array $booking state, item, badge, meta, party, terms, escrow
 * @var array $photos  baseline / borrower / lender / return / evidence sets
 * @var array $claim   claim summary shown in the pending-moderator state
 */

// Sample view data — replaced by the controller once BookingController lands.
$booking ??= [];
$booking += [
    'state'        => (string) ($_GET['state'] ?? 'awaiting'),
    'item'         => 'Bosch Cordless Drill GSB 120',
    'party'        => 'Lender: T.H.K. Madushan   Trust 96  ·  23 lends  ·  ✓ Verified',
    'terms'        => '12 – 17 Jul 2026  ·  5 days  ·  monthly option not applicable',
    'escrow'       => '75 pts',
    'escrow_note'  => 'held in escrow',
    'notes'        => 'Small scratch on the battery cover, noted by both parties.',
];

$states = [
    'awaiting' => [
        'badge'       => ['info', 'i', 'Awaiting lender response'],
        'meta'        => 'Requested 17 Jul 2026, 3:40 PM  ·  as Borrower',
        'notice'      => ['info', 'Madushan has 48 hours to respond — by 19 Jul, 3:40 PM. If there’s no '
                        . 'decision by then, the request is automatically cancelled and your 75 pts return '
                        . 'to your wallet. Points are reserved but have not moved yet.'],
    ],
    'handover' => [
        'badge'  => ['warning', '!', 'Handover in progress'],
        'meta'   => 'Accepted 18 Jul  ·  meet at handover  ·  as Borrower',
        'notice' => ['warning', 'Both parties must upload photos and accept before the rental starts. '
                   . 'These photos are the condition baseline used at return.'],
    ],
    'in-progress' => [
        'badge'  => ['success', '✓', 'Rental active'],
        'meta'   => 'Handover completed 12 Jul  ·  due back 17 Jul  ·  as Borrower',
        'notice' => ['success', 'Rental active — due back tomorrow, 17 Jul. Return on time to protect '
                   . 'your on-time streak and trust score.'],
    ],
    'return' => [
        'badge'  => ['info', 'i', 'Return in progress'],
        'meta'   => 'Due 17 Jul  ·  returning today  ·  as Borrower',
        'notice' => ['info', 'Compare each angle against the handover baseline. If the condition matches, '
                   . 'confirm the return — escrow is released to Madushan and your booking closes.'],
    ],
    'overdue' => [
        'badge'  => ['error', '✕', '3 days overdue'],
        'meta'   => 'Was due 17 Jul  ·  today is 20 Jul  ·  as Borrower',
        'notice' => ['error', 'This return is 3 days late. Late fees of 15 pts (5 pts/day) have accrued '
                   . 'and Madushan has been notified. Continued delay affects your on-time record and '
                   . 'trust score, and may be escalated to your moderator.'],
    ],
    'auto-cancelled' => [
        'badge'  => ['warning', '!', 'Auto-cancelled'],
        'meta'   => 'Requested 17 Jul  ·  cancelled 19 Jul  ·  as Borrower',
        'notice' => ['warning', 'Madushan didn’t respond within 48 hours, so this request was '
                   . 'automatically cancelled on 19 Jul at 3:40 PM. Your 75 pts have been returned to '
                   . 'your wallet in full. No-response requests don’t affect anyone’s trust score.'],
    ],
    'pending-moderator' => [
        'badge'  => ['info', 'i', 'Pending moderator'],
        'meta'   => 'Damage claim raised 18 Jul  ·  read-only  ·  as Borrower',
        'notice' => ['info', 'A damage claim is under review. Moderator A. Akalvily (Kollupitiya) will '
                   . 'arrange an in-person resolution with both parties. This booking is read-only until '
                   . 'the case is resolved and all three parties sign off.'],
    ],
];

$state = isset($states[$booking['state']]) ? $booking['state'] : 'awaiting';
$view  = $states[$state];

$claim ??= [
    'severity'   => 'Moderate',
    'amount'     => '60 pts',
    'cap'        => '300 pts',
    'raised_by'  => 'Madushan (lender)',
    'evidence'   => 4,
];

$pageTitle = $booking['item'];
$navActive = 'bookings';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/bookings">My Bookings</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($booking['item']) ?></span>
</nav>

<header class="record-head">
    <h1 class="record-head__title"><?= e($booking['item']) ?></h1>
    <span class="badge badge--<?= e($view['badge'][0]) ?>">
        <span aria-hidden="true"><?= e($view['badge'][1]) ?></span>
        <?= e($view['badge'][2]) ?>
    </span>
</header>

<p class="record-meta"><?= e($view['meta']) ?></p>

<?php // The summary row is hidden once the booking has closed or gone read-only. ?>
<?php if (!in_array($state, ['return', 'overdue', 'auto-cancelled', 'pending-moderator'], true)): ?>
    <div class="record-card">
        <span class="thumb <?= $state === 'handover' ? 'thumb--row' : 'thumb--sm' ?>"></span>
        <div class="record-card__body">
            <span class="record-card__party"><?= e($booking['party']) ?></span>
            <span class="record-card__terms"><?= e($booking['terms']) ?></span>
        </div>
        <span class="record-card__amount">
            <strong><?= e($booking['escrow']) ?></strong>
            <span><?= e($booking['escrow_note']) ?></span>
        </span>
    </div>
<?php endif; ?>

<?php if ($state === 'awaiting' || $state === 'in-progress' || $state === 'overdue'
    || $state === 'auto-cancelled' || $state === 'pending-moderator'): ?>
    <p class="notice notice--<?= e($view['notice'][0]) ?>">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-<?= $view['notice'][0] === 'success' ? 'check-circle' : ($view['notice'][0] === 'error' ? 'x' : ($view['notice'][0] === 'warning' ? 'alert-triangle' : 'info')) ?>"></use></svg>
        <?= e($view['notice'][1]) ?>
    </p>
<?php endif; ?>

<?php if ($state === 'handover'): ?>

    <div class="panel-row">
        <section class="panel panel--half">
            <h2 class="panel__title">Your handover photos (borrower)</h2>
            <div class="photo-grid">
                <?php for ($photo = 1; $photo <= 3; $photo++): ?>
                    <span class="thumb thumb--photo-tall"></span>
                <?php endfor; ?>
            </div>
            <form method="post" action="/bookings/1/handover-photos" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label class="upload-drop upload-drop--sm">
                    <span class="upload-drop__glyph" aria-hidden="true">＋</span>
                    <span>Add another photo</span>
                    <input class="visually-hidden" type="file" name="photos[]" accept="image/*" multiple>
                </label>
            </form>
        </section>

        <section class="panel panel--half">
            <h2 class="panel__title">Madushan’s handover photos (lender)</h2>
            <div class="photo-grid">
                <?php for ($photo = 1; $photo <= 4; $photo++): ?>
                    <span class="thumb thumb--photo-tall"></span>
                <?php endfor; ?>
            </div>
            <span class="badge badge--success">
                <span aria-hidden="true">✓</span>
                Uploaded 4 photos
            </span>
        </section>
    </div>

    <div class="field">
        <label class="field__label" for="condition-notes">Condition notes</label>
        <textarea class="textarea" id="condition-notes" name="condition_notes"><?= e($booking['notes']) ?></textarea>
    </div>

    <p class="notice notice--warning">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
        <?= e($view['notice'][1]) ?>
    </p>

<?php elseif ($state === 'in-progress'): ?>

    <section class="panel">
        <h2 class="panel__title">Handover baseline photos  ·  12 Jul</h2>
        <div class="photo-grid">
            <?php for ($photo = 1; $photo <= 5; $photo++): ?>
                <span class="thumb thumb--photo"></span>
            <?php endfor; ?>
        </div>
    </section>

<?php elseif ($state === 'return'): ?>

    <div class="panel-row">
        <section class="panel panel--half">
            <h2 class="panel__title">Handover baseline  ·  12 Jul</h2>
            <div class="photo-grid">
                <?php for ($photo = 1; $photo <= 5; $photo++): ?>
                    <span class="thumb thumb--photo"></span>
                <?php endfor; ?>
            </div>
        </section>

        <section class="panel panel--half">
            <h2 class="panel__title">Return photos  ·  17 Jul</h2>
            <div class="photo-grid">
                <?php for ($photo = 1; $photo <= 3; $photo++): ?>
                    <span class="thumb thumb--photo"></span>
                <?php endfor; ?>
            </div>
            <form method="post" action="/bookings/1/return-photos" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label class="upload-drop upload-drop--sm">
                    <span aria-hidden="true">＋</span>
                    <span>Add return photo</span>
                    <input class="visually-hidden" type="file" name="photos[]" accept="image/*" multiple>
                </label>
            </form>
        </section>
    </div>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        <?= e($view['notice'][1]) ?>
    </p>

<?php elseif ($state === 'overdue'): ?>

    <section class="panel panel--narrow">
        <p class="line-item">
            <span class="line-item__label">Rental total</span>
            <span class="line-item__value">75 pts</span>
        </p>
        <p class="line-item line-item--negative">
            <span class="line-item__label">Late fees (3 × 5 pts)</span>
            <span class="line-item__value">+15 pts</span>
        </p>
        <p class="line-item line-item--total">
            <span class="line-item__label">Total on return</span>
            <span class="line-item__value">90 pts</span>
        </p>
    </section>

<?php elseif ($state === 'pending-moderator'): ?>

    <section class="panel">
        <h2 class="panel__title">Claim summary</h2>
        <div class="facts">
            <span class="fact">
                <span class="fact__label">Claimed severity</span>
                <span class="fact__value"><?= e($claim['severity']) ?></span>
            </span>
            <span class="fact">
                <span class="fact__label">Claimed amount</span>
                <span class="fact__value"><?= e($claim['amount']) ?></span>
            </span>
            <span class="fact">
                <span class="fact__label">Declared value cap</span>
                <span class="fact__value"><?= e($claim['cap']) ?></span>
            </span>
            <span class="fact">
                <span class="fact__label">Raised by</span>
                <span class="fact__value"><?= e($claim['raised_by']) ?></span>
            </span>
        </div>
        <h3 class="field__label">Evidence photos</h3>
        <div class="photo-grid">
            <?php for ($photo = 1; $photo <= $claim['evidence']; $photo++): ?>
                <span class="thumb thumb--photo"></span>
            <?php endfor; ?>
        </div>
    </section>

<?php endif; ?>

<div class="actions">
    <?php if ($state === 'awaiting'): ?>
        <a class="btn btn--ghost" href="/bookings/1/cancel">Cancel request</a>
        <a class="btn btn--ghost" href="/messages/new">Message lender</a>
    <?php elseif ($state === 'handover'): ?>
        <a class="btn btn--primary" href="/bookings/1/accept-handover">Accept handover</a>
        <a class="btn btn--ghost" href="/bookings/1/cancel">Cancel booking</a>
    <?php elseif ($state === 'in-progress'): ?>
        <a class="btn btn--primary" href="/bookings/1?state=return">Start return</a>
        <a class="btn btn--ghost" href="/bookings/1/report">Report a problem</a>
    <?php elseif ($state === 'return'): ?>
        <a class="btn btn--primary" href="/bookings/1/confirm-return">Confirm return — release escrow</a>
        <a class="btn btn--ghost" href="/bookings/1/damage-claim" data-modal-open="damage-claim">Raise damage claim</a>
    <?php elseif ($state === 'overdue'): ?>
        <a class="btn btn--primary" href="/bookings/1?state=return">Start return now</a>
        <a class="btn btn--ghost" href="/messages/new">Message lender</a>
    <?php elseif ($state === 'auto-cancelled'): ?>
        <a class="btn btn--primary" href="/items/browse">Browse similar items</a>
        <a class="btn btn--ghost" href="/items/1">Request again</a>
    <?php else: ?>
        <button class="btn btn--primary" type="button" disabled>Awaiting moderator resolution</button>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../partials/modal-damage-claim.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
