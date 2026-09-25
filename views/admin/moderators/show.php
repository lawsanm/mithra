<?php

declare(strict_types=1);

/**
 * Individual moderator profile page.
 *
 * @var array $moderator     initials, name, division, status, status_label, appointed_at
 * @var array $bond          value, status, status_label
 * @var array $activityStats label, value pairs
 */

$pageTitle = $moderator['name'] . ' — Moderator';
$navActive = 'moderators';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/admin/moderators">Moderators</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current"><?= e($moderator['name']) ?></span>
</nav>

<header class="page-header">
    <h1 class="page-header__title"><?= e($moderator['name']) ?></h1>
    <span class="badge badge--<?= e($moderator['status']) ?>"><?= e($moderator['status_label']) ?></span>
</header>

<div class="form-card form-card--wide">
    <div class="two-col">
        <div style="display: flex; align-items: flex-start; gap: var(--space-5);">
            <span class="avatar" style="width: 64px; height: 64px; font-size: var(--text-h2);"><?= e($moderator['initials']) ?></span>
            <div>
                <h2 style="font-size: var(--text-lede); font-weight: var(--weight-semibold); margin-bottom: var(--space-2);"><?= e($moderator['name']) ?></h2>
                <p style="font-size: var(--text-ui-label); color: var(--color-text-muted); margin-bottom: var(--space-1);"><?= e($moderator['division']) ?> GN Division</p>
                <p style="font-size: var(--text-ui-label); color: var(--color-text-muted);">Appointed <?= e($moderator['appointed_at']) ?></p>
            </div>
        </div>
        <div class="bond-widget">
            <span class="bond-widget__label">Conduct bond</span>
            <div style="display: flex; align-items: center; gap: var(--space-3); margin: var(--space-2) 0;">
                <span class="bond-widget__value"><?= e(number_format($bond['value'])) ?> pts</span>
                <span class="badge badge--<?= e($bond['status']) ?>"><?= e($bond['status_label']) ?></span>
            </div>
        </div>
    </div>
</div>

<section class="section">
    <h2 class="section__title">Activity stats</h2>
    <div class="stat-grid stat-grid--3">
        <?php foreach ($activityStats as $stat): ?>
            <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<div class="form-card form-card--danger form-card--wide">
    <h3 class="form-card__legend u-text-error">Remove moderator</h3>

    <div class="notice notice--warning notice--full" style="margin-bottom: var(--space-5);">
        Removing a moderator returns their bond to the Sponsor Pool (good standing) or forfeits it to the Reserve Pool (removed for cause).
    </div>

    <details class="disclosure">
        <summary class="btn btn--ghost">Remove moderator…</summary>
        <div data-demo-form>
            <p class="demo-note">Preview only. Saving is not available yet.</p>
            <div class="field u-mb-4">
                <label class="field__label" for="removal-reason-type">Reason</label>
                <select class="input" id="removal-reason-type" name="reason_type" disabled>
                    <option value="">Select reason…</option>
                    <option value="good_standing">Good standing</option>
                    <option value="for_cause">Removed for cause</option>
                    <option value="voluntary">Voluntary resignation</option>
                </select>
            </div>

            <div class="field u-mb-4">
                <label class="field__label" for="removal-reason">Details</label>
                <textarea class="input" id="removal-reason" name="reason" rows="3" placeholder="Provide the reason for removal…" disabled></textarea>
            </div>

            <button class="btn btn--danger" type="submit" disabled>Remove moderator</button>
        </div>
    </details>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
