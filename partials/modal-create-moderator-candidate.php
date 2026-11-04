<?php

declare(strict_types=1);

/** Admin records a member's public nomination for the selected division. */

?>
<dialog aria-labelledby="modal-create-moderator-candidate-title" class="modal modal--sm" id="modal-create-moderator-candidate">
    <form action="<?= base_url() ?>/admin/moderators/candidates" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="division_id" value="<?= e((string) $selectedDivision) ?>">

        <div class="modal__head">
            <h2 class="modal__title" id="modal-create-moderator-candidate-title">Record a moderator nomination</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta u-mb-4">
            <?= e($division['name']) ?> · Select the nominated resident and the resident who submitted the nomination. Select the same person for a self-nomination.
        </p>

        <?php if ($candidates === []): ?>
            <p class="notice notice--info">There are no active home members in this division to nominate.</p>
        <?php else: ?>
            <div class="field">
                <label class="field__label" for="moderator-candidate-id">Nominee</label>
                <select class="input" id="moderator-candidate-id" name="candidate_id">
                    <option value="">Choose a nominee</option>
                    <?php foreach ($candidates as $member): ?>
                        <option value="<?= e((string) $member['id']) ?>"><?= e($member['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label" for="moderator-nominator-id">Nominated by</label>
                <select class="input" id="moderator-nominator-id" name="nominated_by">
                    <option value="">Choose a nominator</option>
                    <?php foreach ($candidates as $member): ?>
                        <option value="<?= e((string) $member['id']) ?>"><?= e($member['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal__footer">
                <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
                <button class="btn btn--primary" type="submit">Record nomination</button>
            </div>
        <?php endif; ?>
    </form>
</dialog>
