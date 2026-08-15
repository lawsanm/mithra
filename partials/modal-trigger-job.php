<?php

declare(strict_types=1);

/**
 * Trigger cron job confirmation modal.
 *
 * @var array $triggerJob  name, description, last_run
 */

$triggerJob ??= ['name' => '', 'description' => '', 'last_run' => ''];

?>
<dialog class="modal modal--sm" id="modal-trigger-job">
    <form method="post" action="<?= base_url() ?>/admin/cron/trigger">
        <?= csrf_field() ?>
        <div class="modal__head">
            <h2 class="modal__title">Trigger job manually</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
            </button>
        </div>

        <div class="line-item"><span class="line-item__label">Job</span><span class="line-item__value"><?= e($triggerJob['name']) ?></span></div>
        <div class="line-item"><span class="line-item__label">Last run</span><span class="line-item__value"><?= e($triggerJob['last_run']) ?></span></div>
        <p style="margin-top: var(--space-3);"><?= e($triggerJob['description']) ?></p>

        <div class="notice notice--warning notice--full">
            Running a job manually executes it outside its normal schedule. Results will appear in the cron log.
        </div>

        <input type="hidden" name="job_name" value="<?= e($triggerJob['name']) ?>">

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Run now</button>
        </div>
    </form>
</dialog>
