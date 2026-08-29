<?php

declare(strict_types=1);

/**
 * Individual moderator profile page.
 *
 * @var array $moderator     initials, name, division, status, status_label, appointed_at
 * @var array $bond          value, status, status_label, deductions(array)
 * @var array $activityStats label, value pairs
 * @var array $appointHistory how_selected, appointed_by, objection_outcome, gn_endorsement
 * @var array $recentActivity timeline events: text, date
 */

$moderator ??= [
    'initials'     => 'KP',
    'name'         => 'Kamal Perera',
    'division'     => 'Kaduwela West',
    'status'       => 'success',
    'status_label' => 'Active',
    'appointed_at' => '15 Jan 2026',
];

$bond ??= [
    'value'        => 500,
    'status'       => 'success',
    'status_label' => 'Intact',
    'deductions'   => [],
];

$activityStats ??= [
    ['label' => 'Members verified',   'value' => '47'],
    ['label' => 'Listings approved',  'value' => '83'],
    ['label' => 'Aid grants vouched', 'value' => '12'],
    ['label' => 'Disputes mediated',  'value' => '8'],
    ['label' => 'Months active',      'value' => '6'],
    ['label' => 'Stipends received',  'value' => '6'],
];

$appointHistory ??= [
    'how_selected'      => 'Phase 2 — Admin appointed',
    'appointed_by'      => 'System Admin',
    'objection_outcome' => 'Passed — 0 objections',
    'gn_endorsement'    => 'Yes',
];

$recentActivity ??= [
    ['text' => 'Approved listing: Rice Cooker (1.8 L)',  'date' => '27 Jul 2026'],
    ['text' => 'Verified member: Dilani Jayasuriya',     'date' => '26 Jul 2026'],
    ['text' => 'Mediated dispute DC-0039 — resolved',    'date' => '24 Jul 2026'],
    ['text' => 'Approved aid grant AG-015',              'date' => '22 Jul 2026'],
    ['text' => 'Monthly stipend credited: 50 pts',       'date' => '1 Jul 2026'],
];

$pageTitle = $moderator['name'] . ' — Moderator';
$navActive = 'moderators';

include __DIR__ . '/../../../partials/header-admin.php';

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

<div class="form-card" style="width: 100%; max-width: 100%;">
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
            <details style="margin-top: var(--space-3);">
                <summary style="font-size: var(--text-ui-caption); color: var(--color-text-muted); cursor: pointer;">Bond deduction history</summary>
                <div style="margin-top: var(--space-3);">
                    <?php if ($bond['deductions'] === []): ?>
                        <p style="font-size: var(--text-ui-body); color: var(--color-text-muted);">No deductions recorded</p>
                    <?php else: ?>
                        <?php foreach ($bond['deductions'] as $ded): ?>
                            <div class="line-item">
                                <span class="line-item__label"><?= e($ded['reason']) ?></span>
                                <span class="line-item__value">−<?= e((string) $ded['amount']) ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </div>
</div>

<section class="section">
    <h2 class="section__title">Activity stats</h2>
    <div class="stat-grid stat-grid--3">
        <?php foreach ($activityStats as $stat): ?>
            <div class="stat-card">
                <span class="stat-card__label"><?= e($stat['label']) ?></span>
                <strong class="stat-card__value stat-card__value--primary"><?= e($stat['value']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="form-card" style="width: 100%; max-width: 100%;">
    <h3 class="form-card__legend">Appointment history</h3>
    <div class="line-item">
        <span class="line-item__label">How selected</span>
        <span class="line-item__value"><span class="badge badge--info"><?= e($appointHistory['how_selected']) ?></span></span>
    </div>
    <div class="line-item">
        <span class="line-item__label">Appointed by</span>
        <span class="line-item__value"><?= e($appointHistory['appointed_by']) ?></span>
    </div>
    <div class="line-item">
        <span class="line-item__label">Objection window</span>
        <span class="line-item__value"><span class="badge badge--success"><?= e($appointHistory['objection_outcome']) ?></span></span>
    </div>
    <div class="line-item">
        <span class="line-item__label">GN Officer endorsement</span>
        <span class="line-item__value"><span class="badge badge--success"><?= e($appointHistory['gn_endorsement']) ?></span></span>
    </div>
</div>

<section class="section">
    <h2 class="section__title">Recent activity</h2>
    <div class="timeline">
        <?php foreach ($recentActivity as $event): ?>
            <div class="timeline__item">
                <p class="timeline__title"><?= e($event['text']) ?></p>
                <span class="timeline__date"><?= e($event['date']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="form-card form-card--danger" style="width: 100%; max-width: 100%;">
    <h3 class="form-card__legend" style="color: var(--color-error);">Remove moderator</h3>

    <div class="notice notice--warning notice--full" style="margin-bottom: var(--space-5);">
        Removing a moderator returns their bond to the Sponsor Pool (good standing) or forfeits it to the Reserve Pool (removed for cause).
    </div>

    <div id="removal-section" style="display: none;">
        <form method="post" action="<?= base_url() ?>/admin/moderators/<?= e(rawurlencode($moderator['name'])) ?>/remove">
            <?= csrf_field() ?>

            <div class="field" style="margin-bottom: var(--space-4);">
                <label class="field__label" for="removal-reason-type">Reason</label>
                <select class="input" id="removal-reason-type" name="reason_type" required>
                    <option value="">Select reason…</option>
                    <option value="good_standing">Good standing</option>
                    <option value="for_cause">Removed for cause</option>
                    <option value="voluntary">Voluntary resignation</option>
                </select>
            </div>

            <div class="field" style="margin-bottom: var(--space-4);">
                <label class="field__label" for="removal-reason">Details</label>
                <textarea class="input" id="removal-reason" name="reason" rows="3" required placeholder="Provide the reason for removal…"></textarea>
            </div>

            <button class="btn btn--danger" type="submit">Remove moderator</button>
        </form>
    </div>

    <button class="btn btn--ghost" type="button" id="toggle-removal">Remove moderator…</button>
</div>

<script>
(function () {
    var btn = document.getElementById('toggle-removal');
    var section = document.getElementById('removal-section');
    if (!btn || !section) return;

    btn.addEventListener('click', function () {
        if (section.style.display === 'none') {
            section.style.display = 'block';
            btn.textContent = 'Cancel';
        } else {
            section.style.display = 'none';
            btn.textContent = 'Remove moderator…';
        }
    });
})();
</script>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
