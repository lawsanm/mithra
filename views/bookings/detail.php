<?php

declare(strict_types=1);

/**
 * One booking, for its borrower or its lender. Figma: "Booking — Awaiting
 * Response" and the booking states after it.
 *
 * @var array  $booking  the findForDetail() row
 * @var string $role     borrower | lender
 * @var array  $status   badge class, glyph, label
 * @var array  $actions  accept, decline, cancel — what this member may do now
 * @var string $answerBy when an unanswered request auto-cancels
 * @var string $endedBy  why a booking that did not run ended
 * @var array|null $handover me, sides (label, photos, note, accepted), locked, at, can_edit, can_accept, waiting
 * @var array|null $return   me, sides (label, photos, note), returned, decision, can_edit, can_accept, can_claim
 * @var array|null $claim    the damage claim on this booking and what this member can do
 * @var array      $claimItem the claim modal's header and limits
 * @var bool       $claimOpen render the claim dialog open
 * @var string|null $rateHref where to rate the other member, while not yet rated
 * @var array|null  $dispute  can_raise, route, record (the latest dispute)
 * @var array|null $flash
 */

$pageTitle = (string) $booking['item_title'];
$navActive = 'bookings';
$id        = (string) $booking['id'];
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
<p class="record-meta">Booking #<?= e($id) ?> · As <?= e(ucfirst($role)) ?> · Requested <?= e(date('j M Y, H:i', strtotime($booking['requested_at']))) ?></p>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="record-card">
    <div class="record-card__body">
        <span class="record-card__party"><?= e($role === 'borrower' ? 'Lender: ' . $booking['lender_name'] : 'Borrower: ' . $booking['borrower_name']) ?></span>
        <span class="record-card__terms"><?= e(date('j M Y', strtotime($booking['start_date']))) ?> – <?= e(date('j M Y', strtotime($booking['end_date']))) ?> · <?= e((string) $booking['days']) ?> day<?= (int) $booking['days'] === 1 ? '' : 's' ?></span>
    </div>
    <span class="record-card__amount"><strong><?= e((string) $booking['rental_charge']) ?> pts</strong><span>rental charge</span></span>
</div>

<?php if ((int) $booking['days_overdue'] > 0 && in_array($booking['status'], ['in_progress', 'awaiting_return'], true)): ?>
    <p class="notice notice--warning notice--full">The scheduled return date has passed by <?= e((string) $booking['days_overdue']) ?> days.</p>
<?php endif; ?>

<?php if ($booking['status'] === 'requested'): ?>
    <p class="notice notice--info notice--full">
        <?= $role === 'lender'
            ? 'Answer by ' . e($answerBy) . '. After that the request is cancelled automatically.'
            : 'Waiting for ' . e($booking['lender_name']) . ' to answer, by ' . e($answerBy) . '. No points have moved yet.' ?>
    </p>
<?php endif; ?>

<?php if ($endedBy !== ''): ?>
    <p class="notice notice--info notice--full"><?= e($endedBy) ?></p>
<?php endif; ?>

<?php if ($rateHref !== null): ?>
    <div class="help-cta">
        <p class="help-cta__text">How did it go? Rate <?= e($role === 'borrower' ? $booking['lender_name'] : $booking['borrower_name']) ?> — it feeds their trust score.</p>
        <a class="btn btn--primary" href="<?= e($rateHref) ?>">Rate this <?= $booking['status'] === 'completed' ? 'booking' : 'cancellation' ?></a>
    </div>
<?php endif; ?>

<section class="panel">
    <h2 class="panel__title">Booking details</h2>
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Lender</dt><dd class="fact__value"><?= e($booking['lender_name']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Borrower</dt><dd class="fact__value"><?= e($booking['borrower_name']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Agreed rate</dt><dd class="fact__value"><?= e((string) $booking['agreed_rate']) ?> pts · <?= e($booking['rate_basis']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Rental charge</dt><dd class="fact__value"><?= e((string) $booking['rental_charge']) ?> pts</dd></div>
        <div class="fact"><dt class="fact__label">Late buffer</dt><dd class="fact__value"><?= e((string) $booking['late_buffer']) ?> pts · returned if on time</dd></div>
        <div class="fact"><dt class="fact__label">Held in escrow on acceptance</dt><dd class="fact__value"><?= e((string) ((int) $booking['rental_charge'] + (int) $booking['late_buffer'])) ?> pts</dd></div>
        <div class="fact"><dt class="fact__label">Declared item value</dt><dd class="fact__value"><?= e(number_format((int) $booking['declared_value'])) ?> pts</dd></div>
    </dl>
    <?php if (!empty($booking['message'])): ?>
        <p class="record-card__quote">“<?= e($booking['message']) ?>” — <?= e($booking['borrower_name']) ?></p>
    <?php endif; ?>
</section>

<?php if ($actions['accept'] || $actions['decline'] || $actions['cancel']): ?>
    <section class="panel">
        <h2 class="panel__title"><?= $booking['status'] === 'requested' && $role === 'lender' ? 'Your answer' : 'Change of plan' ?></h2>
        <div class="actions">
            <?php if ($actions['accept']): ?>
                <form method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/accept"
                    data-confirm="Accept this request? <?= e((string) ((int) $booking['rental_charge'] + (int) $booking['late_buffer'])) ?> points move from the borrower into escrow, and overlapping requests are declined." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Accept request</button>
                </form>
            <?php endif; ?>
            <?php if ($actions['decline']): ?>
                <form class="field-row" method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/decline" novalidate>
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="decline-reason">Reason (optional)</label>
                    <input class="input" type="text" id="decline-reason" name="reason" placeholder="Reason (optional)">
                    <button class="btn btn--ghost" type="submit">Decline</button>
                </form>
            <?php endif; ?>
            <?php if ($actions['cancel']): ?>
                <form method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/cancel"
                    data-confirm="Cancel this booking? <?= $booking['status'] === 'awaiting_handover' ? 'Everything held in escrow goes back to the borrower.' : 'No points have moved yet.' ?>" novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost" type="submit"><?= $booking['status'] === 'requested' ? 'Withdraw request' : 'Cancel booking' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($handover !== null): ?>
    <section class="panel" id="handover"<?= $handover['waiting'] ? ' data-handover-poll="' . e(base_url() . '/bookings/' . $id . '/handover-status') . '"' : '' ?>>
        <h2 class="panel__title">Handover</h2>
        <?php if ($handover['locked']): ?>
            <p class="notice notice--success">Both sides accepted on <?= e($handover['at']) ?>. These photos are the baseline the return is compared against.</p>
        <?php elseif ($handover['waiting']): ?>
            <p class="record-meta">
                Each of you photographs the item (1–5 photos) and notes its condition, then accepts.
                When both have accepted, the rental charge goes to the lender and the loan starts.
            </p>
        <?php endif; ?>

        <div class="two-col">
            <?php foreach ($handover['sides'] as $side): ?>
                <div class="stack">
                    <p class="line-item">
                        <span class="line-item__label"><?= e($side['label']) ?></span>
                        <span class="badge badge--<?= $side['accepted'] ? 'success' : 'neutral' ?>"><?= $side['accepted'] ? 'Accepted' : 'Not accepted yet' ?></span>
                    </p>
                    <?php if ($side['photos'] === []): ?>
                        <p class="record-meta">No photos yet.</p>
                    <?php else: ?>
                        <?php $gridPhotos = $side['photos']; include __DIR__ . '/../../partials/photo-grid.php'; ?>
                    <?php endif; ?>
                    <?php if ($side['note'] !== ''): ?>
                        <p class="panel__note"><?= e($side['note']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($handover['can_edit']): ?>
            <form class="stack" method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/handover/photos" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <label class="upload-drop">
                    <span class="upload-drop__glyph" aria-hidden="true">＋</span>
                    <span data-upload-name><?= $handover['sides'][$handover['me']]['photos'] === [] ? 'Add 1–5 photos of the item' : 'Replace your photos (1–5)' ?></span>
                    <input class="visually-hidden" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                </label>
                <div class="field">
                    <label class="field__label" for="handover-note">Condition note</label>
                    <input class="input" type="text" id="handover-note" name="note" value="<?= e($handover['sides'][$handover['me']]['note']) ?>" placeholder="Small scratch on the left side; battery fully charged…">
                </div>
                <div class="actions">
                    <button class="btn btn--ghost" type="submit">Save photos</button>
                </div>
            </form>
            <?php if ($handover['can_accept']): ?>
                <form method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/handover/accept"
                    data-confirm="Accept the handover? Your photos and note are locked once you accept." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Accept handover</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($return !== null): ?>
    <section class="panel" id="return">
        <h2 class="panel__title">Return</h2>
        <?php if ($return['decision'] !== ''): ?>
            <p class="notice notice--info"><?= e($return['decision']) ?> Returned <?= e($return['returned']) ?>.</p>
        <?php elseif ($return['returned'] !== ''): ?>
            <p class="record-meta">Return started <?= e($return['returned']) ?>. Compare it with the handover photos above.</p>
        <?php else: ?>
            <p class="record-meta">
                When the item comes back, each of you photographs it (1–5 photos). The lender then accepts the
                condition or raises a damage claim. Returned by the end of <?= e(date('j M Y', strtotime($booking['end_date']))) ?>,
                the <?= e((string) $booking['late_buffer']) ?>-point buffer goes back to the borrower.
            </p>
        <?php endif; ?>

        <div class="two-col">
            <?php foreach ($return['sides'] as $side): ?>
                <div class="stack">
                    <p class="field__label"><?= e($side['label']) ?></p>
                    <?php if ($side['photos'] === []): ?>
                        <p class="record-meta">No photos yet.</p>
                    <?php else: ?>
                        <?php $gridPhotos = $side['photos']; include __DIR__ . '/../../partials/photo-grid.php'; ?>
                    <?php endif; ?>
                    <?php if ($side['note'] !== ''): ?>
                        <p class="panel__note"><?= e($side['note']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($return['can_edit']): ?>
            <form class="stack" method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/return/photos" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <label class="upload-drop">
                    <span class="upload-drop__glyph" aria-hidden="true">＋</span>
                    <span data-upload-name><?= $return['sides'][$return['me']]['photos'] === [] ? 'Add 1–5 return photos' : 'Replace your return photos (1–5)' ?></span>
                    <input class="visually-hidden" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                </label>
                <div class="field">
                    <label class="field__label" for="return-note">Condition note</label>
                    <input class="input" type="text" id="return-note" name="note" value="<?= e($return['sides'][$return['me']]['note']) ?>">
                </div>
                <div class="actions">
                    <button class="btn btn--ghost" type="submit">Save return photos</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($return['can_accept'] || $return['can_claim']): ?>
            <div class="actions">
                <?php if ($return['can_accept']): ?>
                    <form method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/return/accept"
                        data-confirm="Accept the item’s condition? The booking completes and the buffer is settled. You cannot raise a claim afterwards." novalidate>
                        <?= csrf_field() ?>
                        <button class="btn btn--primary" type="submit">Accept return</button>
                    </form>
                <?php endif; ?>
                <?php if ($return['can_claim']): ?>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/bookings/<?= e($id) ?>?claim=1#damage-claim" data-modal-open="damage-claim">Raise a damage claim</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($return['can_claim']): ?>
        <?php include __DIR__ . '/../../partials/modal-damage-claim.php'; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($claim !== null): ?>
    <section class="panel" id="claim">
        <h2 class="panel__title">Damage claim</h2>
        <dl class="facts">
            <div class="fact"><dt class="fact__label">Severity</dt><dd class="fact__value"><?= e($claim['severity']) ?></dd></div>
            <div class="fact"><dt class="fact__label">Penalty asked</dt><dd class="fact__value"><?= e(number_format($claim['penalty'])) ?> pts</dd></div>
            <div class="fact"><dt class="fact__label">Track</dt><dd class="fact__value"><?= e($claim['track']) ?></dd></div>
            <div class="fact"><dt class="fact__label">Status</dt><dd class="fact__value"><?= e($claim['status']) ?></dd></div>
        </dl>
        <?php if ($claim['description'] !== ''): ?>
            <p class="record-card__quote">“<?= e($claim['description']) ?>”</p>
        <?php endif; ?>
        <?php if ($claim['photos'] !== []): ?>
            <?php $gridPhotos = $claim['photos']; include __DIR__ . '/../../partials/photo-grid.php'; ?>
        <?php endif; ?>

        <?php if ($claim['resolution'] !== null): ?>
            <p class="notice notice--info">
                <?= e(trim('Moderator ' . $claim['resolution']['moderator'])) ?> recorded a penalty of
                <?= e(number_format($claim['resolution']['penalty'])) ?> pts.
                <?= e($claim['resolution']['notes']) ?>
                Lender <?= $claim['resolution']['lender'] ? 'signed' : 'has not signed' ?> ·
                borrower <?= $claim['resolution']['borrower'] ? 'signed' : 'has not signed' ?>.
            </p>
        <?php endif; ?>

        <div class="actions">
            <?php if ($claim['can_answer']): ?>
                <form method="post" action="<?= base_url() ?>/damage-claims/<?= e((string) $claim['id']) ?>/accept"
                    data-confirm="Accept the claim? <?= e(number_format($claim['penalty'])) ?> pts move from your wallet to the lender." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Accept claim</button>
                </form>
                <form method="post" action="<?= base_url() ?>/damage-claims/<?= e((string) $claim['id']) ?>/contest"
                    data-confirm="Contest the claim? Your moderator will meet you both and decide." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost" type="submit">Contest</button>
                </form>
            <?php endif; ?>
            <?php if ($claim['can_withdraw']): ?>
                <form method="post" action="<?= base_url() ?>/damage-claims/<?= e((string) $claim['id']) ?>/withdraw"
                    data-confirm="Withdraw the claim? The return is accepted and the booking completes." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost" type="submit">Withdraw claim</button>
                </form>
            <?php endif; ?>
            <?php if ($claim['can_sign']): ?>
                <form method="post" action="<?= base_url() ?>/damage-claims/<?= e((string) $claim['id']) ?>/sign-off"
                    data-confirm="Sign the resolution? When both of you have signed, the recorded points move and the booking closes." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--primary" type="submit">I agree with this resolution</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($dispute !== null): ?>
    <section class="panel" id="dispute">
        <h2 class="panel__title">Dispute</h2>
        <?php if ($dispute['record'] !== null): ?>
            <?php $record = $dispute['record']; ?>
            <dl class="facts">
                <div class="fact"><dt class="fact__label">Raised by</dt><dd class="fact__value"><?= e($record['raised_by']) ?></dd></div>
                <div class="fact"><dt class="fact__label">Status</dt><dd class="fact__value"><?= e($record['status']) ?></dd></div>
            </dl>
            <p class="record-card__quote">“<?= e($record['reason']) ?>”</p>
            <?php if ($record['ruling'] !== ''): ?>
                <p class="notice notice--info"><?= e($record['ruling']) ?></p>
            <?php endif; ?>
            <?php if ($record['editable']): ?>
                <form class="field-row" method="post" action="<?= base_url() ?>/disputes/<?= e((string) $record['id']) ?>" novalidate>
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="dispute-edit">Reason</label>
                    <input class="input" type="text" id="dispute-edit" name="reason" value="<?= e($record['reason']) ?>">
                    <button class="btn btn--ghost" type="submit">Save reason</button>
                </form>
            <?php endif; ?>
            <?php if ($record['mine'] && $record['open']): ?>
                <form method="post" action="<?= base_url() ?>/disputes/<?= e((string) $record['id']) ?>/withdraw"
                    data-confirm="Withdraw the dispute? <?= $dispute['route'] === 'resolution' || $booking['status'] === 'escalated' ? 'The moderator’s resolution comes back for both signatures.' : 'The claim stands as accepted.' ?>" novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost" type="submit">Withdraw dispute</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($dispute['can_raise']): ?>
            <p class="record-meta">
                <?= $dispute['route'] === 'resolution'
                    ? 'Do not agree with the moderator’s resolution? Raise a dispute instead of signing; the Admin rules on it.'
                    : 'You accepted this claim. For 7 days afterwards you can still ask the Admin to review it.' ?>
            </p>
            <form class="field-row" method="post" action="<?= base_url() ?>/bookings/<?= e($id) ?>/disputes" novalidate>
                <?= csrf_field() ?>
                <label class="visually-hidden" for="dispute-reason">Why do you disagree?</label>
                <input class="input" type="text" id="dispute-reason" name="reason" placeholder="Why do you disagree?">
                <button class="btn btn--ghost" type="submit">Raise a dispute</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="actions">
    <a class="btn btn--ghost" href="<?= base_url() ?>/bookings?role=<?= e($role) ?>">Back to My Bookings</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/members/<?= e((string) ($role === 'borrower' ? $booking['lender_id'] : $booking['borrower_id'])) ?>">View <?= e($role === 'borrower' ? 'lender' : 'borrower') ?> profile</a>
</div>
<?php
$pageScripts = ['confirm.js', 'upload-name.js', 'polling.js', 'modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
