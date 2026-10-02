<?php

declare(strict_types=1);

/**
 * Temporary community. Figma: "Community — Temporary Request" (77:113).
 *
 * Shows the member's current temporary membership, if any, and what they can
 * do with it: withdraw a pending request, extend or leave an active one, or
 * promote it to home. Otherwise — or after a rejection — the request form.
 *
 * @var string      $homeCommunity the member's home division
 * @var array|null  $current       name, label, tone, line, reason, extending
 * @var string      $state         none | pending | active | paused | rejected | expired | deactivated
 * @var bool        $canApply      no temporary membership is open
 * @var bool        $showRejection a rejection from the last 7 days, with its reason
 * @var array       $divisions     selectable divisions: id, name
 * @var array       $proofTypes    value => label
 * @var array       $draft         division, proof_type
 * @var array       $errors        per-field messages
 * @var array       $promotion     lines for the promotion modal
 * @var array|null  $flash
 */

$pageTitle = 'Temporary community';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/profile">My profile</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Temporary community</span>
</nav>

<h1 class="detail__title">Temporary community</h1>

<p class="record-meta">
    Staying somewhere else for a while? Join that GN division temporarily so you can lend
    and borrow locally. Your home community stays <?= e($homeCommunity) ?>.
</p>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<?php // Refusals for fields this state does not show still need to reach the member. ?>
<?php $unshown = $canApply ? array_intersect_key($errors, ['form' => 1]) : array_diff_key($errors, ['renewal_proof' => 1]); ?>
<?php if ($unshown !== []): ?>
    <p class="notice notice--error"><?= e(implode(' ', $unshown)) ?></p>
<?php endif; ?>

<?php if ($current !== null && !$canApply): ?>
    <section class="panel panel--wide">
        <h2 class="panel__title">Your temporary community</h2>
        <p class="line-item">
            <span class="line-item__label"><?= e($current['name']) ?></span>
            <span class="badge badge--<?= e($current['tone']) ?>"><?= e($current['label']) ?></span>
        </p>
        <p class="record-meta"><?= e($current['line']) ?></p>

        <?php if ($current['reason'] !== ''): ?>
            <p class="notice notice--warning">Your last extension was not approved. Reason: <?= e($current['reason']) ?></p>
        <?php endif; ?>

        <?php if ($state === 'pending'): ?>
            <p class="notice notice--info">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
                The moderator of <?= e($current['name']) ?> is checking your proof of stay.
            </p>
            <form method="post" action="<?= base_url() ?>/community/temporary/leave" novalidate
                  data-confirm="Withdraw this request? You can apply again later.">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Withdraw request</button>
            </form>
        <?php else: ?>
            <?php if ($current['extending']): ?>
                <p class="notice notice--info">Your extension is with the moderator.</p>
            <?php else: ?>
                <form class="stack" method="post" action="<?= base_url() ?>/community/temporary/extend" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <p class="form-card__legend">Extend by <?= e((string) CommunityService::TERM_MONTHS) ?> months</p>
                    <label class="upload-drop">
                        <span class="upload-drop__glyph" aria-hidden="true">＋</span>
                        <span data-upload-name>Upload fresh proof of your stay</span>
                        <input class="visually-hidden" type="file" name="renewal_proof" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <?= field_error($errors, 'renewal_proof') ?>
                    <div class="actions">
                        <button class="btn btn--primary" type="submit">Request extension</button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="actions">
                <form method="post" action="<?= base_url() ?>/community/temporary/leave" novalidate
                      data-confirm="Leave <?= e($current['name']) ?>? Your listings there are paused. Bookings there must be finished first.">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost" type="submit">Leave community</button>
                </form>
                <?php if ($state === 'active'): ?>
                    <a class="btn btn--ghost" href="#community-promotion" data-modal-open="community-promotion">Promote to home community…</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($canApply): ?>
    <?php if ($showRejection): ?>
        <p class="notice notice--warning">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
            Your request for <?= e($current['name']) ?> was not approved. Reason: <?= e($current['reason']) ?>
            Send fresh proof below to resubmit.
        </p>
    <?php endif; ?>

    <form class="panel panel--wide" method="post" action="<?= base_url() ?>/community/temporary" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="field__label" for="home-community">Home community</label>
            <input class="input" type="text" id="home-community" value="<?= e($homeCommunity) ?>" readonly>
        </div>

        <div class="field">
            <label class="field__label" for="temporary-community">Temporary community</label>
            <select class="input" id="temporary-community" name="division">
                <option value="">Choose a GN division…</option>
                <?php foreach ($divisions as $division): ?>
                    <option value="<?= e((string) $division['id']) ?>"<?= $draft['division'] === (string) $division['id'] ? ' selected' : '' ?>>
                        <?= e($division['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'division') ?>
        </div>

        <div class="field">
            <label class="field__label" for="proof-type">Kind of proof</label>
            <select class="input" id="proof-type" name="proof_type">
                <option value="">Choose…</option>
                <?php foreach ($proofTypes as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $draft['proof_type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'proof_type') ?>
        </div>

        <p class="form-card__legend">Proof of temporary stay</p>

        <label class="upload-drop">
            <span class="upload-drop__glyph" aria-hidden="true">＋</span>
            <span data-upload-name>Upload a photo of your rental agreement, employer letter, or similar</span>
            <input class="visually-hidden" type="file" name="proof" accept="image/jpeg,image/png,image/webp">
        </label>
        <?= field_error($errors, 'proof') ?>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Temporary membership lasts <?= e((string) CommunityService::TERM_MONTHS) ?> months and needs verification by
            the selected division's moderator. You keep full membership of your home community.
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/profile">Cancel</a>
            <button class="btn btn--primary" type="submit">Submit for verification</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($state === 'active'): ?>
    <?php include __DIR__ . '/../../partials/modal-community-promotion.php'; ?>
<?php endif; ?>

<?php
$pageScripts = ['modal.js', 'confirm.js', 'upload-name.js'];
include __DIR__ . '/../../partials/footer.php';
?>
