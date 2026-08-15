<?php

declare(strict_types=1);

/**
 * Sponsor Fund Ledger - cash-to-points conversions and audit trail.
 *
 * @var array $summary     totalReceived, totalUsed, remaining (each: value, sub)
 * @var array $inflows     rows: date, sponsor, ref, category, cash, pts, status, status_label
 * @var array $outflows    rows: date, payee, ref, category, cash, pts, status, status_label
 * @var array $audits      rows: quarter, range, auditor, report_date, status, status_label
 * @var array $auditorInfo name, description
 */

$summary ??= [
    'totalReceived' => ['value' => '192,000 pts', 'sub' => 'Rs 192,000 converted at 1:1 ratio'],
    'totalUsed'     => ['value' => '143,800 pts', 'sub' => 'Welcome bonuses · Stipends · Infrastructure'],
    'remaining'     => ['value' => '48,200 pts',  'sub' => 'Held in Sponsor Pool'],
];

$inflows ??= [
    ['date' => '25 Jul 2026', 'sponsor' => 'Dialog Axiata PLC',       'ref' => 'INV-2026-047', 'category' => 'Sponsorship',   'cash' => 'Rs 50,000',  'pts' => '+50,000',  'status' => 'success', 'status_label' => 'Settled'],
    ['date' => '18 Jul 2026', 'sponsor' => 'Sampath Bank Foundation', 'ref' => 'INV-2026-042', 'category' => 'Sponsorship',   'cash' => 'Rs 30,000',  'pts' => '+30,000',  'status' => 'success', 'status_label' => 'Settled'],
    ['date' => '10 Jul 2026', 'sponsor' => 'Brandix Lanka',           'ref' => 'INV-2026-038', 'category' => 'CSR Grant',     'cash' => 'Rs 25,000',  'pts' => '+25,000',  'status' => 'success', 'status_label' => 'Settled'],
    ['date' => '01 Jul 2026', 'sponsor' => 'Cargills Ceylon PLC',     'ref' => 'INV-2026-035', 'category' => 'Sponsorship',   'cash' => 'Rs 40,000',  'pts' => '+40,000',  'status' => 'warning', 'status_label' => 'Pending'],
    ['date' => '22 Jun 2026', 'sponsor' => 'MAS Holdings',            'ref' => 'INV-2026-031', 'category' => 'CSR Grant',     'cash' => 'Rs 20,000',  'pts' => '+20,000',  'status' => 'success', 'status_label' => 'Settled'],
    ['date' => '15 Jun 2026', 'sponsor' => 'John Keells Holdings',    'ref' => 'INV-2026-028', 'category' => 'Sponsorship',   'cash' => 'Rs 27,000',  'pts' => '+27,000',  'status' => 'success', 'status_label' => 'Settled'],
];

$outflows ??= [
    ['date' => '26 Jul 2026', 'payee' => 'DigitalOcean (hosting)',    'ref' => 'RCP-2026-089', 'category' => 'Infrastructure', 'cash' => 'Rs 8,400',  'pts' => '−8,400',  'status' => 'success', 'status_label' => 'Paid'],
    ['date' => '20 Jul 2026', 'payee' => 'Google Workspace',          'ref' => 'RCP-2026-085', 'category' => 'Infrastructure', 'cash' => 'Rs 3,200',  'pts' => '−3,200',  'status' => 'success', 'status_label' => 'Paid'],
    ['date' => '15 Jul 2026', 'payee' => 'SSL Certificate renewal',   'ref' => 'RCP-2026-082', 'category' => 'Infrastructure', 'cash' => 'Rs 4,500',  'pts' => '−4,500',  'status' => 'success', 'status_label' => 'Paid'],
    ['date' => '01 Jul 2026', 'payee' => 'Auditor retainer — Q3',     'ref' => 'RCP-2026-078', 'category' => 'Compliance',     'cash' => 'Rs 15,000', 'pts' => '−15,000', 'status' => 'success', 'status_label' => 'Paid'],
    ['date' => '28 Jun 2026', 'payee' => 'Domain renewal (mithra.lk)','ref' => 'RCP-2026-074', 'category' => 'Infrastructure', 'cash' => 'Rs 2,800',  'pts' => '−2,800',  'status' => 'warning', 'status_label' => 'Pending'],
];

$auditorInfo ??= [
    'name'        => 'Harrington & Associates — Chartered Accountants',
    'description' => 'Independent quarterly audit of the Sponsor Fund. Cash-in vs points-credited reconciliation, verified against bank statements and platform ledger.',
];

$audits ??= [
    ['quarter' => 'Q2 2026', 'range' => 'Apr – Jun 2026', 'auditor' => 'Harrington & Associates', 'report_date' => '12 Jul 2026', 'status' => 'success', 'status_label' => 'Matched'],
    ['quarter' => 'Q1 2026', 'range' => 'Jan – Mar 2026', 'auditor' => 'Harrington & Associates', 'report_date' => '10 Apr 2026', 'status' => 'success', 'status_label' => 'Matched'],
    ['quarter' => 'Q4 2025', 'range' => 'Oct – Dec 2025', 'auditor' => 'Harrington & Associates', 'report_date' => '14 Jan 2026', 'status' => 'success', 'status_label' => 'Matched'],
];

$pageTitle = 'Sponsor Fund Ledger';
$navActive = 'pools';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <div>
        <h1 class="page-header__title">Sponsor Fund Ledger</h1>
        <p class="page-intro__meta">Cash → Points conversions · 1:1 ratio · maintenance costs only</p>
    </div>
    <div class="page-header__action actions">
        <button class="btn btn--ghost" disabled title="Export coming soon">Export CSV</button>
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/pools">Back to Pools</a>
    </div>
</header>

<div class="stat-grid stat-grid--3">
    <div class="stat-card" style="background-color: var(--color-info-tint);">
        <span class="stat-card__label" style="text-transform: uppercase; letter-spacing: 0.05em; font-size: var(--text-ui-caption);">Total Received</span>
        <strong class="stat-card__value stat-card__value--primary"><?= e($summary['totalReceived']['value']) ?></strong>
        <span class="stat-card__note"><?= e($summary['totalReceived']['sub']) ?></span>
    </div>
    <div class="stat-card" style="background-color: var(--color-accent-tint);">
        <span class="stat-card__label" style="text-transform: uppercase; letter-spacing: 0.05em; font-size: var(--text-ui-caption);">Total Used</span>
        <strong class="stat-card__value" style="color: var(--color-accent-text);"><?= e($summary['totalUsed']['value']) ?></strong>
        <span class="stat-card__note"><?= e($summary['totalUsed']['sub']) ?></span>
    </div>
    <div class="stat-card" style="background-color: var(--color-success-tint);">
        <span class="stat-card__label" style="text-transform: uppercase; letter-spacing: 0.05em; font-size: var(--text-ui-caption);">Remaining Balance</span>
        <strong class="stat-card__value" style="color: var(--color-success-text);"><?= e($summary['remaining']['value']) ?></strong>
        <span class="stat-card__note"><?= e($summary['remaining']['sub']) ?></span>
    </div>
</div>

<!-- Tabs -->
<ul class="filter-pills" role="tablist">
    <li><button class="pill pill--active" role="tab" aria-selected="true" data-tab="inflows">Inflows</button></li>
    <li><button class="pill" role="tab" aria-selected="false" data-tab="outflows">Outflows</button></li>
    <li><button class="pill" role="tab" aria-selected="false" data-tab="audit">Audit Reconciliation</button></li>
</ul>

<!-- ── Tab 1: Inflows ── -->
<div class="tab-content" id="tab-inflows">
    <div class="notice notice--info notice--full">
        Points from the Sponsor Pool are debited only for verifiable system maintenance costs. Each debit requires a receipt reference. No personal expenses, no discretionary spending — only essential infrastructure keeping the platform running.
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Sponsor / Payee</th>
                    <th>Ref</th>
                    <th>Category</th>
                    <th>Cash (Rs)</th>
                    <th>Pts Credited</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inflows as $row): ?>
                    <tr>
                        <td><?= e($row['date']) ?></td>
                        <td><strong><?= e($row['sponsor']) ?></strong></td>
                        <td><span style="font-family: monospace; font-size: var(--text-ui-caption);"><?= e($row['ref']) ?></span></td>
                        <td><span class="badge badge--info"><?= e($row['category']) ?></span></td>
                        <td><?= e($row['cash']) ?></td>
                        <td><strong style="color: var(--color-success-text);"><?= e($row['pts']) ?></strong></td>
                        <td><span class="badge badge--<?= e($row['status']) ?>"><?= e($row['status_label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Tab 2: Outflows ── -->
<div class="tab-content" id="tab-outflows" style="display: none;">
    <div class="notice notice--info notice--full">
        Points from the Sponsor Pool are debited only for verifiable system maintenance costs. Each debit requires a receipt reference. No personal expenses, no discretionary spending — only essential infrastructure keeping the platform running.
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Sponsor / Payee</th>
                    <th>Ref</th>
                    <th>Category</th>
                    <th>Cash (Rs)</th>
                    <th>Pts Debited</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outflows as $row): ?>
                    <tr>
                        <td><?= e($row['date']) ?></td>
                        <td><strong><?= e($row['payee']) ?></strong></td>
                        <td><span style="font-family: monospace; font-size: var(--text-ui-caption);"><?= e($row['ref']) ?></span></td>
                        <td>
                            <span class="badge badge--<?= $row['category'] === 'Infrastructure' ? 'info' : 'warning' ?>">
                                <?= e($row['category']) ?>
                            </span>
                        </td>
                        <td><?= e($row['cash']) ?></td>
                        <td><strong style="color: var(--color-error-text);"><?= e($row['pts']) ?></strong></td>
                        <td><span class="badge badge--<?= e($row['status']) ?>"><?= e($row['status_label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="page-intro__meta" style="margin-top: var(--space-4);">
        Visible to: Admin and Sponsor Liaison only · External Transparency Dashboard shows aggregate figures.
    </p>
</div>

<!-- ── Tab 3: Audit Reconciliation ── -->
<div class="tab-content" id="tab-audit" style="display: none;">
    <div class="notice notice--info notice--full">
        Harrington &amp; Associates independently audits the Sponsor Fund each quarter. Their figures are compared against our internal ledger here. Any mismatch — even by 1 pt — is flagged automatically and surfaced to the board. All past audits have matched.
    </div>

    <div class="form-card" style="width: 100%; max-width: 100%; margin-bottom: var(--space-6);">
        <h3 class="form-card__legend"><?= e($auditorInfo['name']) ?></h3>
        <p class="page-intro__meta"><?= e($auditorInfo['description']) ?></p>
    </div>

    <div style="display: flex; flex-direction: column; gap: var(--space-4);">
        <?php foreach ($audits as $audit): ?>
            <div class="form-card" style="width: 100%; max-width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="form-card__legend" style="margin-bottom: var(--space-1);"><?= e($audit['quarter']) ?> — <?= e($audit['range']) ?></h3>
                        <p class="page-intro__meta"><?= e($audit['auditor']) ?> · Report date: <?= e($audit['report_date']) ?></p>
                    </div>
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <span class="badge badge--<?= e($audit['status']) ?>">
                            <?= $audit['status'] === 'success' ? '✓' : '✕' ?> <?= e($audit['status_label']) ?>
                        </span>
                        <button class="btn btn--ghost" disabled title="Report viewing coming soon">View report</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('[data-tab]');
    var panels = document.querySelectorAll('.tab-content');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) {
                t.classList.remove('pill--active');
                t.setAttribute('aria-selected', 'false');
            });
            panels.forEach(function (p) { p.style.display = 'none'; });

            tab.classList.add('pill--active');
            tab.setAttribute('aria-selected', 'true');
            document.getElementById('tab-' + tab.getAttribute('data-tab')).style.display = '';
        });
    });
})();
</script>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
