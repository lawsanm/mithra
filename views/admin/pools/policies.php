<?php

declare(strict_types=1);

/**
 * Point Policies — admin view of earning, penalty, cap and pool rules.
 *
 * @var array $earning     earning rules: label, value
 * @var array $borrowing   borrowing & in-flight rules: label, value
 * @var array $penalties   penalty rules: label, value
 * @var array $caps        caps & safeguards: label, value
 */

$earning ??= [
    ['label' => 'New member grant',    'value' => '100 pts, funded from Sponsor Pool on GN validation'],
    ['label' => 'Completed lend',      'value' => '+5 pts bonus per on-time completed lend'],
    ['label' => 'Community aid task',   'value' => '+10–25 pts, moderator-approved'],
];

$borrowing ??= [
    ['label' => 'Booking cost',           'value' => 'Item day-rate × days, moved to In-Flight Pool at confirmation'],
    ['label' => 'In-Flight Pool release', 'value' => 'Returned to lender wallet on confirmed return'],
    ['label' => '48-hour rule',           'value' => 'Unconfirmed bookings auto-cancel, escrow refunded'],
];

$penalties ??= [
    ['label' => 'Late return',    'value' => '−10 pts per day, capped at 50% of booking cost'],
    ['label' => 'Damage ruling',  'value' => 'Deduction per moderator / admin final decision'],
    ['label' => 'No-show',        'value' => '−15 pts flat, logged against trust score'],
];

$caps ??= [
    ['label' => 'Wallet cap',        'value' => '1,000 pts max per member wallet'],
    ['label' => 'Single booking cap', 'value' => '200 pts max per booking'],
    ['label' => 'Negative balance',   'value' => 'Floor at −100 pts, then lending is frozen'],
];

$pageTitle = 'Point Policies';
$navActive = 'pools';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Point Policies</h1>
    <button class="btn btn--primary page-header__action" disabled title="Policy editing coming soon">Edit policies</button>
</header>
<p class="page-intro__meta">Rules that govern how points are earned, held and deducted · points are a closed-platform credit — never cash</p>

<div class="policy-grid">
    <div class="policy-card">
        <h2 class="policy-card__title">Earning</h2>
        <?php foreach ($earning as $row): ?>
            <div class="policy-row">
                <span class="policy-row__label"><?= e($row['label']) ?></span>
                <span class="policy-row__value"><?= e($row['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="policy-card">
        <h2 class="policy-card__title">Borrowing &amp; In-Flight Pool</h2>
        <?php foreach ($borrowing as $row): ?>
            <div class="policy-row">
                <span class="policy-row__label"><?= e($row['label']) ?></span>
                <span class="policy-row__value"><?= e($row['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="policy-card">
        <h2 class="policy-card__title">Penalties</h2>
        <?php foreach ($penalties as $row): ?>
            <div class="policy-row">
                <span class="policy-row__label"><?= e($row['label']) ?></span>
                <span class="policy-row__value"><?= e($row['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="policy-card">
        <h2 class="policy-card__title">Caps &amp; safeguards</h2>
        <?php foreach ($caps as $row): ?>
            <div class="policy-row">
                <span class="policy-row__label"><?= e($row['label']) ?></span>
                <span class="policy-row__value"><?= e($row['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="notice notice--warning notice--full">
    ★ Policy changes take effect at the next nightly invariant check and are recorded in the global ledger audit log. Existing bookings keep the policy they were created under.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
