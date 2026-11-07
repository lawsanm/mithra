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
 * @var array  $candidates  eligible candidate rows sorted by proposal ranking
 * @var array  $selected    initials, name, division, trust_score, member_since (pre-filled if coming from index page)
 * @var array  $objections  initials, name, reason, date, status
 * @var int    $currentStep 1|2|3
 * @var string $countdown   remaining time text
 * @var bool   $canAppoint  whether this division currently accepts an appointment
 * @var bool   $isLaunch    whether this is the division's first appointment
 */

$pageTitle = 'Appoint moderator';
$navActive = 'moderators';
$errors = $errors ?? [];
$values = $values ?? [];

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

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
<?php foreach ($errors as $field => $error): ?><?php if ($field !== 'acknowledged'): ?><p class="notice notice--error"><?= e($error) ?></p><?php endif; ?><?php endforeach; ?>

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
        <?php if ($isLaunch): ?>
            Choose the approved resident who best meets the bootstrap criteria. GN endorsement is preferred.
        <?php else: ?>
            Select the highest-trust member who meets the normal-operation eligibility criteria.
        <?php endif; ?>
    </div>

    <?php if (!$canAppoint): ?>
        <div class="notice notice--warning notice--full">
            <?php if ($activeModerator !== null): ?>
                <?= e((string) $activeModerator) ?> currently holds this division's moderator appointment. A replacement can be selected after that appointment ends.
            <?php else: ?>
                This division is not currently available for appointment. Confirm it is active and, for a first appointment, has at least 10 active members.
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
                        <span class="badge badge--info u-ml-2">Trust <?= e((string) $candidate['trust_score']) ?></span>
                        <?php if (!$isLaunch && $candidate['recommended']): ?>
                            <span class="badge badge--warning u-ml-2"><?= $isLaunch ? 'Recommended — highest weighted score' : 'Recommended — highest trust score' ?></span>
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
                <?php if (($isLaunch || $candidate['recommended']) && $canAppoint): ?>
                    <a class="btn btn--primary" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $division['id']) ?>?member=<?= e((string) $candidate['id']) ?>">Review member</a>
                <?php else: ?>
                    <span class="badge badge--neutral"><?= $canAppoint ? 'Lower-ranked candidate' : 'Not available' ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- ── Step 2: Review appointment ── -->
<div class="step-content" id="step-2" <?= $currentStep === 2 ? '' : 'hidden' ?>>
    <div class="two-col">
        <div class="form-card form-card--wide">
            <h2 class="form-card__legend">Selected member</h2>

            <div style="display: flex; align-items: center; gap: var(--space-4); margin-bottom: var(--space-5);">
                <span class="avatar avatar--lg" id="review-initials"><?= e((string) ($selected['initials'] ?? '')) ?></span>
                <div>
                    <strong id="review-name" class="u-text-lede"><?= e((string) ($selected['name'] ?? '')) ?></strong>
                    <p class="list-row__meta" style="margin: 0;">
                        <span id="review-division"><?= e($division['name']) ?></span> · Trust score <span id="review-trust"><?= e((string) ($selected['trust_score'] ?? '')) ?></span> · Member since <span id="review-since"><?= e((string) ($selected['member_since'] ?? '')) ?></span>
                    </p>
                </div>
            </div>

            <h3 style="font-size: var(--text-ui-label); font-weight: var(--weight-semibold); margin-bottom: var(--space-3);">Appointment criteria</h3>

            <div class="line-item">
                <span class="line-item__label">Verified identity and residence</span>
                <span class="badge badge--success">Recorded</span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Platform competency</span>
                <span class="badge badge--success">Demonstrated</span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Conflict declaration</span>
                <span class="badge badge--success">Signed</span>
            </div>
            <div class="line-item" id="review-gn-row">
                <span class="line-item__label" id="review-gn-label">GN Officer endorsement</span>
                <span class="badge badge--neutral" id="review-gn-badge"><?= !empty($selected['gn_endorsed']) ? 'Recorded' : 'Not recorded' ?></span>
            </div>
            <?php if ($isLaunch): ?>
                <div class="line-item">
                    <span class="line-item__label">Bootstrap checks</span>
                    <strong>Complete</strong>
                </div>
            <?php else: ?>
                <div class="line-item">
                    <span class="line-item__label">Completed transactions · membership</span>
                    <strong><?= e((string) ($selected['transactions'] ?? '')) ?> · <?= e((string) ($selected['months'] ?? '')) ?> months</strong>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-card form-card--wide">
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
                    <p class="timeline__title">Objections reviewed</p>
                    <span class="timeline__date">Admin reviews any concerns before confirmation</span>
                </div>
                <div class="timeline__item">
                    <p class="timeline__title">Three-month trial and conduct bond</p>
                    <span class="timeline__date">Both start on appointment day; 500 points are held</span>
                </div>
            </div>
        </div>
    </div>

    <div class="notice notice--warning notice--full">
        Appointing starts the 7-day public objection window and the three-month trial. The 500-point conduct bond is held from day one.
    </div>

    <div class="actions" style="margin-top: var(--space-5);">
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $division['id']) ?>">Back to selection</a>
        <?php if ($canAppoint && $selected !== null): ?>
            <form method="post" action="<?= base_url() ?>/admin/moderators/appoint" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="division_id" value="<?= e((string) $division['id']) ?>">
                <input type="hidden" name="candidate_user_id" value="<?= e((string) $selected['id']) ?>">
                <div class="field"><label class="field__label" for="appointment-acknowledged"><input type="checkbox" id="appointment-acknowledged" name="acknowledged" value="1" <?= ($values['acknowledged'] ?? '') === '1' ? 'checked' : '' ?>> I have verified the candidate’s signed acknowledgement that misconduct leads to removal, bond forfeiture and a permanent conduct record.</label><?php if (isset($errors['acknowledged'])): ?><p class="field__error"><?= e($errors['acknowledged']) ?></p><?php endif; ?></div>
                <button class="btn btn--primary" type="submit">Appoint and start objection window</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php $pageScripts = ['list-filter.js']; include __DIR__ . '/../../../partials/footer.php'; ?>
