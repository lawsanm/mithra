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

$pageTitle = 'Moderator management';
$navActive = 'moderators';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Moderator management</h1>
    <div class="actions">
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/history">Appointment history</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/liaison">Liaison assignment</a>
    <button class="btn btn--primary page-header__action" type="button" data-modal-open="modal-create-moderator-candidate">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Record nomination
    </button>
    </div>
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
        <?php $statTone = ''; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<!-- Phase indicator -->
<div class="phase-card phase-card--phase-<?= e((string) $phase['number']) ?>">
    <strong class="phase-card__title"><?= e($phase['label']) ?></strong>
    <p class="phase-card__meta"><?= e($phase['description']) ?></p>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Nominations and candidate checks</h2>
    </div>

    <?php if ($nominations === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">No nominations recorded</p>
            <p class="empty-state__body">Record a community nomination to begin candidate checks and evaluation.</p>
        </div>
    <?php else: ?>
        <div class="scroll-x">
            <table class="data-table">
                <thead>
                    <tr><th>Candidate</th><th>Status</th><th>Platform capability</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($nominations as $nomination): ?>
                        <tr>
                            <td>
                                <div class="cluster">
                                    <span class="avatar"><?= e($nomination['initials']) ?></span>
                                    <strong><?= e($nomination['name']) ?></strong>
                                </div>
                            </td>
                            <td><span class="badge badge--info"><?= e(ucfirst(str_replace('_', ' ', $nomination['candidate_status']))) ?></span></td>
                            <td><?= (int) $nomination['competency_score'] > 0 ? 'Demonstrated' : 'Pending' ?></td>
                            <td>
                                <button class="btn btn--ghost" type="button" data-modal-open="modal-review-moderator-candidate-<?= e((string) $nomination['candidate_id']) ?>">
                                    Review checks
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- ── Phase 1 content: first appointment waits for the minimum membership ── -->
<section class="section" id="phase-1-content" <?= $phase['number'] === 1 ? '' : 'hidden' ?>>
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
                    <div class="preview-action" data-demo-form>
                        <div class="actions">
                            <button class="btn btn--ghost" type="button" disabled>Reject</button>
                            <button class="btn btn--primary" type="button" disabled>Approve</button>
                        </div>
                        <span class="demo-note">Not available in this demo</span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="u-mt-6">
        <button class="btn btn--primary" disabled title="Available when approximately 10 members are registered">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
            Appoint first moderator
        </button>
        <span class="list-row__meta" style="margin-left: var(--space-3);">Available when ~10 members are registered</span>
    </div>
</section>

<!-- ── Phase 2 content: first appointment or replacement ── -->
<section class="section" id="phase-2-content" <?= $phase['number'] === 2 ? '' : 'hidden' ?>>
    <div class="notice notice--warning notice--full">
        <?php if ($hasModerator): ?>
            This division already has an active or trial moderator. End that appointment before selecting a replacement.
        <?php elseif ($hasAppointmentHistory): ?>
            The division has a vacancy after a previous appointment. Select the highest-trust member who meets normal-operation eligibility. A 7-day objection window starts when appointed; the three-month trial and 500-point bond start on day one.
        <?php else: ?>
            The division has reached the member threshold. Select the approved resident who best meets the bootstrap criteria. A 7-day objection window starts when appointed; the three-month trial and 500-point bond start on day one.
        <?php endif; ?>
    </div>

    <div class="section__head">
        <h2 class="section__title">Verified members</h2>
    </div>

    <div class="scroll-x">
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
                            <div class="cluster">
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
                                <span class="badge badge--success">Declaration signed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$hasModerator && !$hasAppointmentHistory && $member['launch_ready']): ?>
                                <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $selectedDivision) ?>?member=<?= e((string) $member['id']) ?>">Select</a>
                            <?php elseif ($hasModerator): ?>
                                <span class="badge badge--neutral">Current moderator must leave first</span>
                            <?php elseif ($hasAppointmentHistory && $member['eligible'] && $member['id'] === $recommendedCandidateId): ?>
                                <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $selectedDivision) ?>?member=<?= e((string) $member['id']) ?>">Select</a>
                            <?php elseif ((!$hasAppointmentHistory && $member['launch_ready']) || ($hasAppointmentHistory && $member['eligible'])): ?>
                                <span class="badge badge--neutral">Lower-ranked candidate</span>
                            <?php else: ?>
                                <span class="badge badge--neutral">Checks incomplete</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ── Phase 3 content (Kaduwela West — 142 members, data-driven pool) ── -->
<section class="section" id="phase-3-content" <?= $phase['number'] === 3 ? '' : 'hidden' ?>>
    <div class="notice notice--info notice--full">
        <?php if ($hasModerator): ?>
            This division already has an active or trial moderator. End that appointment before selecting a replacement.
        <?php else: ?>
            Data-driven eligibility pool active. Members qualifying: 6+ months verified, trust score 70+, 10+ completed transactions, clean record, and signed conflict declaration.
        <?php endif; ?>
    </div>

    <div class="section__head">
        <h2 class="section__title">Eligibility pool</h2>
        <span class="badge badge--info"><?= e((string) count($eligibilityPool)) ?> eligible</span>
    </div>

    <div class="scroll-x">
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
                <?php if ($eligibilityPool === []): ?>
                    <tr><td colspan="7">No members currently meet the recorded normal-operation eligibility criteria.</td></tr>
                <?php endif; ?>
                <?php foreach ($eligibilityPool as $member): ?>
                    <tr<?= $member['recommended'] ? ' class="list-row--highlighted"' : '' ?>>
                        <td>
                            <div class="cluster">
                                <span class="avatar"><?= e($member['initials']) ?></span>
                                <div>
                                    <strong><?= e($member['name']) ?></strong>
                                    <?php if ($member['recommended']): ?>
                                        <span class="badge badge--warning u-ml-2">Recommended</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><strong style="color: var(--color-primary);"><?= e((string) $member['trust_score']) ?></strong></td>
                        <td><?= e((string) $member['months']) ?></td>
                        <td><?= e((string) $member['transactions']) ?></td>
                        <td>
                            <span class="badge badge--<?= $member['record'] === 'Clean' ? 'success' : 'warning' ?>"><?= e($member['record']) ?></span>
                            <?php if ($member['upheld_claims'] > 0): ?>
                                <span class="list-row__meta"><?= e((string) $member['upheld_claims']) ?> resolved claim(s)</span>
                            <?php endif; ?>
                            <?php if ($member['has_suspension']): ?>
                                <span class="list-row__meta">Suspension on record</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($member['gn_endorsed']): ?>
                                <span class="badge badge--success">✓</span>
                            <?php else: ?>
                                <span class="badge badge--neutral">✕</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasModerator): ?>
                                <span class="badge badge--neutral">Current moderator must leave first</span>
                            <?php else: ?>
                                <?php if ($member['recommended']): ?>
                                    <a class="btn btn--primary" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $selectedDivision) ?>?member=<?= e((string) $member['id']) ?>">Select recommended</a>
                                <?php else: ?>
                                    <span class="badge badge--neutral">Lower trust score</span>
                                <?php endif; ?>
                            <?php endif; ?>
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
        <div class="scroll-x">
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
                                <div class="cluster">
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
                                <a class="btn btn--ghost" href="<?= e($mod['objections_href']) ?>">View objections</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php foreach ($nominations as $nomination): ?>
    <?php include __DIR__ . '/../../../partials/modal-review-moderator-candidate.php'; ?>
<?php endforeach; ?>
<?php include __DIR__ . '/../../../partials/modal-create-moderator-candidate.php'; ?>
<?php $pageScripts = ['modal.js']; include __DIR__ . '/../../../partials/footer.php'; ?>
