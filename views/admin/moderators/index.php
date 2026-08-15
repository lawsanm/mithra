<?php

declare(strict_types=1);

/**
 * Moderator management — three-phase selection process.
 *
 * Shows per-division moderator status with phase-appropriate content:
 * Phase 1 (0–~10 members): Admin handles registrations directly.
 * Phase 2 (~10 members):   Admin appoints first moderator from verified pool.
 * Phase 3 (history):       Data-driven eligibility pool, highest trust score.
 *
 * @var array  $divisions       id, name, active(bool)
 * @var array  $phase           number(1|2|3), label, description
 * @var array  $divisionStats   label, value
 * @var array  $pendingMembers  initials, name, nic_ending, address, applied_ago
 * @var array  $verifiedMembers initials, name, verified_at, gn_endorsed(bool), conflict(string|null)
 * @var array  $eligibilityPool initials, name, trust_score, months, transactions, record, gn_endorsed(bool), recommended(bool)
 * @var array  $activeMods      initials, name, division, appointed_at, objection_status, objection_label, trust_score, bond, href
 */

$divisions ??= [
    ['id' => 0, 'name' => 'All divisions',    'active' => true],
    ['id' => 1, 'name' => 'Kaduwela West',    'active' => false],
    ['id' => 2, 'name' => 'Maharagama South',  'active' => false],
    ['id' => 3, 'name' => 'Battaramulla',      'active' => false],
];

$phase ??= ['number' => 3, 'label' => 'Phase 3 — Data-driven selection', 'description' => 'Platform has history. Eligibility pool active.'];

$divisionStats ??= [
    ['label' => 'Active moderators',    'value' => '2'],
    ['label' => 'Pending appointments', 'value' => '1'],
    ['label' => 'Divisions covered',    'value' => '2 / 3'],
];

$pendingMembers ??= [
    [
        'initials'    => 'NF',
        'name'        => 'Nadeeka Fernando',
        'nic_ending'  => '5523',
        'address'     => '18, Lake View Rd, Battaramulla',
        'applied_ago' => 'Applied 3 days ago',
    ],
    [
        'initials'    => 'RJ',
        'name'        => 'Ruwan Jayawardena',
        'nic_ending'  => '7891',
        'address'     => '42/1, Kotte Rd, Battaramulla',
        'applied_ago' => 'Applied 1 day ago',
    ],
];

$verifiedMembers ??= [
    ['initials' => 'SK', 'name' => 'Sita Kumari',       'verified_at' => '2026-05-12', 'gn_endorsed' => true,  'conflict' => null],
    ['initials' => 'AP', 'name' => 'Amara Peris',       'verified_at' => '2026-04-28', 'gn_endorsed' => true,  'conflict' => null],
    ['initials' => 'DN', 'name' => 'Dinesh Nanayakkara', 'verified_at' => '2026-05-30', 'gn_endorsed' => false, 'conflict' => null],
    ['initials' => 'PS', 'name' => 'Priyanka Silva',    'verified_at' => '2026-06-01', 'gn_endorsed' => false, 'conflict' => 'Runs rental business'],
];

$eligibilityPool ??= [
    ['initials' => 'PR', 'name' => 'Priya Rathnayake', 'trust_score' => 96, 'months' => 14, 'transactions' => 34, 'record' => 'Clean', 'gn_endorsed' => true,  'recommended' => true],
    ['initials' => 'AB', 'name' => 'Amara Bandara',    'trust_score' => 91, 'months' => 12, 'transactions' => 28, 'record' => 'Clean', 'gn_endorsed' => true,  'recommended' => false],
    ['initials' => 'CW', 'name' => 'Chathura Wijesinghe', 'trust_score' => 85, 'months' => 10, 'transactions' => 22, 'record' => 'Clean', 'gn_endorsed' => false, 'recommended' => false],
    ['initials' => 'NP', 'name' => 'Nadeesha Peris',   'trust_score' => 78, 'months' => 8,  'transactions' => 15, 'record' => 'Clean', 'gn_endorsed' => false, 'recommended' => false],
    ['initials' => 'SW', 'name' => 'Sandun Weerasinghe', 'trust_score' => 72, 'months' => 7,  'transactions' => 12, 'record' => 'Clean', 'gn_endorsed' => true,  'recommended' => false],
];

$activeMods ??= [
    [
        'initials'        => 'KP',
        'name'            => 'Kamal Perera',
        'division'        => 'Kaduwela West',
        'appointed_at'    => '15 Jan 2026',
        'objection_status' => 'success',
        'objection_label' => 'Confirmed',
        'trust_score'     => 92,
        'bond'            => '500 pts',
        'href'            => base_url() . '/admin/moderators/1',
    ],
    [
        'initials'        => 'SK',
        'name'            => 'Sita Kumari',
        'division'        => 'Maharagama South',
        'appointed_at'    => '22 Jul 2026',
        'objection_status' => 'warning',
        'objection_label' => '5 days left',
        'trust_score'     => null,
        'bond'            => '—',
        'href'            => base_url() . '/admin/moderators/objections/2',
    ],
];

$pageTitle = 'Moderator management';
$navActive = 'moderators';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Moderator management</h1>
</header>

<ul class="filter-pills">
    <?php foreach ($divisions as $div): ?>
        <li>
            <a class="pill<?= !empty($div['active']) ? ' pill--active' : '' ?>"
               href="<?= base_url() ?>/admin/moderators<?= $div['id'] > 0 ? '?division=' . e((string) $div['id']) : '' ?>"
               <?= !empty($div['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($div['name']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="stat-grid stat-grid--3">
    <?php foreach ($divisionStats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value"><?= e($stat['value']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<!-- Phase indicator -->
<div class="phase-card phase-card--phase-<?= e((string) $phase['number']) ?>">
    <strong class="phase-card__title"><?= e($phase['label']) ?></strong>
    <p class="phase-card__meta"><?= e($phase['description']) ?></p>
</div>

<!-- ── Phase 1 content (Battaramulla sample — 6 members, no moderator) ── -->
<section class="section" id="phase-1-content" style="display: none;">
    <div class="notice notice--info notice--full">
        You are handling member registrations directly. No moderator is needed until approximately 10 members are registered in this division.
    </div>

    <div class="section__head">
        <h2 class="section__title">Pending registration requests</h2>
    </div>

    <?php if ($pendingMembers === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">No pending registrations</p>
            <p class="empty-state__body">New member applications for this division will appear here.</p>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($pendingMembers as $member): ?>
                <li class="list-row">
                    <span class="avatar"><?= e($member['initials']) ?></span>
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($member['name']) ?></span>
                        <span class="list-row__meta">NIC ending <?= e($member['nic_ending']) ?> · <?= e($member['address']) ?> · <?= e($member['applied_ago']) ?></span>
                    </div>
                    <form method="post" action="<?= base_url() ?>/admin/moderators/registrations/reject" style="display:inline;">
                        <?= csrf_field() ?>
                        <button class="btn btn--ghost" type="submit">Reject</button>
                    </form>
                    <form method="post" action="<?= base_url() ?>/admin/moderators/registrations/approve" style="display:inline;">
                        <?= csrf_field() ?>
                        <button class="btn btn--primary" type="submit">Approve</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div style="margin-top: var(--space-6);">
        <button class="btn btn--primary" disabled title="Available when approximately 10 members are registered">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
            Appoint first moderator
        </button>
        <span class="list-row__meta" style="margin-left: var(--space-3);">Available when ~10 members are registered</span>
    </div>
</section>

<!-- ── Phase 2 content (Maharagama South — 11 members, no moderator yet) ── -->
<section class="section" id="phase-2-content" style="display: none;">
    <div class="notice notice--warning notice--full">
        The division has reached the member threshold. Select and appoint the first moderator from the verified members below. The appointment is announced publicly with a 7-day objection window before it is finalised.
    </div>

    <div class="section__head">
        <h2 class="section__title">Verified members</h2>
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Verified date</th>
                    <th>GN Officer endorsed</th>
                    <th>Conflict of interest</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($verifiedMembers as $member): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:var(--space-3);">
                                <span class="avatar"><?= e($member['initials']) ?></span>
                                <strong><?= e($member['name']) ?></strong>
                            </div>
                        </td>
                        <td><?= e(date('j M Y', strtotime($member['verified_at']))) ?></td>
                        <td>
                            <?php if ($member['gn_endorsed']): ?>
                                <span class="badge badge--success">✓ Endorsed</span>
                            <?php else: ?>
                                <span class="badge badge--neutral">✕ Not endorsed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($member['conflict'] !== null): ?>
                                <span class="badge badge--warning">! <?= e($member['conflict']) ?></span>
                            <?php else: ?>
                                <span class="badge badge--success">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($member['conflict'] === null): ?>
                                <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/appoint?division=2&member=<?= e($member['initials']) ?>">Select</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ── Phase 3 content (Kaduwela West — 142 members, data-driven pool) ── -->
<section class="section" id="phase-3-content">
    <div class="notice notice--info notice--full">
        Data-driven eligibility pool active. Members qualifying: 6+ months verified, trust score 70+, 10+ completed transactions, clean record, no conflict of interest.
    </div>

    <div class="section__head">
        <h2 class="section__title">Eligibility pool</h2>
        <span class="badge badge--info"><?= e((string) count($eligibilityPool)) ?> eligible</span>
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Trust score</th>
                    <th>Months verified</th>
                    <th>Transactions</th>
                    <th>Record</th>
                    <th>GN endorsed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eligibilityPool as $member): ?>
                    <tr<?= $member['recommended'] ? ' class="list-row--highlighted"' : '' ?>>
                        <td>
                            <div style="display:flex; align-items:center; gap:var(--space-3);">
                                <span class="avatar"><?= e($member['initials']) ?></span>
                                <div>
                                    <strong><?= e($member['name']) ?></strong>
                                    <?php if ($member['recommended']): ?>
                                        <span class="badge badge--warning" style="margin-left: var(--space-2);">Recommended</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><strong style="color: var(--color-primary);"><?= e((string) $member['trust_score']) ?></strong></td>
                        <td><?= e((string) $member['months']) ?></td>
                        <td><?= e((string) $member['transactions']) ?></td>
                        <td><span class="badge badge--success">✓ <?= e($member['record']) ?></span></td>
                        <td>
                            <?php if ($member['gn_endorsed']): ?>
                                <span class="badge badge--success">✓</span>
                            <?php else: ?>
                                <span class="badge badge--neutral">✕</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn--<?= $member['recommended'] ? 'primary' : 'ghost' ?>" href="<?= base_url() ?>/admin/moderators/appoint?division=1&member=<?= e($member['initials']) ?>">Select</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ── Active moderators (always visible) ── -->
<section class="section">
    <h2 class="section__title">Active moderators</h2>

    <?php if ($activeMods === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">No moderators appointed yet</p>
            <p class="empty-state__body">Moderators will appear here once they are appointed and confirmed.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Division</th>
                        <th>Appointed</th>
                        <th>Objection window</th>
                        <th>Trust score</th>
                        <th>Bond</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeMods as $mod): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:var(--space-3);">
                                    <span class="avatar"><?= e($mod['initials']) ?></span>
                                    <strong><?= e($mod['name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e($mod['division']) ?></td>
                            <td><?= e($mod['appointed_at']) ?></td>
                            <td>
                                <span class="badge badge--<?= e($mod['objection_status']) ?>">
                                    <?= $mod['objection_status'] === 'success' ? '✓' : '⏱' ?> <?= e($mod['objection_label']) ?>
                                </span>
                            </td>
                            <td><?= $mod['trust_score'] !== null ? e((string) $mod['trust_score']) : '—' ?></td>
                            <td><?= e($mod['bond']) ?></td>
                            <td>
                                <a class="btn btn--ghost" href="<?= e($mod['href']) ?>">View profile</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    var pills = document.querySelectorAll('.filter-pills .pill');
    var phase1 = document.getElementById('phase-1-content');
    var phase2 = document.getElementById('phase-2-content');
    var phase3 = document.getElementById('phase-3-content');
    var phaseCard = document.querySelector('.phase-card');

    var divisionPhases = {
        '0': { num: 3, label: 'Phase 3 — Data-driven selection', desc: 'Platform has history. Eligibility pool active.' },
        '1': { num: 3, label: 'Phase 3 — Data-driven selection', desc: 'Platform has history. Eligibility pool active.' },
        '2': { num: 2, label: 'Phase 2 — First moderator appointment', desc: '~10 members reached. Admin selects and appoints the first moderator.' },
        '3': { num: 1, label: 'Phase 1 — Admin-run', desc: 'Under 10 members. Admin handles registrations directly.' }
    };

    function showPhase(divId) {
        var p = divisionPhases[divId] || divisionPhases['0'];
        phase1.style.display = p.num === 1 ? '' : 'none';
        phase2.style.display = p.num === 2 ? '' : 'none';
        phase3.style.display = p.num === 3 ? '' : 'none';

        phaseCard.className = 'phase-card phase-card--phase-' + p.num;
        phaseCard.querySelector('.phase-card__title').textContent = p.label;
        phaseCard.querySelector('.phase-card__meta').textContent = p.desc;
    }

    pills.forEach(function (pill) {
        pill.addEventListener('click', function (e) {
            e.preventDefault();
            pills.forEach(function (p) { p.classList.remove('pill--active'); p.removeAttribute('aria-current'); });
            pill.classList.add('pill--active');
            pill.setAttribute('aria-current', 'true');

            var href = pill.getAttribute('href');
            var match = href.match(/division=(\d+)/);
            var divId = match ? match[1] : '0';
            showPhase(divId);
        });
    });
})();
</script>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
