<?php

declare(strict_types=1);

$pageTitle = 'Review moderator appointment';
$navActive = 'moderators';
$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a class="breadcrumb__link" href="<?= base_url() ?>/admin/moderators">Moderators</a><span class="breadcrumb__separator">›</span><span class="breadcrumb__current">Appointment review</span></nav>
<header class="page-header"><h1 class="page-header__title"><?= e($appointment['full_name']) ?> — appointment review</h1></header>
<section class="form-card form-card--wide">
    <h2 class="form-card__legend"><?= e($appointment['division_name']) ?></h2>
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/expenses/<?= e((string) $appointment['id']) ?>">Expense records</a>
    <p>Status: <strong><?= e(ucfirst($appointment['status'])) ?></strong></p>
    <p>Objection window ends: <?= e($appointment['objection_window_ends'] ?? 'Not recorded') ?></p>
    <p>Three-month trial ends: <?= e($appointment['trial_ends_at'] ?? 'Not recorded') ?></p>
    <p>Confirmed: <?= e($appointment['confirmed_at'] ?? 'Not yet confirmed') ?></p>
    <p>Bond: <?= e((string) $appointment['bond_points']) ?> points · <?= e($appointment['bond_status']) ?></p>
    <?php if ($appointment['ended_at'] !== null): ?><p>Ended: <?= e($appointment['ended_at']) ?> · <?= e($appointment['end_reason']) ?></p><?php endif; ?>
</section>
<section class="section">
    <h2 class="section__title">Community objections</h2>
    <?php if ($objections === []): ?><p class="notice notice--info">No objections on this page.</p><?php endif; ?>
    <ul class="row-list">
        <?php foreach ($objections as $objection): ?>
            <li class="list-row"><div class="list-row__body"><strong>#<?= e((string) $objection['id']) ?> · <?= e($objection['full_name']) ?></strong><p><?= e($objection['reason']) ?></p><span class="list-row__meta"><?= e($objection['created_at']) ?> · <?= e($objection['status']) ?></span></div></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php if ($appointment['ended_at'] === null): ?>
<section class="form-card form-card--wide">
    <h2 class="form-card__legend">Record Admin decision</h2>
    <p class="notice notice--info">Confirm the appointment only after reviewing performance at the end of the three-month trial and resolving all objections. Upholding an objection ends the appointment immediately. Choose bond forfeiture only when removal is for cause.</p>
    <form method="post" action="<?= base_url() ?>/admin/moderators/appointments/<?= e((string) $appointment['id']) ?>?page=<?= e((string) $page) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label class="field__label" for="decision-action">Action</label><select class="input" name="action" id="decision-action">
            <option value="">Select action</option>
            <option value="record_objection" <?= ($values['action'] ?? '') === 'record_objection' ? 'selected' : '' ?>>Record a community objection received by the Admin</option>
            <?php foreach (['dismiss' => 'Dismiss an objection', 'uphold' => 'Uphold an objection and end appointment', 'confirm' => 'Confirm completed trial', 'remove' => 'Record resignation or removal'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($values['action'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select><?php if (isset($errors['action'])): ?><p class="field__error"><?= e($errors['action']) ?></p><?php endif; ?></div>
        <div class="field"><label class="field__label" for="objection-member">Member who raised the concern (for a new objection)</label><select class="input" id="objection-member" name="member_id"><option value="">Select member</option><?php foreach ($members as $member): ?><option value="<?= e((string) $member['id']) ?>" <?= ($values['member_id'] ?? '') === (string) $member['id'] ? 'selected' : '' ?>><?= e($member['full_name']) ?></option><?php endforeach; ?></select><?php if (isset($errors['member_id'])): ?><p class="field__error"><?= e($errors['member_id']) ?></p><?php endif; ?></div>
        <div class="field"><label class="field__label" for="decision-objection">Objection (for dismiss or uphold)</label><select class="input" name="objection_id" id="decision-objection"><option value="">Select objection</option>
            <?php foreach ($objections as $objection): ?><?php if ($objection['status'] === 'pending'): ?><option value="<?= e((string) $objection['id']) ?>" <?= ($values['objection_id'] ?? '') === (string) $objection['id'] ? 'selected' : '' ?>>#<?= e((string) $objection['id']) ?> · <?= e($objection['full_name']) ?></option><?php endif; ?><?php endforeach; ?>
        </select><?php if (isset($errors['objection_id'])): ?><p class="field__error"><?= e($errors['objection_id']) ?></p><?php endif; ?></div>
        <div class="field"><label class="field__label" for="decision-bond">Bond outcome (for uphold or departure)</label><select class="input" name="bond_decision" id="decision-bond"><option value="">Select outcome</option><option value="return" <?= ($values['bond_decision'] ?? '') === 'return' ? 'selected' : '' ?>>Good standing — return to Sponsor Pool</option><option value="forfeit" <?= ($values['bond_decision'] ?? '') === 'forfeit' ? 'selected' : '' ?>>For cause — forfeit to Reserve Pool</option></select><?php if (isset($errors['bond_decision'])): ?><p class="field__error"><?= e($errors['bond_decision']) ?></p><?php endif; ?></div>
        <div class="field"><label class="field__label" for="decision-reason">Decision notes / performance evaluation</label><textarea class="input" id="decision-reason" name="reason" rows="4"><?= e($values['reason'] ?? '') ?></textarea><?php if (isset($errors['reason'])): ?><p class="field__error"><?= e($errors['reason']) ?></p><?php endif; ?></div>
        <button class="btn btn--primary" type="submit">Save decision</button>
    </form>
</section>
<?php endif; ?>
<section class="section"><h2 class="section__title">Moderator audit history</h2><ul class="row-list">
    <?php foreach ($history as $entry): ?><li class="list-row"><div class="list-row__body"><strong><?= e($entry['incident_type']) ?></strong><p><?= e($entry['notes'] ?? '') ?></p><span class="list-row__meta"><?= e($entry['recorded_at']) ?> · <?= e($entry['recorded_by'] ?? 'Former administrator') ?></span></div></li><?php endforeach; ?>
</ul></section>
<nav class="actions" aria-label="Review pagination">
    <?php if ($page > 1): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page - 1)) ?>">Previous</a><?php endif; ?>
    <?php if (count($objections) === 20 || count($history) === 20): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page + 1)) ?>">Next</a><?php endif; ?>
</nav>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
