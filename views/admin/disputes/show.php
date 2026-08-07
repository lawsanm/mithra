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

$dispute ??= [
    'title'        => 'Grinding Drill',
    'case_number'  => '#CD-0142',
    'status'       => 'error',
    'status_label' => 'Escalated - timer expired',
];

$history ??= [
    ['text' => 'Damage claimed by lender T.H.K. Madushan - Moderate, 60 pts', 'date' => '14 Jul'],
    ['text' => 'Mediation by Mod. J. Kavipriya - proposed 40 pts to lender',   'date' => '16 Jul'],
    ['text' => 'Moderator & lender signed off · borrower M. Lawsan refused',   'date' => '17 Jul'],
    ['text' => '7-day timer expired - escalated to Admin',                      'date' => '18 Jul'],
];

$evidence ??= [];

$proposed_pts ??= 40;

$pageTitle = $dispute['title'] . ' — final ruling';
$navActive = 'disputes';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/admin/disputes">Disputes</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current"><?= e($dispute['title']) ?> · <?= e($dispute['case_number']) ?></span>
</nav>

<header class="page-header">
    <h1 class="page-header__title"><?= e($dispute['title']) ?> - final ruling</h1>
    <span class="badge badge--<?= e($dispute['status']) ?>">✕ <?= e($dispute['status_label']) ?></span>
</header>

<div class="two-col">
    <div class="stack" style="display:flex;flex-direction:column;gap:var(--space-6);">
        <div class="form-card" style="width:100%;">
            <h2 class="form-card__legend" style="font-size:var(--text-lede);">Case history</h2>
            <div class="timeline">
                <?php foreach ($history as $event): ?>
                    <div class="timeline__item">
                        <p class="timeline__title"><?= e($event['text']) ?></p>
                        <span class="timeline__date"><?= e($event['date']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-card" style="width:100%;">
            <h2 class="form-card__legend" style="font-size:var(--text-lede);color:var(--color-primary);">Evidence - handover vs return</h2>
            <div class="thumb-grid">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="thumb-grid__img"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div>
        <form class="form-card" style="width:100%;" method="post" action="/admin/disputes/<?= e(ltrim($dispute['case_number'], '#CD-')) ?>/ruling">
            <?= csrf_field() ?>
            <h2 class="form-card__legend" style="font-size:var(--text-lede);">Final decision</h2>

            <div class="field">
                <label class="field__label" for="award_pts">Award to lender (pts)</label>
                <input class="input" id="award_pts" name="award_pts" type="number" min="0" value="<?= e((string) $proposed_pts) ?>">
            </div>

            <div class="field">
                <label class="field__label" for="rationale">Ruling rationale</label>
                <textarea class="input" id="rationale" name="rationale" rows="3" placeholder="Photos support moderate damage; moderator's proposal upheld..."></textarea>
            </div>

            <div class="actions">
                <button class="btn btn--primary" type="submit">Record final ruling</button>
            </div>
            <div class="actions">
                <a class="btn btn--ghost" href="/admin/disputes">Return to disputes</a>
            </div>
        </form>
    </div>
</div>

<div class="notice notice--warning notice--full">
    Final rulings are binding on all three parties, move escrow immediately, and are visible in the audit log.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
