<?php

declare(strict_types=1);

/** Admin records the checks and structured evaluation for one nominee. */

?>
<dialog aria-labelledby="moderator-candidate-review-title-<?= e((string) $nomination['candidate_id']) ?>"
        class="modal modal--lg"
        id="modal-review-moderator-candidate-<?= e((string) $nomination['candidate_id']) ?>">
    <form action="<?= base_url() ?>/admin/moderators/candidates/<?= e((string) $nomination['candidate_id']) ?>/review" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="division_id" value="<?= e((string) $selectedDivision) ?>">

        <div class="modal__head">
            <h2 class="modal__title" id="moderator-candidate-review-title-<?= e((string) $nomination['candidate_id']) ?>">
                Review <?= e($nomination['name']) ?>
            </h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta u-mb-4">
            Review verified residency, independent platform use and the signed conflict declaration. Endorsement is preferred.
        </p>

        <div class="field">
            <label class="field__label" for="candidate-endorsement-<?= e((string) $nomination['candidate_id']) ?>">Optional endorsement — record the officer or organisation, date and how you verified it</label>
            <input class="input" id="candidate-endorsement-<?= e((string) $nomination['candidate_id']) ?>" name="endorsement" type="text" value="<?= e($nomination['endorsement']) ?>">
        </div>

        <div class="field">
            <strong>Registration evidence</strong>
            <?php foreach (['nic_photo_path' => 'NIC photograph', 'proof_file_path' => 'Proof of address'] as $key => $label): ?>
                <?php if (!empty($nomination[$key])): ?>
                    <a class="link" href="<?= e(photo_url($nomination[$key])) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
                <?php else: ?>
                    <span><?= e($label) ?>: no upload on file</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="field">
            <label class="field__label">Signed declaration reference and date
                <input class="input" name="declaration_reference" maxlength="255" value="<?= e($nomination['declaration_reference']) ?>">
            </label>
        </div>
        <div class="field">
            <label class="field__label">What did the resident do independently during registration?
                <textarea class="input" name="capability_note" maxlength="255"><?= e($nomination['capability_note']) ?></textarea>
            </label>
        </div>
        <?php if ($nomination['reviewed_at'] !== null): ?>
            <p>Last reviewed by <?= e($nomination['reviewer_name'] ?? 'Former administrator') ?> on <?= e($nomination['reviewed_at']) ?>.</p>
        <?php endif; ?>
        <fieldset class="field">
            <legend class="field__label">Required checks</legend>
            <ul class="checklist">
                <li>
                    <label class="checklist__label" for="candidate-nic-<?= e((string) $nomination['candidate_id']) ?>">
                        <input id="candidate-nic-<?= e((string) $nomination['candidate_id']) ?>" name="nic_verified" type="checkbox" value="1" <?= $nomination['nic_verified'] ? 'checked' : '' ?>>
                        Identity checked against NIC
                    </label>
                </li>
                <li>
                    <label class="checklist__label" for="candidate-residence-<?= e((string) $nomination['candidate_id']) ?>">
                        <input id="candidate-residence-<?= e((string) $nomination['candidate_id']) ?>" name="residence_verified" type="checkbox" value="1" <?= $nomination['residence_verified'] ? 'checked' : '' ?>>
                        Residence checked for this division
                    </label>
                </li>
                <li>
                    <label class="checklist__label" for="candidate-declaration-<?= e((string) $nomination['candidate_id']) ?>">
                        <input id="candidate-declaration-<?= e((string) $nomination['candidate_id']) ?>" name="declaration_signed" type="checkbox" value="1" <?= $nomination['declaration_signed'] ? 'checked' : '' ?>>
                        Signed declaration confirms no relevant personal or financial interests
                    </label>
                </li>
            </ul>
        </fieldset>

        <div class="field">
            <label for="capability-<?= e((string) $nomination['candidate_id']) ?>">
                <input type="checkbox" id="capability-<?= e((string) $nomination['candidate_id']) ?>" name="competency_score" value="100" <?= (int) $nomination['competency_score'] > 0 ? 'checked' : '' ?>>
                Demonstrated independent platform use during registration
            </label>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save candidate review</button>
        </div>
    </form>
    <form class="modal__content" method="post" action="<?= base_url() ?>/admin/moderators/candidates/<?= e((string) $nomination['candidate_id']) ?>/withdraw" novalidate>
        <?= csrf_field() ?>
        <details class="disclosure"><summary>Withdraw this nomination</summary><p>The nomination and its review will be retained. It will no longer be considered for appointment.</p><button class="btn btn--danger" type="submit">Withdraw nomination</button></details>
    </form>
</dialog>
