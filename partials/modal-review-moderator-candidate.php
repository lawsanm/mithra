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
            Confirm each document and reference in person. The two references must be from different households. Score each area from 0 to 100; the weighted total is calculated when all checks and scores are complete.
        </p>

        <div class="field">
            <label class="field__label" for="candidate-endorsement-<?= e((string) $nomination['candidate_id']) ?>">GN Officer or community organisation endorsement</label>
            <input class="input" id="candidate-endorsement-<?= e((string) $nomination['candidate_id']) ?>" name="endorsement" type="text" value="<?= e($nomination['endorsement']) ?>">
        </div>

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
            <label class="field__label" for="candidate-reference-one-<?= e((string) $nomination['candidate_id']) ?>">First character reference</label>
            <input class="input" id="candidate-reference-one-<?= e((string) $nomination['candidate_id']) ?>" name="reference_1" type="text" value="<?= e($nomination['reference_1']) ?>">
        </div>

        <div class="field">
            <label class="field__label" for="candidate-reference-two-<?= e((string) $nomination['candidate_id']) ?>">Second character reference, from another household</label>
            <input class="input" id="candidate-reference-two-<?= e((string) $nomination['candidate_id']) ?>" name="reference_2" type="text" value="<?= e($nomination['reference_2']) ?>">
        </div>

        <fieldset class="field">
            <legend class="field__label">Structured evaluation scores</legend>
            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="candidate-interview-<?= e((string) $nomination['candidate_id']) ?>">Interview · 35%</label>
                    <input class="input" id="candidate-interview-<?= e((string) $nomination['candidate_id']) ?>" name="interview_score" type="number" inputmode="numeric" value="<?= e($nomination['interview_score']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="candidate-involvement-<?= e((string) $nomination['candidate_id']) ?>">Community involvement · 35%</label>
                    <input class="input" id="candidate-involvement-<?= e((string) $nomination['candidate_id']) ?>" name="involvement_score" type="number" inputmode="numeric" value="<?= e($nomination['involvement_score']) ?>">
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="candidate-references-score-<?= e((string) $nomination['candidate_id']) ?>">References · 20%</label>
                    <input class="input" id="candidate-references-score-<?= e((string) $nomination['candidate_id']) ?>" name="references_score" type="number" inputmode="numeric" value="<?= e($nomination['references_score']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="candidate-competency-<?= e((string) $nomination['candidate_id']) ?>">Smartphone competency · 10%</label>
                    <input class="input" id="candidate-competency-<?= e((string) $nomination['candidate_id']) ?>" name="competency_score" type="number" inputmode="numeric" value="<?= e($nomination['competency_score']) ?>">
                </div>
            </div>
        </fieldset>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save candidate review</button>
        </div>
    </form>
</dialog>
