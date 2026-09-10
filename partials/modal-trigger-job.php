<?php

declare(strict_types=1);

/**
 * Trigger cron job confirmation modal.
 *
 * @var array $triggerJob  name, description, last_run
 */

$triggerJob ??= ['name' => '', 'description' => '', 'last_run' => ''];

?>
<dialog aria-labelledby="modal-trigger-job-title" class="modal modal--sm" id="modal-trigger-job">
    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        
        <div class="modal__head">
            <h2 class="modal__title" id="modal-trigger-job-title">Trigger job manually</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
            </button>
        </div>

        <div class="line-item"><span class="line-item__label">Job</span><span class="line-item__value" data-modal-field="job"><?= e($triggerJob['name']) ?></span></div>
        <div class="line-item"><span class="line-item__label">Last run</span><span class="line-item__value" data-modal-field="lastRun"><?= e($triggerJob['last_run']) ?></span></div>
        <p data-modal-field="description" style="margin-top: var(--space-3);"><?= e($triggerJob['description']) ?></p>

        <div class="notice notice--warning notice--full">
            Running a job manually executes it outside its normal schedule. Results will appear in the cron log.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Run now</button>
        </div>
    </div>
</dialog>
