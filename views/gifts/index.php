<?php

declare(strict_types=1);

/**
 * Gifting history. Figma: "Gifting — History" (75:128).
 *
 * @var array $tabs  sent / received tabs: label, box, active
 * @var array $caps  daily and annual gifting caps
 * @var array $gifts rows: initials, name, note, amount, direction, date
 */

$pageTitle = 'Gifts';
$navActive = 'gifts';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Gifts</h1>
    <button class="btn btn--primary page-header__action" type="button" data-modal-open="send-gift">
        Send a gift
    </button>
</header>

<nav class="tabs" aria-label="Gift direction">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="tabs__link<?= !empty($tab['active']) ? ' tabs__link--active' : '' ?>"
            href="/gifts?box=<?= e(rawurlencode($tab['box'])) ?>"
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
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../partials/modal-send-gift.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
