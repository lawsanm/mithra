<?php

declare(strict_types=1);

/**
 * Member approvals for a division (initial setup phase).
 *
 * @var array  $division       id, name
 * @var array  $approvalStats  label, value
 * @var array  $pendingMembers id, initials, name, nic_ending, address, applied_ago, documents
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
        <?php $statTone = ''; include __DIR__ . '/../../../partials/stat-card.php'; ?>
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
            <?php $hasRequiredDocuments = count($member['documents']) === 2; ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title">
                        <span class="avatar"><?= e($member['initials']) ?></span>
                        <?= e($member['name']) ?>
                    </span>
                    <span class="list-row__meta">
                        NIC ending <?= e($member['nic_ending']) ?> · <?= e($member['address']) ?> · <?= e($member['applied_ago']) ?>
                    </span>
                    <?php if ($member['documents'] !== []): ?>
                        <?php $gridPhotos = $member['documents']; include __DIR__ . '/../../../partials/photo-grid.php'; ?>
                    <?php endif; ?>
                    <?php if (!$hasRequiredDocuments): ?>
                        <span class="demo-note">One or more required documents are missing; approval is unavailable until both are on file.</span>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <form method="post" action="<?= base_url() ?>/admin/registrations/<?= e((string) $member['id']) ?>/reject" data-confirm="Reject this registration? The member will remain unable to sign in." novalidate>
                        <?= csrf_field() ?>
                        <button class="btn btn--ghost" type="submit">Reject</button>
                    </form>
                    <form method="post" action="<?= base_url() ?>/admin/registrations/<?= e((string) $member['id']) ?>/approve" data-confirm="Approve this registration? This opens the account and credits the welcome bonus from the Sponsor Pool." novalidate>
                        <?= csrf_field() ?>
                        <button class="btn btn--primary" type="submit"<?= !$hasRequiredDocuments ? ' disabled' : '' ?>>Approve</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
