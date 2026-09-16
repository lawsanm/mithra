<?php

declare(strict_types=1);

/**
 * Point Policies — the rules that move points, grouped by area.
 *
 * @var array<string, list<array{label: string, value: string}>> $groups group title => rules
 */

$pageTitle = 'Point Policies';
$navActive = 'pools';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Point Policies</h1>
    <button class="btn btn--primary page-header__action" disabled title="Policy editing coming soon">Edit policies</button>
</header>
<p class="page-intro__meta">Rules that govern how points are earned, held and deducted · points are a closed-platform credit — never cash</p>

<div class="policy-grid">
    <?php foreach ($groups as $title => $rules): ?>
        <div class="policy-card">
            <h2 class="policy-card__title"><?= e($title) ?></h2>
            <?php foreach ($rules as $rule): ?>
                <div class="policy-row">
                    <span class="policy-row__label"><?= e($rule['label']) ?></span>
                    <span class="policy-row__value"><?= e($rule['value']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="notice notice--warning notice--full">
    ★ Policy changes take effect at the next nightly invariant check and are recorded in the global ledger audit log. Existing bookings keep the policy they were created under.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
