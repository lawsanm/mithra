<?php

declare(strict_types=1);

/**
 * Request a temporary community. Figma: "Community — Temporary Request" (77:113).
 *
 * @var string $homeCommunity  the member's unchangeable home division
 * @var array  $divisions      selectable GN divisions
 * @var array  $draft          values entered so far
 * @var array  $errors         per-field messages from the Validator
 */

$pageTitle = 'Request a temporary community';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Request a temporary community</h1>

<p class="record-meta">
    Staying somewhere else for a while? Join that GN division temporarily so you can lend
    and borrow locally.
</p>

<div class="panel panel--wide" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
    <div class="field">
        <label class="field__label" for="home-community">Home community</label>
        <input class="input" type="text" id="home-community" value="<?= e($homeCommunity) ?>" readonly disabled>
    </div>

    <div class="field">
        <label class="field__label" for="temporary-community">Temporary community</label>
        <select class="input" id="temporary-community" name="temporary_community" disabled>
            <?php foreach ($divisions as $division): ?>
                <option value="<?= e($division) ?>"<?= $draft['temporary_community'] === $division ? ' selected' : '' ?>>
                    <?= e($division) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'temporary_community') ?>
    </div>

    <p class="form-card__legend">Proof of temporary stay</p>

    <label class="upload-drop">
        <span class="upload-drop__glyph" aria-hidden="true">＋</span>
        <span>Upload rental agreement, employer letter, or similar</span>
        <input class="visually-hidden" type="file" name="proof" accept="image/*,application/pdf" disabled>
    </label>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Temporary membership lasts 6 months and needs verification by
        the selected division's moderator. You keep full membership of your home community.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/settings">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Submit for verification</button>
    </div>
</div>

<section class="panel panel--wide">
    <h2 class="panel__title">Already staying there permanently?</h2>
    <div class="help-cta">
        <p class="help-cta__text">
            Once a temporary community is verified you can promote it to your home community.
        </p>
        <button class="btn btn--ghost" type="button" data-modal-open="community-promotion">
            Promote to home community…
        </button>
    </div>
</section>

<?php include __DIR__ . '/../../partials/modal-community-promotion.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
