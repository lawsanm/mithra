<?php

declare(strict_types=1);

/**
 * Public home page — what a visitor sees on the Mithra logo before signing in.
 * The copy follows Rules/Mithra_Project_Plan_Revised.md §1, §2 and §4.
 *
 * @var array|null $flash
 */

$steps = [
    [
        'icon'  => 'icon-check-circle',
        'title' => 'Get verified',
        'body'  => 'Register with your NIC and proof of address. Your GN division moderator '
                 . 'verifies you, and you receive a 200-point welcome bonus.',
    ],
    [
        'icon'  => 'icon-package',
        'title' => 'List what you own',
        'body'  => 'Photograph the drill, tent or projector sitting in your cupboard. Neighbours '
                 . 'borrow it and you earn points for every day it is out.',
    ],
    [
        'icon'  => 'icon-handshake',
        'title' => 'Borrow what you need',
        'body'  => 'Spend your points to borrow from verified neighbours in your own division, '
                 . 'hand it back, and rate each other.',
    ],
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

$pageTitle = 'Lend, share and care with your neighbours';
$navActive = 'home';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<section class="hero" aria-labelledby="hero-title">
    <p class="hero__eyebrow">Lend · Share · Care</p>
    <h1 class="hero__title" id="hero-title">Your neighbours already own what you need.</h1>
    <p class="hero__lede">
        Mithra means <em>friend</em>. It helps verified neighbours in the same GN division lend,
        share and give to each other with points instead of cash, so the cupboards of a whole
        neighbourhood become a shared library of things.
    </p>
    <a class="btn btn--primary" href="<?= base_url() ?>/register">Join your community</a>
</section>

<section class="section home-section" aria-labelledby="how-title">
    <h2 class="section__title" id="how-title">How it works</h2>
    <ol class="feature-grid">
        <?php foreach ($steps as $number => $step): ?>
            <li class="feature">
                <span class="feature__icon" aria-hidden="true">
                    <svg class="icon"><use href="#<?= e($step['icon']) ?>"></use></svg>
                </span>
                <h3 class="feature__title"><?= e(($number + 1) . '. ' . $step['title']) ?></h3>
                <p class="feature__body"><?= e($step['body']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

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
