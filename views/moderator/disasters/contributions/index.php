<?php

declare(strict_types=1);

/**
 * Sponsor contributions the Liaison has asked this Moderator to confirm
 * (Plan §14.3). The Moderator states what they actually received so the
 * Liaison has a second account to check the sponsor's claim against.
 *
 * @var array $pending   rows: id, reference, sponsor, claim, recorded
 * @var array $confirmed rows: reference, sponsor, received, ack, status, status_label
 */

$pageTitle = 'Sponsor contributions to confirm';
$navActive = 'disasters';

$chrome = 'moderator';
include __DIR__ . '/../../../../partials/header.php';

$baseUrl = base_url() . '/moderator/disasters/contributions';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/moderator/disasters">Disaster relief</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Sponsor contributions</span>
</nav>

<h1 class="detail__title">Sponsor contributions to confirm</h1>
<p class="page-intro__meta">
    When a sponsor tells the Sponsor Liaison they gave you cash or goods, you are asked to confirm what you
    actually received and attach your acknowledgement. The Liaison checks both accounts before it goes into
    the sponsor's CSR report.
</p>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Waiting for your confirmation</h2>
    </div>

    <?php if ($pending === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">Nothing to confirm</p>
            <p class="empty-state__body">New requests from the Sponsor Liaison appear here.</p>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($pending as $row): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($row['reference']) ?> · <?= e($row['sponsor']) ?></span>
                        <span class="list-row__meta"><?= e($row['claim']) ?></span>
                        <span class="list-row__meta"><?= e($row['recorded']) ?></span>
                    </div>
                    <a class="btn btn--primary" href="<?= e($baseUrl . '/' . $row['id']) ?>/confirm">Confirm receipt</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Already confirmed</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($confirmed as $row): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($row['reference']) ?> · <?= e($row['sponsor']) ?></span>
                    <span class="list-row__meta">Received <?= e($row['received']) ?>  ·  acknowledgement <?= e($row['ack']) ?></span>
                </div>
                <span class="badge badge--<?= e($row['status']) ?>"><?= e($row['status_label']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../../partials/footer.php'; ?>
