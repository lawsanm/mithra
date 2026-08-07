<?php

declare(strict_types=1);

/**
 * Reserve pool & write-offs - admin queue for debts older than 60 days.
 *
 * @var array $stats      three stat cards: reserve balance, candidates, written off
 * @var array $candidates write-off queue rows: initials, name, division, amount, reason, age, status, status_label
 */

$stats ??= [
    ['label' => 'Reserve Pool balance', 'value' => '6,400 pts',  'note' => 'Closures & write-off buffer'],
    ['label' => 'Write-off candidates', 'value' => '3',          'note' => 'Debts older than 60 days'],
    ['label' => 'Written off (2026)',   'value' => '840 pts',     'note' => '11 accounts'],
];

$candidates ??= [
    [
        'initials'     => 'ML',
        'name'         => 'M. Lawsan',
        'division'     => 'Kollupitiya',
        'amount'       => '−45 pts',
        'reason'       => 'overdue late fees',
        'age'          => '74 days',
        'extra'        => 'account dormant',
        'status'       => 'info',
        'status_label' => '74 days',
    ],
    [
        'initials'     => 'AA',
        'name'         => 'A. Akalvily',
        'division'     => 'Dehiwala',
        'amount'       => '−120 pts',
        'reason'       => 'damage award unpaid',
        'age'          => '68 days',
        'extra'        => 'member unreachable',
        'status'       => 'warning',
        'status_label' => 'Unreachable',
    ],
    [
        'initials'     => 'JK',
        'name'         => 'J. Kavipriya',
        'division'     => 'Wellawatte',
        'amount'       => '−30 pts',
        'reason'       => 'late fees',
        'age'          => '62 days',
        'extra'        => 'repayment plan lapsed',
        'status'       => 'info',
        'status_label' => '62 days',
    ],
];

$pageTitle = 'Reserve pool & write-offs';
$navActive = 'pools';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Reserve pool &amp; write-offs</h1>
</header>

<div class="stat-grid stat-grid--3">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value stat-card__value--primary"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="section">
    <h2 class="section__title">Write-off queue — debts over 60 days</h2>

    <ul class="row-list">
        <?php foreach ($candidates as $c): ?>
            <li class="list-row">
                <span class="avatar"><?= e($c['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($c['name']) ?> — <?= e($c['division']) ?></span>
                    <span class="list-row__meta"><?= e($c['amount']) ?> · <?= e($c['reason']) ?> · <?= e($c['age']) ?> · <?= e($c['extra']) ?></span>
                </div>
                <span class="badge badge--<?= e($c['status']) ?>"><?= e($c['status_label']) ?></span>
                <button class="btn btn--primary" type="button" data-modal-open="modal-approve-writeoff" data-name="<?= e($c['name']) ?>" data-division="<?= e($c['division']) ?>" data-amount="<?= e($c['amount']) ?>" data-reason="<?= e($c['reason']) ?>" data-age="<?= e($c['age']) ?>">Approve write-off</button>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="notice notice--info notice--full">
    Write-offs draw down the Reserve Pool so member balances stay whole and the nightly invariant holds. Each approval is logged in the audit ledger.
</div>

<?php include __DIR__ . '/../../../partials/modal-approve-writeoff.php'; ?>
<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
