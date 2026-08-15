<?php

declare(strict_types=1);

/**
 * "Send a gift" modal, shown here in its cap-exceeded validation state.
 * Figma: "Send a Gift — Modal (validation)" (75:101).
 *
 * Open with a trigger carrying data-modal-open="send-gift"; the host page must
 * also load /js/modal.js.
 *
 * @var array $recipients selectable members
 * @var array $giftDraft  recipient, amount, reason
 * @var array $giftErrors per-field messages from the Validator
 */

$recipients = $recipients ?? [];

$giftDraft = ($giftDraft ?? []) + [
    'recipient' => (string) ($recipients[0]['id'] ?? ''),
    'amount'    => '45',
    'reason'    => 'Thank you for the school run help!',
];

// Remaining daily allowance, so the cap line and any error reflect real usage
// rather than being written into the template.
$giftRemaining = $giftRemaining ?? Gift::DAILY_CAP;
$giftSentToday = $giftSentToday ?? 0;

/**
 * Per-field messages from the Validator. The Figma "validation" frame is this
 * modal with an amount over the cap — it renders when the amount really is
 * over, not as the default state.
 */
$giftErrors = $giftErrors ?? [];

if ($giftErrors === [] && (int) $giftDraft['amount'] > $giftRemaining) {
    $giftErrors['amount'] = sprintf(
        'Daily gift cap exceeded — you’ve sent %d of %d pts today. You can send up to %d pts more.',
        $giftSentToday,
        Gift::DAILY_CAP,
        $giftRemaining
    );
}

$giftReasonCount = sprintf(
    'Max 100 characters  ·  %d/100',
    mb_strlen((string) $giftDraft['reason'])
);

?>
<dialog class="modal modal--sm" id="send-gift" aria-labelledby="send-gift-title">
    <div class="modal__head">
        <h2 class="modal__title" id="send-gift-title">Send a gift</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <form class="stack" method="post" action="<?= base_url() ?>/gifts">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field__label" for="gift-recipient">Recipient</label>
            <select class="input" id="gift-recipient" name="recipient" required>
                <?php if ($giftDraft['recipient'] === ''): ?>
                    <option value="">Choose a member…</option>
                <?php endif; ?>
                <?php foreach ($recipients as $recipient): ?>
                    <option value="<?= e((string) $recipient['id']) ?>"
                        <?= $giftDraft['recipient'] === (string) $recipient['id'] ? ' selected' : '' ?>>
                        <?= e($recipient['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="gift-amount">Amount (pts)</label>
            <input
                class="input"
                type="number"
                id="gift-amount"
                name="amount"
                value="<?= e($giftDraft['amount']) ?>"
                min="1"
                step="1"
                required
                <?php if (isset($giftErrors['amount'])): ?>
                    aria-invalid="true" aria-describedby="gift-amount-error"
                <?php endif; ?>
            >
            <?php if (isset($giftErrors['amount'])): ?>
                <span class="field__error" id="gift-amount-error"><?= e($giftErrors['amount']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="gift-reason">Reason</label>
            <input
                class="input"
                type="text"
                id="gift-reason"
                name="reason"
                value="<?= e($giftDraft['reason']) ?>"
                maxlength="100"
                required
            >
            <span class="field__hint"><?= e($giftReasonCount) ?></span>
        </div>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Gifts are capped at 200 pts per sender per day and 2,000 pts per sender per
            year, and are blocked while you have a pending damage claim. Recipients can
            disable gifts in their settings.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" <?= $giftErrors !== [] ? 'disabled' : '' ?>>
                Send gift
            </button>
        </div>
    </form>
</dialog>
