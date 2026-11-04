<?php

declare(strict_types=1);

$pageTitle = 'Moderator expense records';
$navActive = 'moderators';
$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';
?>
<header class="page-header"><h1 class="page-header__title">Expense records · <?= e($appointment['full_name']) ?></h1><a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/objections/<?= e((string) $appointment['id']) ?>">Back to appointment</a></header>
<p class="notice notice--info">Record claims checked in person against original receipts. Amounts are cash reimbursements in rupees from operational funds. Mark paid only after cash payment has actually been made. Paid records cannot be edited or deleted.</p>
<section class="section"><h2 class="section__title">Recorded claims</h2>
<?php if ($rows === []): ?><p>No claims on this page.</p><?php endif; ?>
<div class="scroll-x"><table class="data-table"><thead><tr><th>Claim</th><th>Purpose / receipt</th><th>Claimed</th><th>Approved</th><th>Paid</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td>#<?= e((string) $row['id']) ?><?php if ($row['paid_at'] === null): ?> <a href="?page=<?= e((string) $page) ?>&amp;claim=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?></td><td><?= e($row['purpose']) ?><br><?= e($row['receipt_reference']) ?></td><td><?= e((string) $row['amount']) ?></td><td><?= e((string) ($row['approved_amount'] ?? 0)) ?></td><td><?= e($row['paid_at'] ?? 'Not paid') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="form-card form-card--wide"><h2 class="form-card__legend">Record or update a claim</h2>
<form method="post" action="<?= base_url() ?>/admin/moderators/expenses/<?= e((string) $appointment['id']) ?>?page=<?= e((string) $page) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label class="field__label" for="expense-action">Action</label><select class="input" id="expense-action" name="action"><?php foreach (['save' => 'Save checked claim', 'pay' => 'Record cash payment', 'delete' => 'Delete unpaid claim'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($values['action'] ?? 'save') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['action'])): ?><p class="field__error"><?= e($errors['action']) ?></p><?php endif; ?></div>
    <div class="field"><label class="field__label" for="expense-claim">Claim to update (leave blank for new claim)</label><select class="input" id="expense-claim" name="claim_id"><option value="">New claim</option><?php foreach ($rows as $row): ?><?php if ($row['paid_at'] === null): ?><option value="<?= e((string) $row['id']) ?>" <?= ($values['claim_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>>#<?= e((string) $row['id']) ?> · <?= e($row['purpose']) ?></option><?php endif; ?><?php endforeach; ?></select><?php if (isset($errors['claim_id'])): ?><p class="field__error"><?= e($errors['claim_id']) ?></p><?php endif; ?></div>
    <p class="list-row__meta">For a new or corrected claim, enter all details below. Payment and deletion use the selected saved claim.</p>
    <?php foreach (['purpose' => 'Purpose', 'receipt_reference' => 'Original receipt reference', 'amount' => 'Claimed amount (rupees)', 'approved_amount' => 'Approved amount (rupees; zero records a rejected claim)'] as $key => $label): ?>
    <div class="field"><label class="field__label" for="expense-<?= e($key) ?>"><?= e($label) ?></label><input class="input" id="expense-<?= e($key) ?>" name="<?= e($key) ?>" type="<?= in_array($key, ['amount', 'approved_amount'], true) ? 'number' : 'text' ?>" value="<?= e($values[$key] ?? '') ?>"><?php if (isset($errors[$key])): ?><p class="field__error"><?= e($errors[$key]) ?></p><?php endif; ?></div>
    <?php endforeach; ?>
    <button class="btn btn--primary" type="submit">Save expense action</button>
</form></section>
<nav class="actions" aria-label="Expense pagination"><?php if ($page > 1): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page - 1)) ?>">Previous</a><?php endif; ?><?php if (count($rows) === 20): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page + 1)) ?>">Next</a><?php endif; ?></nav>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
