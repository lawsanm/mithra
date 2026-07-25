<?php

declare(strict_types=1);

/**
 * Donation requests received for one of my donation listings.
 * Figma: "Donations — Requests Received" (74:92).
 *
 * @var array $donation item name, request count, first_come flag
 * @var array $requests rows: initials, name, meta, message, profile_href
 */

// Sample view data — replaced by the controller once DonationController lands.
$donation ??= [
    'item'          => 'Baby Clothes Bundle',
    'request_count' => '3 requests',
    'first_come'    => false,
];

$requests ??= [
    [
        'initials'     => 'JK',
        'name'         => 'J. Kavipriya',
        'meta'         => 'Trust 98  ·  8 transactions  ·  Kollupitiya',
        'message'      => '“Expecting our second in August — this would help so much. Can collect any evening.”',
        'profile_href' => '/members/2',
    ],
    [
        'initials'     => 'TM',
        'name'         => 'T.H.K. Madushan',
        'meta'         => 'Trust 88  ·  12 transactions  ·  Bambalapitiya',
        'message'      => '“My sister just moved back with her baby. Happy to come to you this weekend.”',
        'profile_href' => '/members/1',
    ],
    [
        'initials'     => 'AA',
        'name'         => 'A. Akalvily',
        'meta'         => 'Trust 96  ·  31 transactions  ·  Kollupitiya',
        'message'      => '“For my niece — we’re setting up on a tight budget. Thank you for donating!”',
        'profile_href' => '/members/3',
    ],
];

$pageTitle = 'Donation requests';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/items">My Items</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($donation['item']) ?></span>
</nav>

<header class="record-head">
    <h1 class="record-head__title">Donation requests — <?= e($donation['item']) ?></h1>
    <span class="badge badge--info">
        <span aria-hidden="true">i</span>
        <?= e($donation['request_count']) ?>
    </span>
</header>

<form class="toggle-field" method="post" action="/donations/1/first-come">
    <?= csrf_field() ?>
    <input
        class="toggle"
        type="checkbox"
        id="first-come"
        name="first_come"
        value="1"
        <?= $donation['first_come'] ? 'checked' : '' ?>
    >
    <label class="toggle-field__label" for="first-come">
        First-come-first-served — <?= $donation['first_come'] ? 'on' : 'off. You choose the recipient.' ?>
    </label>
</form>

<ul class="row-list">
    <?php foreach ($requests as $request): ?>
        <li class="record-card record-card--roomy">
            <span class="avatar avatar--md"><?= e($request['initials']) ?></span>
            <div class="record-card__body">
                <span class="record-card__party"><?= e($request['name']) ?></span>
                <span class="record-card__terms"><?= e($request['meta']) ?></span>
                <p class="record-card__quote"><?= e($request['message']) ?></p>
            </div>
            <a class="btn btn--ghost" href="<?= e($request['profile_href']) ?>">View profile</a>
            <form method="post" action="/donations/1/recipient">
                <?= csrf_field() ?>
                <input type="hidden" name="member" value="<?= e($request['name']) ?>">
                <button class="btn btn--primary" type="submit">Choose recipient</button>
            </form>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
