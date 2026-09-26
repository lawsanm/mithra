<?php

declare(strict_types=1);

/**
 * Landing page for a visitor who has not signed in. Figma: Common → "Landing
 * Page" (92:120).
 *
 * The figures are counted from the database, never sample numbers. The design's
 * "estimated savings" figure is left out: nothing in Mithra records one.
 *
 * @var array $stats value, label
 */

$stats = $stats ?? [];

$steps = [
    ['List & lend', 'Photograph an item you rarely use, declare its value, and your division moderator approves it.'],
    ['Borrow with points', 'Request items nearby. Points are held in escrow until the item comes back safely.'],
    ['Give & support', 'Donate items, gift points to neighbours, or request an aid grant when times are tight.'],
];

$pillars = [
    ['icon' => 'icon-handshake', 'title' => 'Lend',  'body' => 'Points-based borrowing of everyday items between verified neighbours.'],
    ['icon' => 'icon-gift',      'title' => 'Share', 'body' => 'Small point gifts to say thanks, and a trust score that shows who you are dealing with.'],
    ['icon' => 'icon-heart',     'title' => 'Care',  'body' => 'Donations, quiet aid grants for families in need, and a Disaster Mode for emergencies.'],
];

$faqs = [
    [
        'question' => 'Who can join Mithra?',
        'answer'   => 'Any resident of a Grama Niladhari (GN) division where Mithra runs. You register '
                    . 'with your NIC and proof of address, and your division moderator verifies you '
                    . 'before you can lend or borrow.',
    ],
    [
        'question' => 'Does it cost anything?',
        'answer'   => 'No. Members never pay cash. Borrowing uses points, which you earn by lending, '
                    . 'donating and receiving gifts. Every new verified member starts with a '
                    . '200-point welcome bonus.',
    ],
    [
        'question' => 'Can I buy points or cash them out?',
        'answer'   => 'No. Points are platform credit, not money. Only sponsor companies add points to '
                    . 'the system, one point for every rupee they contribute, and points can never be '
                    . 'turned back into cash.',
    ],
    [
        'question' => 'How do I know I can trust a borrower?',
        'answer'   => 'Everyone is a verified resident of your own division, and every member has a '
                    . 'trust score from 0 to 100 built from real bookings and ratings. It is shown on '
                    . 'every listing and lending screen.',
    ],
    [
        'question' => 'What if something I lent comes back damaged?',
        'answer'   => 'Raise a damage claim from the booking. Simple cases settle between the two '
                    . 'members; anything contested goes to your moderator, who resolves it in person. '
                    . 'Claims are capped at the item’s declared value.',
    ],
    [
        'question' => 'Where does the money come from?',
        'answer'   => 'Sponsor companies contribute under a written agreement. Every movement of '
                    . 'points is written to an append-only ledger and checked every night, and the '
                    . 'transparency dashboard shows the result to members and sponsors.',
    ],
];

$pageTitle = 'Lend · Share · Care';
$navActive = '';
$pageClass = 'page--public';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<section class="landing-hero">
    <div class="landing-hero__copy">
        <h1 class="landing-hero__title">Borrow what you need. Lend what you don't.</h1>
        <p class="landing-hero__lede">
            Mithra is a money-free sharing network for Sri Lankan communities. Lend items to
            neighbours in your GN division, earn points, and borrow what you need — with escrow
            protection and trusted local moderators.
        </p>
        <div class="actions">
            <a class="btn btn--primary" href="<?= base_url() ?>/register">Join your community</a>
            <a class="btn btn--ghost" href="<?= base_url() ?>/how-it-works">See how it works</a>
        </div>
        <span class="landing-hero__tag">No money changes hands — only points</span>
    </div>
    <div class="landing-hero__photo" aria-hidden="true"></div>
</section>

<section class="section">
    <h2 class="section-title">How it works</h2>
    <ol class="step-cards">
        <?php foreach ($steps as $index => [$title, $body]): ?>
            <li class="step-card">
                <span class="step-card__number"><?= e((string) ($index + 1)) ?></span>
                <h3 class="step-card__title"><?= e($title) ?></h3>
                <p class="step-card__body"><?= e($body) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<dl class="stat-band">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-band__item">
            <dt class="stat-band__label"><?= e($stat['label']) ?></dt>
            <dd class="stat-band__value"><?= e($stat['value']) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>

<section class="section home-section" aria-labelledby="pillars-title">
    <h2 class="section__title" id="pillars-title">One platform, three ways to help</h2>
    <ul class="feature-grid">
        <?php foreach ($pillars as $pillar): ?>
            <li class="feature feature--tinted">
                <span class="feature__icon" aria-hidden="true">
                    <svg class="icon"><use href="#<?= e($pillar['icon']) ?>"></use></svg>
                </span>
                <h3 class="feature__title"><?= e($pillar['title']) ?></h3>
                <p class="feature__body"><?= e($pillar['body']) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="section home-section" id="faq" aria-labelledby="faq-title">
    <h2 class="section__title" id="faq-title">Frequently asked questions</h2>
    <div class="row-list">
        <?php foreach ($faqs as $index => $faq): ?>
            <details class="faq"<?= $index === 0 ? ' open' : '' ?>>
                <summary class="faq__question"><?= e($faq['question']) ?></summary>
                <p class="faq__answer"><?= e($faq['answer']) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel home-cta home-section" aria-labelledby="cta-title">
    <h2 class="panel__title" id="cta-title">Ready to meet your neighbours?</h2>
    <p class="home-cta__text">Registration takes a few minutes. Your moderator does the rest.</p>
    <a class="btn btn--primary" href="<?= base_url() ?>/register">Register</a>
</section>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
