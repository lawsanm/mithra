<?php

declare(strict_types=1);

/**
 * Moderator appointment flow — three-step process.
 *
 * Step 1: Select member from eligibility pool.
 * Step 2: Review criteria and confirm appointment.
 * Step 3: Objection window (7 days).
 *
 * @var array  $division    id, name
 * @var array  $candidates  initials, name, trust_score, member_since, transactions, months, record, gn_endorsed(bool), recommended(bool)
 * @var array  $selected    initials, name, division, trust_score, member_since (pre-filled if coming from index page)
 * @var array  $objections  initials, name, reason, date, status
 * @var int    $currentStep 1|2|3
 * @var string $countdown   remaining time text
 */

$division ??= ['id' => 1, 'name' => 'Kaduwela West'];

$candidates ??= [
    [
        'initials'     => 'PR',
        'name'         => 'Priya Rathnayake',
        'trust_score'  => 96,
        'member_since' => 'May 2025',
        'transactions' => 34,
        'months'       => 14,
        'record'       => 'Clean',
        'gn_endorsed'  => true,
        'recommended'  => true,
    ],
    [
        'initials'     => 'AB',
        'name'         => 'Amara Bandara',
        'trust_score'  => 91,
        'member_since' => 'Jul 2025',
        'transactions' => 28,
        'months'       => 12,
        'record'       => 'Clean',
        'gn_endorsed'  => true,
        'recommended'  => false,
    ],
    [
        'initials'     => 'CW',
        'name'         => 'Chathura Wijesinghe',
        'trust_score'  => 85,
        'member_since' => 'Sep 2025',
        'transactions' => 22,
        'months'       => 10,
        'record'       => 'Clean',
        'gn_endorsed'  => false,
        'recommended'  => false,
    ],
    [
        'initials'     => 'NP',
        'name'         => 'Nadeesha Peris',
        'trust_score'  => 78,
        'member_since' => 'Nov 2025',
        'transactions' => 15,
        'months'       => 8,
        'record'       => 'Clean',
        'gn_endorsed'  => false,
        'recommended'  => false,
    ],
];

$selected ??= null;

$objections ??= [
    [
        'initials' => 'SW',
        'name'     => 'Sandun Weerasinghe',
        'reason'   => 'Candidate runs a private lending circle which may conflict with platform duties.',
        'date'     => '24 Jul 2026',
        'status'   => 'pending',
    ],
];

$currentStep ??= 1;

$countdown ??= '6 days, 14 hours remaining';

$pageTitle = 'Appoint moderator';
$navActive = 'moderators';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/admin/moderators">Moderators</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current">Appoint moderator</span>
</nav>

<header class="page-header">
    <h1 class="page-header__title">Appoint moderator</h1>
    <p class="page-intro__meta"><?= e($division['name']) ?> GN Division</p>
</header>

<!-- Step indicator -->
<div class="step-indicator" aria-label="Appointment steps">
    <div class="step-indicator__step<?= $currentStep === 1 ? ' step-indicator__step--active' : ' step-indicator__step--complete' ?>" data-step="1">
        <span class="step-indicator__number">1</span>
        <span class="step-indicator__label">Select member</span>
    </div>
    <div class="step-indicator__connector"></div>
    <div class="step-indicator__step<?= $currentStep === 2 ? ' step-indicator__step--active' : '' ?>" data-step="2">
        <span class="step-indicator__number">2</span>
        <span class="step-indicator__label">Review appointment</span>
    </div>
    <div class="step-indicator__connector"></div>
    <div class="step-indicator__step" data-step="3">
        <span class="step-indicator__number">3</span>
        <span class="step-indicator__label">Objection window</span>
    </div>
</div>

<!-- ── Step 1: Select member ── -->
<div class="step-content" id="step-1" <?= $currentStep === 1 ? '' : 'hidden' ?>>
    <div class="notice notice--info notice--full">
        Select a member from the eligibility pool below. Members are sorted by trust score — the highest-scoring member is recommended.
    </div>

    <div class="field" style="margin-bottom: var(--space-5);">
        <label class="label" for="member-search">Search members</label>
        <input class="input" type="search" id="member-search" data-filter-list="candidate-list" placeholder="Search by name...">
    </div>

    <?php if ($candidates === []): ?><p class="notice notice--info">No eligible candidates in this division.</p><?php endif; ?>
    <ul class="row-list" id="candidate-list">
        <?php foreach ($candidates as $i => $candidate): ?>
            <li class="list-row<?= $candidate['recommended'] ? ' list-row--highlighted' : '' ?>" data-name="<?= e(strtolower($candidate['name'])) ?>">
                <span class="avatar"><?= e($candidate['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title">
                        <?= e($candidate['name']) ?>
                        <span class="badge badge--info" style="margin-left: var(--space-2);">Trust <?= e((string) $candidate['trust_score']) ?></span>
                        <?php if ($candidate['recommended']): ?>
                            <span class="badge badge--warning" style="margin-left: var(--space-2);">Recommended — highest trust score</span>
                        <?php endif; ?>
                    </span>
                    <span class="list-row__meta">Member since <?= e($candidate['member_since']) ?></span>
                    <div class="filter-pills" style="margin-top: var(--space-2);">
                        <span class="pill">Transactions: <?= e((string) $candidate['transactions']) ?></span>
                        <span class="pill">Months: <?= e((string) $candidate['months']) ?></span>
                        <span class="pill" style="<?= $candidate['record'] === 'Clean' ? 'background-color: var(--color-success-tint); color: var(--color-success-text); border-color: var(--color-success-tint);' : '' ?>">Record: <?= e($candidate['record']) ?></span>
                        <span class="pill" style="<?= $candidate['gn_endorsed'] ? 'background-color: var(--color-success-tint); color: var(--color-success-text); border-color: var(--color-success-tint);' : '' ?>">GN endorsed: <?= $candidate['gn_endorsed'] ? '✓' : '✕' ?></span>
                    </div>
                </div>
                <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $division['id']) ?>?member=<?= e((string) $candidate['id']) ?>">Select this member</a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- ── Step 2: Review appointment ── -->
<div class="step-content" id="step-2" <?= $currentStep === 2 ? '' : 'hidden' ?>>
    <div class="two-col">
        <div class="form-card" style="width: 100%;">
            <h2 class="form-card__legend">Selected member</h2>

            <div style="display: flex; align-items: center; gap: var(--space-4); margin-bottom: var(--space-5);">
                <span class="avatar avatar--lg" id="review-initials"><?= e((string) ($selected['initials'] ?? '')) ?></span>
                <div>
                    <strong id="review-name" style="font-size: var(--text-lede);"><?= e((string) ($selected['name'] ?? '')) ?></strong>
                    <p class="list-row__meta" style="margin: 0;">
                        <span id="review-division"><?= e($division['name']) ?></span> · Trust score <span id="review-trust"><?= e((string) ($selected['trust_score'] ?? '')) ?></span> · Member since <span id="review-since"><?= e((string) ($selected['member_since'] ?? '')) ?></span>
                    </p>
                </div>
            </div>

            <h3 style="font-size: var(--text-ui-label); font-weight: var(--weight-semibold); margin-bottom: var(--space-3);">Appointment criteria</h3>

            <div class="line-item">
                <span class="line-item__label">✓ Verified resident</span>
                <span class="badge badge--neutral">To verify</span>
            </div>
            <div class="line-item">
                <span class="line-item__label">✓ Platform capable</span>
                <span class="badge badge--neutral">To verify</span>
            </div>
            <div class="line-item">
                <span class="line-item__label">✓ No conflict of interest</span>
                <span class="badge badge--neutral">To verify</span>
            </div>
            <div class="line-item" id="review-gn-row">
                <span class="line-item__label" id="review-gn-label">GN Officer endorsement</span>
                <span class="badge" id="review-gn-badge">To verify</span>
            </div>
        </div>

        <div class="form-card" style="width: 100%;">
            <h2 class="form-card__legend">What happens next</h2>
            <div class="timeline">
                <div class="timeline__item">
                    <p class="timeline__title">Appointment announced</p>
                    <span class="timeline__date">All division members are notified</span>
                </div>
                <div class="timeline__item">
                    <p class="timeline__title">7-day objection window</p>
                    <span class="timeline__date">Any member can raise a concern</span>
                </div>
                <div class="timeline__item">
                    <p class="timeline__title">Appointment confirmed</p>
                    <span class="timeline__date">If no valid objections after 7 days</span>
                </div>
                <div class="timeline__item">
                    <p class="timeline__title">Conduct bond activated</p>
                    <span class="timeline__date">500-point bond deposited</span>
                </div>
            </div>
        </div>
    </div>

    <div class="notice notice--warning notice--full">
        After confirmation, the appointment is posted publicly for 7 days. If a resident raises a serious, specific concern, you will review it before the appointment is finalised.
    </div>

    <div style="display: flex; gap: var(--space-3); margin-top: var(--space-5);">
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $division['id']) ?>">Back to selection</a>
        <div style="display:inline;" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

            <button class="btn btn--primary" type="button" id="btn-confirm-appointment" disabled>Confirm and start objection window</button>
        </div>
    </div>
</div>

<!-- ── Step 3: Objection window ── -->
<div class="step-content" id="step-3" style="display: none;">
    <div class="notice notice--success notice--full">
        Appointment announced to all <?= e($division['name']) ?> members. 7-day objection window has started.
    </div>

    <div class="stat-grid stat-grid--3" style="margin-bottom: var(--space-6);">
        <div class="stat-card">
            <span class="stat-card__label">Time remaining</span>
            <strong class="stat-card__value">
                <span class="badge badge--warning" style="font-size: var(--text-ui-label);">⏱ <?= e($countdown) ?></span>
            </strong>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Objections raised</span>
            <strong class="stat-card__value"><?= e((string) count($objections)) ?></strong>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Status</span>
            <strong class="stat-card__value"><span class="badge badge--warning">In progress</span></strong>
        </div>
    </div>

    <section class="section">
        <h2 class="section__title">Objections</h2>

        <?php if ($objections === []): ?>
            <div class="empty-state">
                <p class="empty-state__title">No objections raised</p>
                <p class="empty-state__body">If no objections are raised during the 7-day window, the appointment is confirmed automatically.</p>
            </div>
        <?php else: ?>
            <ul class="row-list">
                <?php foreach ($objections as $obj): ?>
                    <li class="list-row">
                        <span class="avatar"><?= e($obj['initials']) ?></span>
                        <div class="list-row__body">
                            <span class="list-row__title"><?= e($obj['name']) ?></span>
                            <span class="list-row__meta"><?= e($obj['reason']) ?></span>
                            <span class="list-row__meta"><?= e($obj['date']) ?></span>
                        </div>
                        <div style="display:inline;" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
                            
                            <button class="btn btn--ghost" type="submit" disabled>Dismiss</button>
                        </div>
                        <div style="display:inline;" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
                            
                            <button class="btn btn--danger" type="submit" disabled>Uphold</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div style="margin-top: var(--space-6);">
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/objections/<?= e((string) $division['id']) ?>">View objection details</a>
    </div>
</div>



<?php $pageScripts = ['list-filter.js']; include __DIR__ . '/../../../partials/footer.php'; ?>
