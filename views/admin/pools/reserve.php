<?php

declare(strict_types=1);

/**
 * Reserve Pool — the safety net that keeps every member balance at zero or
 * above (Plan §7.7).
 *
 * There is no debt and so no write-off queue: when a borrower cannot cover an
 * agreed late fee or penalty, they pay what they have and the Reserve pays the
 * lender the rest immediately. Each cover is a permanent ledger entry and
 * counts against the borrower's trust score for 12 months (§6.3.3). When the
 * Reserve runs low, the Admin notifies the Sponsor Liaison, who tops it up from
 * the Sponsor Pool.
 *
 * @var array $stats  three stat cards: reserve balance, covers this year, top-ups this year
 * @var array $covers recent covers: initials, title, meta, amount
 */

$stats  = $stats ?? [];
$covers = $covers ?? [];

$pageTitle = 'Reserve Pool';
$navActive = 'pools';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Reserve Pool &amp; shortfall covers</h1>
</header>

<div class="stat-grid stat-grid--3">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <h2 class="section__title">Recent shortfall covers</h2>

    <?php if ($covers === []): ?>
        <p class="record-meta">No shortfall has needed covering yet.</p>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($covers as $cover): ?>
                <li class="list-row">
                    <span class="avatar"><?= e($cover['initials']) ?></span>
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($cover['title']) ?></span>
                        <span class="list-row__meta"><?= e($cover['meta']) ?></span>
                    </div>
                    <span class="badge badge--info"><?= e($cover['amount']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="notice notice--info notice--full">
    Members never carry debt. A borrower who cannot cover an agreed late fee or penalty pays what they
    have, and the Reserve Pool pays the lender the rest at that moment. Each cover is logged permanently
    in the ledger. When the Reserve runs low, notify the Sponsor Liaison, who tops it up from the Sponsor Pool.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
