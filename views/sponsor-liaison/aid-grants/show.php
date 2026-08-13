<?php

declare(strict_types=1);

/**
 * Aid grant — approval detail. Figma "Aid Grant — Approval Detail" (382:170).
 *
 * @var array $grant  initials, name, grant_number, status, status_label, meta
 * @var array $request purpose, amount_requested, pool_balance, prior_grants, note
 * @var array $vouch   initials, name, date, note
 * @var array $draft   approved_amount, reason
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$grant ??= [
    'id'           => 1,
    'initials'     => 'ML',
    'name'         => 'M. Lawsan',
    'grant_number' => '#A-1042',
    'status'       => 'info',
    'status_label' => 'Awaiting your approval',
    'meta'         => 'Grant #A-1042  ·  requested 10 Jul  ·  Kollupitiya  ·  Trust 96',
];

$request ??= [
    'purpose'          => 'School supplies',
    'amount_requested' => '300 pts',
    'pool_balance'      => '12,750 pts',
    'prior_grants'      => '1 (closed Jun 2026)',
    'note'              => 'Two children starting the new term, need books and shoes. Evidence attached — school letters for both.',
];

$vouch ??= [
    'initials' => 'JK',
    'name'     => 'Vouched by Moderator J. Kavipriya  ·  12 Jul',
    'note'     => 'Known family, genuine need. No conflict of interest declared.',
];

$draft ??= [
    'approved_amount' => '300',
    'reason'          => '',
];

$pageTitle = $grant['name'] . ' — aid grant review';
$navActive = 'aid-grants';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/sponsor-liaison/aid-grants">Aid Grants</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($grant['name']) ?> · <?= e($grant['grant_number']) ?></span>
</nav>

<div class="content-stretch" style="display: flex; gap: var(--space-3); align-items: center;">
    <span class="avatar avatar--lg"><?= e($grant['initials']) ?></span>
    <div>
        <div style="display: flex; align-items: center; gap: var(--space-3);">
            <h1 class="detail__title"><?= e($grant['name']) ?></h1>
            <span class="badge badge--<?= e($grant['status']) ?>"><?= e($grant['status_label']) ?></span>
        </div>
        <p class="page-intro__meta"><?= e($grant['meta']) ?></p>
    </div>
</div>

<div class="two-col">
    <div class="stack" style="display: flex; flex-direction: column; gap: var(--space-5);">
        <div class="form-card" style="width: 100%;">
            <h2 class="form-card__legend" style="font-size: var(--text-lede);">Request</h2>
            <div style="display: flex; gap: var(--space-10); flex-wrap: wrap;">
                <div>
                    <p class="stat-card__note" style="margin-bottom: 3px;">Purpose</p>
                    <p class="list-row__title"><?= e($request['purpose']) ?></p>
                </div>
                <div>
                    <p class="stat-card__note" style="margin-bottom: 3px;">Amount requested</p>
                    <p class="list-row__title"><?= e($request['amount_requested']) ?></p>
                </div>
                <div>
                    <p class="stat-card__note" style="margin-bottom: 3px;">Aid Pool balance</p>
                    <p class="list-row__title"><?= e($request['pool_balance']) ?></p>
                </div>
                <div>
                    <p class="stat-card__note" style="margin-bottom: 3px;">Prior grants</p>
                    <p class="list-row__title"><?= e($request['prior_grants']) ?></p>
                </div>
            </div>
            <p class="page-intro__meta">“<?= e($request['note']) ?>”</p>
        </div>

        <div class="form-card" style="width: 100%;">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <span class="avatar avatar--md"><?= e($vouch['initials']) ?></span>
                <div>
                    <p class="list-row__title"><?= e($vouch['name']) ?></p>
                    <p class="list-row__meta">“<?= e($vouch['note']) ?>”</p>
                </div>
            </div>
            <span class="badge badge--success">Vouch complete</span>
        </div>
    </div>

    <div>
        <form class="form-card" style="width: 100%;" method="post" action="/sponsor-liaison/aid-grants/<?= e((string) $grant['id']) ?>/decision">
            <?= csrf_field() ?>
            <h2 class="form-card__legend" style="font-size: var(--text-lede);">Your decision</h2>

            <div class="field">
                <label class="field__label" for="approved-amount">Approved amount (adjustable)</label>
                <input class="input" type="number" id="approved-amount" name="approved_amount" value="<?= e($draft['approved_amount']) ?>" min="0" step="1">
                <span class="field__hint">You may approve a different amount than requested.</span>
            </div>

            <div class="field">
                <label class="field__label" for="reason">Reason (required if rejecting)</label>
                <textarea class="input" id="reason" name="reason" rows="2" placeholder="Notes for the member and moderator…"><?= e($draft['reason']) ?></textarea>
            </div>

            <div class="actions">
                <button class="btn btn--primary" type="submit" name="decision" value="approve">Approve grant</button>
            </div>
            <div class="actions">
                <button class="btn btn--ghost" type="submit" name="decision" value="reject">Reject with reason</button>
                <button class="btn btn--ghost" type="submit" name="decision" value="more_info">Request more info</button>
            </div>
        </form>
    </div>
</div>

<div class="notice notice--info notice--full">
    Approved grants draw from the Aid Pool and appear on the Transparency Dashboard. The member is notified quietly, neighbours never see it was a grant.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
