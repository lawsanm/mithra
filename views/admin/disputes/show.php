<?php

declare(strict_types=1);

/**
 * Dispute final ruling - admin reviews an escalated damage claim.
 
 *
 * @var array $dispute      case details and status
 * @var array $history      timeline events: text, date
 * @var array $evidence     labelled evidence groups with photo URLs and notes
 * @var int   $proposed_pts pre-filled award amount from moderator proposal
 * @var array $errors       field errors
 * @var array $draft        submitted form values
 */

$pageTitle = $dispute['title'] . ' — final ruling';
$navActive = 'disputes';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';
include __DIR__ . '/../../../partials/flash.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/admin/disputes">Disputes</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current"><?= e($dispute['title']) ?> · <?= e($dispute['case_number']) ?></span>
</nav>

<header class="page-header">
    <h1 class="page-header__title"><?= e($dispute['title']) ?> - final ruling</h1>
    <span class="badge badge--<?= e($dispute['status']) ?>">✕ <?= e($dispute['status_label']) ?></span>
</header>

<div class="two-col">
    <div class="stack" style="display:flex;flex-direction:column;gap:var(--space-6);">
        <div class="form-card form-card--wide">
            <h2 class="form-card__legend form-card__legend--lg">Case history</h2>
            <div class="timeline">
                <?php foreach ($history as $event): ?>
                    <div class="timeline__item">
                        <p class="timeline__title"><?= e($event['text']) ?></p>
                        <span class="timeline__date"><?= e($event['date']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-card form-card--wide">
            <h2 class="form-card__legend form-card__legend--lg">Case details</h2>
            <div class="facts">
                <div class="fact"><span class="fact__label">GN division</span><span class="fact__value"><?= e($dispute['division']) ?></span></div>
                <div class="fact"><span class="fact__label">Lender</span><span class="fact__value"><?= e($dispute['lender']) ?></span></div>
                <div class="fact"><span class="fact__label">Borrower</span><span class="fact__value"><?= e($dispute['borrower']) ?></span></div>
                <div class="fact"><span class="fact__label">Claim severity</span><span class="fact__value"><?= e($dispute['severity']) ?></span></div>
                <div class="fact"><span class="fact__label">Claim status</span><span class="fact__value"><?= e($dispute['claim_status']) ?></span></div>
            </div>
            <?php if ($dispute['claim_reason'] !== ''): ?>
                <p class="panel__note"><?= e($dispute['claim_reason']) ?></p>
            <?php endif; ?>
            <?php if ($dispute['moderator_name'] !== ''): ?>
                <h3 class="form-card__legend">Moderator record · <?= e($dispute['moderator_name']) ?></h3>
                <p class="panel__note">
                    <?= e($dispute['moderator_outcome'] === '' ? 'Outcome not recorded' : $dispute['moderator_outcome']) ?>
                    <?php if ($dispute['moderator_penalty'] !== null): ?>
                        · proposed <?= e(number_format($dispute['moderator_penalty'])) ?> pts
                    <?php endif; ?>
                </p>
                <?php if ($dispute['moderator_notes'] !== ''): ?>
                    <p><?= e($dispute['moderator_notes']) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="form-card form-card--wide">
            <h2 class="form-card__legend" style="font-size:var(--text-lede);color:var(--color-primary);">Evidence - handover vs return</h2>
            <?php $hasEvidence = false; ?>
            <?php foreach ($evidence as $group): ?>
                <?php if ($group['photos'] !== [] || $group['notes'] !== ''): ?>
                    <?php $hasEvidence = true; ?>
                    <div class="photo-group">
                        <span class="photo-group__label"><?= e($group['label']) ?></span>
                        <?php if ($group['notes'] !== ''): ?>
                            <p class="panel__note"><?= e($group['notes']) ?></p>
                        <?php endif; ?>
                        <?php if ($group['photos'] !== []): ?>
                            <div class="photo-grid">
                                <?php foreach ($group['photos'] as $photo): ?>
                                    <a href="<?= e($photo['url']) ?>" target="_blank" rel="noopener">
                                        <img class="thumb thumb--photo" src="<?= e($photo['url']) ?>" alt="<?= e($photo['alt']) ?>">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$hasEvidence): ?>
                <p class="panel__note">No handover, return, or claim evidence was recorded for this case.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if ($dispute['can_rule']): ?>
            <form class="form-card form-card--wide" method="post" action="<?= base_url() ?>/admin/disputes/<?= e((string) $dispute['id']) ?>" novalidate>
                <?= csrf_field() ?>
                <h2 class="form-card__legend form-card__legend--lg">Final decision</h2>
                <p class="panel__note">A zero-point ruling means the damage claim is not upheld. Any positive award is paid to the lender through the points ledger.</p>

                <div class="field">
                    <label class="field__label" for="award_pts">Award to lender (points)</label>
                    <input class="input" id="award_pts" name="award_pts" type="number" value="<?= e($draft['award_pts'] ?? (string) $proposed_pts) ?>" aria-invalid="<?= isset($errors['award_pts']) ? 'true' : 'false' ?>">
                    <?= field_error($errors, 'award_pts') ?>
                </div>

                <div class="field">
                    <label class="field__label" for="rationale">Ruling rationale</label>
                    <textarea class="input" id="rationale" name="rationale" rows="5" aria-invalid="<?= isset($errors['rationale']) ? 'true' : 'false' ?>" placeholder="Explain how the evidence supports your decision."><?= e($draft['rationale'] ?? '') ?></textarea>
                    <?= field_error($errors, 'rationale') ?>
                </div>

                <?= field_error($errors, 'form') ?>
                <div class="actions">
                    <button class="btn btn--primary" type="submit">Record final ruling</button>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/disputes">Return to disputes</a>
                </div>
            </form>
        <?php else: ?>
            <div class="form-card form-card--wide">
                <h2 class="form-card__legend form-card__legend--lg">Final decision</h2>
                <?php if ($dispute['ruling_at'] !== null): ?>
                    <p class="panel__note">Recorded <?= e($dispute['ruling_at']) ?> · <?= e(number_format((int) ($dispute['ruling_points'] ?? 0))) ?> points awarded to lender.</p>
                    <p><?= e($dispute['resolution']) ?></p>
                <?php else: ?>
                    <p class="panel__note">This case is not currently eligible for an Admin ruling. Check the linked damage claim’s status.</p>
                <?php endif; ?>
                <a class="btn btn--ghost" href="<?= base_url() ?>/admin/disputes">Return to disputes</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="notice notice--warning notice--full">
    Once recorded, the Admin ruling is final. Point movements and the ruling record are saved together; if settlement fails, nothing is saved.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
