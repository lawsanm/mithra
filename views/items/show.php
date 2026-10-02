<?php

declare(strict_types=1);

/**
 * Item detail. Figma: "Item Detail" (65:16).
 *
 * @var array $item      id, title, category, category_slug, rate, declared_value,
 *                       description, listing_type, photos, status, status_glyph,
 *                       status_label, can_borrow
 * @var array $owner     initials, name, verified, meta, href
 * @var array|null $quote  BookingService::quote() plus from/to, for the dates asked about
 * @var string $quoteError why those dates have no quote
 * @var array $quoteInput the dates and basis as typed
 * @var bool  $modalOpen  render the request dialog open (the no-JavaScript path)
 * @var array $pricing    rate options with totals
 * @var bool  $isOwner   the member is looking at their own listing
 * @var array $calendar  dates a borrower cannot have: kind (Unavailable / Booked), label
 * @var array|null $flash
 */

$item    = $item ?? [];
$owner   = $owner ?? [];
$quote      = $quote ?? null;
$quoteError = $quoteError ?? '';
$quoteInput = $quoteInput ?? ['from' => '', 'to' => '', 'basis' => ''];
$isOwner = $isOwner ?? false;

$pageTitle = $item['title'];
$navActive = $isOwner ? 'items' : 'browse';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <?php if ($isOwner): ?>
        <a class="breadcrumb__link" href="<?= base_url() ?>/items">My Items</a>
    <?php else: ?>
        <a class="breadcrumb__link" href="<?= base_url() ?>/items/browse">Browse Items</a>
    <?php endif; ?>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= base_url() ?>/items/browse?category=<?= e(rawurlencode($item['category_slug'])) ?>"><?= e($item['category']) ?></a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($item['title']) ?></span>
</nav>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="detail">
    <div class="detail__aside">
        <?php if ($item['photos'] === []): ?>
            <span class="thumb gallery__main">No photo</span>
        <?php else: ?>
            <img class="thumb gallery__main thumb__img thumb--item" src="<?= e($item['photos'][0]) ?>" alt="<?= e($item['title']) ?>" decoding="async">
            <?php if (count($item['photos']) > 1): ?>
                <div class="gallery__thumbs">
                    <?php foreach ($item['photos'] as $index => $photo): ?>
                        <img
                            class="thumb gallery__thumb thumb__img thumb--item<?= $index === 0 ? ' gallery__thumb--current' : '' ?>"
                            src="<?= e($photo) ?>"
                            alt=""
                        >
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="detail__main">
        <span class="badge badge--<?= e($item['status']) ?>">
            <span aria-hidden="true"><?= e($item['status_glyph']) ?></span>
            <?= e($item['status_label']) ?>
        </span>

        <h1 class="detail__title"><?= e($item['title']) ?></h1>

        <p class="price-row">
            <span class="price"><?= e($item['rate']) ?></span>
            <span class="price__note"><?= e($item['declared_value']) ?></span>
        </p>

        <?php if ($item['description'] !== ''): ?>
            <p class="detail__prose"><?= e($item['description']) ?></p>
        <?php endif; ?>

        <?php if (($calendar ?? []) !== []): ?>
            <section class="stack" aria-label="Dates not available">
                <p class="field__label">Dates not available</p>
                <ul class="row-list">
                    <?php foreach ($calendar as $range): ?>
                        <li class="line-item">
                            <span class="line-item__label"><?= e($range['label']) ?></span>
                            <span class="badge badge--<?= $range['kind'] === 'Booked' ? 'info' : 'neutral' ?>"><?= e($range['kind']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <hr class="divider detail__divider">

        <?php if ($isOwner): ?>

            <p class="notice notice--info">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
                This is your listing — this is how it looks to your neighbours.
            </p>

            <div class="actions">
                <a class="btn btn--primary" href="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/edit">Edit listing</a>
                <a class="btn btn--ghost" href="<?= base_url() ?>/items">Back to My Items</a>
            </div>

        <?php else: ?>

            <div class="owner-card">
                <span class="avatar avatar--lg"><?= e($owner['initials']) ?></span>
                <div class="owner-card__body">
                    <span class="owner-card__name-row">
                        <span class="owner-card__name"><?= e($owner['name']) ?></span>
                        <?php if ($owner['verified']): ?>
                            <span class="verified-pill">✓ Verified</span>
                        <?php endif; ?>
                    </span>
                    <span class="owner-card__meta"><?= e($owner['meta']) ?></span>
                </div>
                <a class="btn btn--ghost" href="<?= e($owner['href']) ?>">View profile</a>
            </div>

            <?php if ($item['can_borrow']): ?>
                <?php // Checking a price is a read-only GET, so it carries no token. ?>
                <form class="stack" method="get" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>" novalidate>
                    <div class="field-row">
                        <div class="field">
                            <label class="field__label" for="borrow-from">From</label>
                            <input class="input input--date" type="date" id="borrow-from" name="from" value="<?= e($quoteInput['from']) ?>">
                        </div>
                        <div class="field">
                            <label class="field__label" for="borrow-to">To</label>
                            <input class="input input--date" type="date" id="borrow-to" name="to" value="<?= e($quoteInput['to']) ?>">
                        </div>
                    </div>
                    <?php if ($quoteError !== ''): ?>
                        <span class="field__error"><?= e($quoteError) ?></span>
                    <?php endif; ?>

                    <?php if ($quote !== null): ?>
                        <p class="total-row">
                            <span class="total-row__label"><?= e($quote['days'] . ' day' . ($quote['days'] === 1 ? '' : 's') . ' · ' . $quote['basis'] . ' rate · plus ' . $quote['buffer'] . ' pts late buffer') ?></span>
                            <strong class="total-row__value"><?= e(number_format($quote['total'])) ?> pts</strong>
                        </p>
                        <?php if ($quote['nudge']): ?>
                            <p class="notice notice--amber">Over <?= e((string) BookingService::MONTHLY_NUDGE_DAYS) ?> days — the monthly rate is usually better value.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="actions">
                        <button class="btn btn--ghost" type="submit">Check price</button>
                        <?php if ($quote !== null): ?>
                            <a class="btn btn--primary"
                               href="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>?<?= e(http_build_query(['from' => $quote['from'], 'to' => $quote['to'], 'basis' => $quote['basis'], 'request' => '1'])) ?>"
                               data-modal-open="request-borrow">Request to Borrow</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php elseif ($item['listing_type'] === 'donation' && ($donation ?? null) !== null): ?>
                <?php if ($donation['request'] !== null): ?>
                    <p class="notice notice--info"><?= e($donation['request']['label']) ?></p>
                    <div class="actions">
                        <?php if ($donation['handover']): ?>
                            <a class="btn btn--primary" href="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/handover">Go to handover</a>
                        <?php endif; ?>
                        <?php if (in_array($donation['request']['status'], ['pending', 'selected'], true)): ?>
                            <form method="post" action="<?= base_url() ?>/donation-requests/<?= e((string) $donation['request']['id']) ?>/withdraw"
                                data-confirm="Withdraw your request? You can ask again while the donation is open." novalidate>
                                <?= csrf_field() ?>
                                <button class="btn btn--ghost" type="submit">Withdraw request</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php elseif ($donation['open']): ?>
                    <form class="stack" method="post" action="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/requests" novalidate>
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="field__label" for="donation-message">Message to <?= e($owner['name']) ?> (optional)</label>
                            <input class="input" type="text" id="donation-message" name="message" placeholder="Why it would help, and when you can collect…">
                        </div>
                        <div class="actions">
                            <button class="btn btn--primary" type="submit">Request this donation</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="notice notice--warning">This donation is no longer taking requests.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="notice notice--warning">This item is not available to borrow right now.</p>
            <?php endif; ?>

            <p class="notice notice--info">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
                Condition photos are required at handover and at return. Your points are held
                in escrow until the lender confirms the item is back in good condition.
            </p>

        <?php endif; ?>
    </div>
</div>

<?php if (!$isOwner && $item['can_borrow'] && $quote !== null): ?>
    <?php include __DIR__ . '/../../partials/modal-request-borrow.php'; ?>
<?php endif; ?>

<?php
$pageScripts = ['modal.js', 'confirm.js'];
include __DIR__ . '/../../partials/footer.php';
?>
