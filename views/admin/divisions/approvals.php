<?php

declare(strict_types=1);

/**
 * Member approvals for a division (initial setup phase).
 *
 * @var array  $division       id, name
 * @var array  $approvalStats  label, value
 * @var array  $pendingMembers initials, name, nic_ending, address, applied_ago
 */

$pageTitle = 'Member Approvals';
$navActive = 'divisions';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <div>
        <h1 class="page-header__title">Member Approvals</h1>
        <p class="page-intro__meta"><?= e($division['name']) ?> GN Division · Initial setup phase</p>
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

    <?php if ($pendingMembers === []): ?>
        <p class="empty-state__body">No registration requests are waiting in this division.</p>
    <?php endif; ?>

    <ul class="row-list">
        <?php foreach ($pendingMembers as $member): ?>
            <li class="list-row">
                <span class="avatar"><?= e($member['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($member['name']) ?></span>
                    <span class="list-row__meta">
                        NIC ending <?= e($member['nic_ending']) ?> · <?= e($member['address']) ?> · <?= e($member['applied_ago']) ?>
                    </span>
                </div>
                <div style="display:inline;" data-demo-form>
                    <p class="demo-note">Preview only. Saving is not available yet.</p>
                    <button class="btn btn--ghost" type="submit" disabled>Reject</button>
                </div>
                <div style="display:inline;" data-demo-form>
                    <p class="demo-note">Preview only. Saving is not available yet.</p>
                    <button class="btn btn--primary" type="submit" disabled>Approve</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
