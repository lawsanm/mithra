<?php

declare(strict_types=1);

$pageTitle = 'Moderator appointment history';
$navActive = 'moderators';
$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';
?>
<header class="page-header"><h1 class="page-header__title">Moderator appointment history</h1><a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators">Back to moderators</a></header>
<?php if ($rows === []): ?><p class="notice notice--info">No appointments on this page.</p><?php endif; ?>
<ul class="row-list">
    <?php foreach ($rows as $row): ?>
        <li class="list-row"><div class="list-row__body"><strong><?= e($row['full_name']) ?> · <?= e($row['division_name']) ?></strong><p class="list-row__meta"><?= e($row['status']) ?> · Appointed <?= e($row['appointed_at']) ?> · Bond <?= e($row['bond_status']) ?></p><?php if ($row['ended_at'] !== null): ?><p><?= e($row['ended_at']) ?> · <?= e($row['end_reason']) ?></p><?php endif; ?></div><a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators/objections/<?= e((string) $row['id']) ?>">View record</a></li>
    <?php endforeach; ?>
</ul>
<nav class="actions" aria-label="Appointment history pagination"><?php if ($page > 1): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page - 1)) ?>">Previous</a><?php endif; ?><?php if (count($rows) === 20): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page + 1)) ?>">Next</a><?php endif; ?></nav>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
