<?php

declare(strict_types=1);

/**
 * Dispute final ruling - admin reviews an escalated damage claim.
 
 *
 * @var array $dispute      title, case_number, status, status_label
 * @var array $history      timeline events: text, date
 * @var array $evidence     photo URLs (handover vs return)
 * @var int   $proposed_pts pre-filled award amount from moderator proposal
 */

$pageTitle = $dispute['title'] . ' — final ruling';
$navActive = 'disputes';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

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
            <h2 class="form-card__legend" style="font-size:var(--text-lede);color:var(--color-primary);">Evidence - handover vs return</h2>
            <div class="thumb-grid">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="thumb-grid__img"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="form-card form-card--wide" data-demo-form>
            <p class="demo-note">Preview only. Saving is not available yet.</p>
            <h2 class="form-card__legend form-card__legend--lg">Final decision</h2>

            <div class="field">
                <label class="field__label" for="award_pts">Award to lender (pts)</label>
                <input class="input" id="award_pts" name="award_pts" type="number" value="<?= e((string) $proposed_pts) ?>" disabled>
            </div>

            <div class="field">
                <label class="field__label" for="rationale">Ruling rationale</label>
                <textarea class="input" id="rationale" name="rationale" rows="3" placeholder="Photos support moderate damage; moderator's proposal upheld..." disabled></textarea>
            </div>

            <div class="actions">
                <button class="btn btn--primary" type="submit" disabled>Record final ruling</button>
            </div>
            <div class="actions">
                <a class="btn btn--ghost" href="<?= base_url() ?>/admin/disputes">Return to disputes</a>
            </div>
        </div>
    </div>
</div>

<div class="notice notice--warning notice--full">
    Final rulings are binding on all three parties, move escrow immediately, and are visible in the audit log.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
