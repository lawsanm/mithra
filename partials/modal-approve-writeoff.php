<?php

declare(strict_types=1);

/**
 * Approve write-off confirmation modal.
 *
 * @var array $writeoff  id, user_name, amount, reason, reserve_balance
 */

$writeoff ??= ['id' => '', 'user_name' => '', 'amount' => 0, 'reason' => '', 'reserve_balance' => 0];

?>
<dialog class="modal modal--sm" id="modal-approve-writeoff">
    <form method="post" action="/admin/pools/writeoffs/approve">
        <?= csrf_field() ?>
        <div class="modal__head">
            <h2 class="modal__title">Approve write-off</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
            </button>
        </div>

        <div class="line-item"><span class="line-item__label">Member</span><span class="line-item__value"><?= e($writeoff['user_name']) ?></span></div>
        <div class="line-item"><span class="line-item__label">Amount</span><span class="line-item__value"><?= e(number_format($writeoff['amount'])) ?> pts</span></div>
        <div class="line-item"><span class="line-item__label">Reason</span><span class="line-item__value"><?= e($writeoff['reason']) ?></span></div>
        <div class="line-item"><span class="line-item__label">Reserve balance after</span><span class="line-item__value"><?= e(number_format($writeoff['reserve_balance'] - $writeoff['amount'])) ?> pts</span></div>

        <div class="notice notice--warning notice--full">
            This deducts the amount from the reserve pool and zeroes the member's outstanding debt. This action cannot be undone.
        </div>

        <input type="hidden" name="id" value="<?= e((string) $writeoff['id']) ?>">

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Approve write-off</button>
        </div>
    </form>
</dialog>
