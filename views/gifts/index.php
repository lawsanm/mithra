<?php

declare(strict_types=1);

/**
 * Gifting history. Figma: "Gifting — History" (75:128).
 *
 * @var array $tabs  sent / received tabs: label, box, active
 * @var array $caps  daily and annual gifting caps
 * @var array $gifts rows: id, initials, name, note, amount, direction, date
 * @var string $box  sent | received
 * @var int   $page
 * @var bool  $hasNextPage
 * @var array|null $flash
 */

$pageTitle = 'Gifts';
$navActive = 'gifts';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Gifts</h1>
    <a class="btn btn--primary page-header__action" href="<?= base_url() ?>/gifts/new#send-gift" data-modal-open="send-gift">
        Send a gift
    </a>
</header>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<nav class="tabs" aria-label="Gift direction">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="tabs__link<?= !empty($tab['active']) ? ' tabs__link--active' : '' ?>"
            href="<?= base_url() ?>/gifts?box=<?= e(rawurlencode($tab['box'])) ?>"
            <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
        ><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="cap-grid">
    <?php foreach ($caps as $cap): ?>
        <div class="cap-card">
            <span class="cap-card__label"><?= e($cap['label']) ?></span>
            <strong class="cap-card__value"><?= e($cap['value']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<ul class="row-list">
    <?php foreach ($gifts as $gift): ?>
        <li class="txn-row">
            <span class="avatar avatar--sm"><?= e($gift['initials']) ?></span>
            <div class="txn-row__body">
                <span class="txn-row__name"><?= e($gift['name']) ?></span>
                <span class="txn-row__note"><?= e($gift['note']) ?></span>
            </div>
            <span class="txn-row__amount">
                <span class="txn-row__value txn-row__value--<?= e($gift['direction']) ?>"><?= e($gift['amount']) ?></span>
                <span class="txn-row__date"><?= e($gift['date']) ?></span>
            </span>
            <?php if ($box === 'received'): ?>
                <a class="btn btn--ghost" href="<?= base_url() ?>/ratings?rate=gift-<?= e((string) $gift['id']) ?>#rate-review">Rate</a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($gifts === []): ?>
    <p class="empty-state"><?= $box === 'sent' ? 'You have not sent any gifts yet.' : 'No gifts received yet.' ?></p>
<?php endif; ?>

<?php
$pageUrl = static fn (int $target): string => base_url() . '/gifts?' . http_build_query(['box' => $box, 'page' => $target]);
include __DIR__ . '/../../partials/pager.php';
?>

<?php include __DIR__ . '/../../partials/modal-send-gift.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
