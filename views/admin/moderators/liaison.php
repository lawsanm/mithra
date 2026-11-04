<?php

declare(strict_types=1);

$pageTitle = 'Sponsor Liaison assignment';
$navActive = 'moderators';
$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';
?>
<header class="page-header"><h1 class="page-header__title">Sponsor Liaison assignment</h1><a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators">Back to moderators</a></header>
<p class="notice notice--info">Assign an existing active account prepared for staff use. Accounts with community memberships, wallet points, active listings or bookings must settle those commitments first. Assign a replacement before removing the last active Liaison. Removing this role returns the account to the member role.</p>
<section class="section"><h2 class="section__title">Staff and available accounts</h2><ul class="row-list">
<?php foreach ($rows as $row): ?><li class="list-row"><div class="list-row__body"><strong><?= e($row['full_name']) ?></strong><p class="list-row__meta">#<?= e((string) $row['id']) ?> · <?= e($row['email'] ?? '') ?> · <?= e($row['role_code']) ?></p></div></li><?php endforeach; ?>
</ul></section>
<section class="form-card form-card--wide"><h2 class="form-card__legend">Update assignment</h2><form method="post" action="<?= base_url() ?>/admin/moderators/liaison?page=<?= e((string) $page) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label class="field__label" for="liaison-account">Account</label><select class="input" name="user_id" id="liaison-account"><option value="">Select account</option><?php foreach ($rows as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= ($values['user_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['full_name']) ?> · <?= e($row['role_code']) ?></option><?php endforeach; ?></select><?php if (isset($errors['user_id'])): ?><p class="field__error"><?= e($errors['user_id']) ?></p><?php endif; ?></div>
    <div class="field"><label class="field__label" for="liaison-action">Action</label><select class="input" name="action" id="liaison-action"><option value="assign" <?= ($values['action'] ?? '') === 'assign' ? 'selected' : '' ?>>Assign Sponsor Liaison role</option><option value="remove" <?= ($values['action'] ?? '') === 'remove' ? 'selected' : '' ?>>Remove Sponsor Liaison role</option></select><?php if (isset($errors['action'])): ?><p class="field__error"><?= e($errors['action']) ?></p><?php endif; ?></div>
    <button class="btn btn--primary" type="submit">Save assignment</button>
</form></section>
<nav class="actions" aria-label="Staff pagination"><?php if ($page > 1): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page - 1)) ?>">Previous</a><?php endif; ?><?php if (count($rows) === 20): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page + 1)) ?>">Next</a><?php endif; ?></nav>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
