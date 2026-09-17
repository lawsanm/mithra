<?php

declare(strict_types=1);

/**
 * Approve write-off confirmation modal.
 *
 * @var array $writeoff  id, user_name, amount, reason, reserve_balance
 */

$writeoff ??= ['id' => '', 'user_name' => '', 'amount' => 0, 'reason' => '', 'reserve_balance' => 0];

?>
<dialog aria-labelledby="modal-approve-writeoff-title" class="modal modal--sm" id="modal-approve-writeoff">
    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        
        <div class="modal__head">
            <h2 class="modal__title" id="modal-approve-writeoff-title">Approve write-off</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
            </button>
        </div>

        <div class="line-item"><span class="line-item__label">Member</span><span class="line-item__value" data-modal-field="name"><?= e($writeoff['user_name']) ?></span></div>
        <div class="line-item"><span class="line-item__label">Amount</span><span class="line-item__value" data-modal-field="amount"><?= e(number_format($writeoff['amount'])) ?> pts</span></div>
        <div class="line-item"><span class="line-item__label">Reason</span><span class="line-item__value" data-modal-field="reason"><?= e($writeoff['reason']) ?></span></div>
        <div class="line-item"><span class="line-item__label">Reserve balance after</span><span class="line-item__value">Not calculated in this preview</span></div>

        <div class="notice notice--warning notice--full">
            This deducts the amount from the reserve pool and zeroes the member's outstanding debt. This action cannot be undone.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Approve write-off</button>
        </div>
    </div>
</dialog>
