<?php

declare(strict_types=1);

/**
 * "Send a gift" modal. Figma: "Send a Gift — Modal" and its validation state
 * (75:101), which renders when the server refused the amount.
 *
 * Open with a trigger carrying data-modal-open="send-gift"; the host page must
 * also load /js/modal.js. Without JavaScript the trigger links to
 * /gifts?to=… and the dialog renders open.
 *
 * @var array $recipients    members who may receive: id, full_name
 * @var array $giftDraft     recipient, amount, reason as typed
 * @var array $giftErrors    per-field messages from the Validator or GiftService
 * @var int   $giftRemaining points still allowed today
 * @var bool  $giftOpen      render it open
 */

$recipients = $recipients ?? [];
$giftErrors = $giftErrors ?? [];

$giftDraft = ($giftDraft ?? []) + ['recipient' => '', 'amount' => '', 'reason' => ''];

$giftRemaining = $giftRemaining ?? Gift::DAILY_CAP;

?>
<dialog class="modal modal--sm" id="send-gift" aria-labelledby="send-gift-title"<?= !empty($giftOpen) ? ' open' : '' ?>>
    <div class="modal__head">
        <h2 class="modal__title" id="send-gift-title">Send a gift</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <form class="stack" method="post" action="<?= base_url() ?>/gifts" novalidate>
        <?= csrf_field() ?>
        <?= field_error($giftErrors, 'form') ?>

        <div class="field">
            <label class="field__label" for="gift-recipient">Recipient</label>
            <select class="input" id="gift-recipient" name="recipient">
                <option value="">Choose a member…</option>
                <?php foreach ($recipients as $recipient): ?>
                    <option value="<?= e((string) $recipient['id']) ?>"<?= (string) $giftDraft['recipient'] === (string) $recipient['id'] ? ' selected' : '' ?>>
                        <?= e($recipient['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($giftErrors, 'recipient') ?>
        </div>

        <div class="field">
            <label class="field__label" for="gift-amount">Amount (pts)</label>
            <input class="input" type="number" id="gift-amount" name="amount" value="<?= e((string) $giftDraft['amount']) ?>"
                <?= isset($giftErrors['amount']) ? 'aria-invalid="true" aria-describedby="gift-amount-error"' : '' ?>>
            <?php if (isset($giftErrors['amount'])): ?>
                <span class="field__error" id="gift-amount-error"><?= e($giftErrors['amount']) ?></span>
            <?php else: ?>
                <span class="field__hint">You can send up to <?= e((string) $giftRemaining) ?> pts more today.</span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="gift-reason">Reason</label>
            <input class="input" type="text" id="gift-reason" name="reason" value="<?= e((string) $giftDraft['reason']) ?>">
            <?= field_error($giftErrors, 'reason') ?>
            <span class="field__hint">Up to <?= e((string) GiftService::REASON_MAX) ?> characters.</span>
        </div>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Gifts are capped at <?= e((string) Gift::DAILY_CAP) ?> pts a day and <?= e(number_format(Gift::ANNUAL_CAP)) ?> pts a year,
            go only to members of your GN division, and cannot be undone. They are paused while you
            have a pending damage claim, an open dispute or an overdue return.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Send gift</button>
        </div>
    </form>
</dialog>
