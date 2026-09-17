<?php

declare(strict_types=1);

/**
 * Create new division modal — admin creates a GN division.
 *
 * @var array $verifiedMembers  options for seed moderator select
 */

$verifiedMembers ??= [];

?>
<dialog aria-labelledby="modal-create-division-title" class="modal modal--sm" id="modal-create-division">
    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        
        <div class="modal__head">
            <h2 class="modal__title" id="modal-create-division-title">Create new division</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta" style="margin-bottom:var(--space-4);">A division maps to one GN division. New members register against it and its moderator handles first-line disputes.</p>

        <div class="field-row">
            <div class="field" style="flex:2;">
                <label class="field__label" for="division_name">Division name</label>
                <input class="input" id="division_name" name="name" type="text" placeholder="e.g. Wellawatte South" required disabled>
            </div>
            <div class="field" style="flex:1;">
                <label class="field__label" for="gn_code">GN code</label>
                <input class="input" id="gn_code" name="gn_code" type="text" placeholder="e.g. 545B" disabled>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="district">District</label>
            <input class="input" id="district" name="district" type="text" placeholder="Colombo" disabled>
        </div>

        <div class="field">
            <label class="field__label" for="seed_moderator">Seed moderator (optional)</label>
            <select class="input" id="seed_moderator" name="seed_moderator_id" disabled>
                <option value="">Select a verified member...</option>
                <?php foreach ($verifiedMembers as $m): ?>
                    <option value="<?= e((string) $m['id']) ?>"><?= e($m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="notice notice--info notice--full">
            The division starts in "Pending" until its first 10 members are GN-validated. It will not appear in public search until active.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Create division</button>
        </div>
    </div>
</dialog>
