<?php

declare(strict_types=1);

/**
 * Member approvals for a division (bootstrap phase).
 *
 * @var array  $division       name
 * @var array  $approvalStats  label, value
 * @var array  $pendingMembers initials, name, nic_ending, address, applied_ago, evidence(array), evidence_warning(string|null)
 */

$division ??= ['name' => 'Kollupitiya', 'id' => 1];

$approvalStats ??= [
    ['label' => 'Pending requests',            'value' => '4'],
    ['label' => 'Approved members',            'value' => '6'],
    ['label' => 'Progress to first moderator', 'value' => '6 / 10'],
];

$pendingMembers ??= [
    [
        'initials'         => 'ML',
        'name'             => 'M. Lawsan',
        'nic_ending'       => '4471',
        'address'          => '22/3, Temple Lane',
        'applied_ago'      => 'Applied 2 days ago',
        'evidence'         => ['NIC photo', 'Utility bill', 'Address matches register'],
        'evidence_warning' => null,
    ],
    [
        'initials'         => 'JK',
        'name'             => 'J. Kavipriya',
        'nic_ending'       => '8823',
        'address'          => '8, Beach Road',
        'applied_ago'      => 'Applied 1 day ago',
        'evidence'         => ['NIC photo', 'Lease letter'],
        'evidence_warning' => null,
    ],
    [
        'initials'         => 'AA',
        'name'             => 'A. Akalvily',
        'nic_ending'       => '1130',
        'address'          => '45/1, Galle Road',
        'applied_ago'      => 'Applied 4 hours ago',
        'evidence'         => ['NIC photo', 'Employment letter'],
        'evidence_warning' => 'Evidence incomplete',
    ],
];

$pageTitle = 'Member Approvals';
$navActive = 'divisions';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <div>
        <h1 class="page-header__title">Member Approvals</h1>
        <p class="page-intro__meta"><?= e($division['name']) ?> GN Division · Bootstrap phase</p>
    </div>
    <span class="badge badge--info page-header__action">i Admin is verifying — no moderator appointed yet</span>
</header>

<div class="stat-grid stat-grid--3">
    <?php foreach ($approvalStats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value"><?= e($stat['value']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<div class="notice notice--warning notice--full">
    You are approving members directly because this division has no moderator yet. Verify each applicant's evidence against the division register. When the community reaches 10 members, appoint the first moderator from the approved pool.
</div>

<section class="section">
    <h2 class="section__title">Pending registration requests</h2>

    <ul class="row-list">
        <?php foreach ($pendingMembers as $member): ?>
            <li class="list-row">
                <span class="avatar"><?= e($member['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($member['name']) ?></span>
                    <span class="list-row__meta">
                        NIC ending <?= e($member['nic_ending']) ?> · <?= e($member['address']) ?> · <?= e($member['applied_ago']) ?>
                    </span>
                    <div class="actions" style="margin-top: var(--space-2);">
                        <?php foreach ($member['evidence'] as $tag): ?>
                            <span class="pill"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                        <?php if ($member['evidence_warning'] !== null): ?>
                            <span class="pill" style="background-color: var(--color-warning-tint); color: var(--color-warning-text);"><?= e($member['evidence_warning']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" action="/admin/divisions/<?= e((string) $division['id']) ?>/approvals/reject" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="member_id" value="<?= e($member['initials']) ?>">
                    <button class="btn btn--ghost" type="submit">Reject</button>
                </form>
                <form method="post" action="/admin/divisions/<?= e((string) $division['id']) ?>/approvals/approve" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="member_id" value="<?= e($member['initials']) ?>">
                    <button class="btn btn--primary" type="submit">Approve</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
