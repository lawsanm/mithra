<?php

declare(strict_types=1);

/**
 * Help & FAQ. Figma: "Help / FAQ" (97:241).
 *
 * @var string $query     current help search term
 * @var array  $faqs      question, answer, open
 * @var array  $moderator name and contact line for the division moderator
 */

// Sample view data — replaced by the controller once HelpController lands.
$query ??= '';

$faqs ??= [
    [
        'question' => 'How do points work? Can I buy or cash out points?',
        'answer'   => 'Points are earned by lending, donating and receiving gifts, and spent by '
                    . 'borrowing. Members can’t buy or cash out points — only sponsors add points '
                    . 'to the system, and everything is tracked on the Transparency Dashboard.',
        'open'     => true,
    ],
    [
        'question' => 'What happens if an item I borrowed gets damaged?',
        'answer'   => 'Raise a damage claim from the booking. Simple cases settle between the two '
                    . 'members; anything contested goes to your GN division moderator, who arranges '
                    . 'an in-person resolution. Claims are capped at the item’s declared value.',
        'open'     => false,
    ],
    [
        'question' => 'How long does moderator verification take?',
        'answer'   => 'Most listings and address changes are reviewed within 48 hours. You are '
                    . 'notified as soon as your moderator decides.',
        'open'     => false,
    ],
    [
        'question' => 'What are the gifting caps and why do they exist?',
        'answer'   => 'Gifts are capped at 35 pts per day and 500 pts per year. The caps keep '
                    . 'points circulating as thanks between neighbours rather than being pooled '
                    . 'into a single account.',
        'open'     => false,
    ],
    [
        'question' => 'Can I use Mithra outside my home GN division?',
        'answer'   => 'Yes — request a temporary community from Settings. With proof of stay and '
                    . 'verification by that division’s moderator you can lend and borrow there for '
                    . 'six months, while keeping your home membership.',
        'open'     => false,
    ],
];

$moderator ??= [
    'line' => 'Your moderator, A. Akalvily, can help with verification, disputes and anything '
            . 'division-specific.',
];

$pageTitle = 'Help & FAQ';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Help &amp; FAQ</h1>

<?php // Read-only GET search: no CSRF token, so it never lands in the URL. ?>
<form method="get" action="/help" role="search">
    <label class="visually-hidden" for="help-search">Search help articles</label>
    <input
        class="input input--search"
        type="search"
        id="help-search"
        name="q"
        value="<?= e($query) ?>"
        placeholder="Search help articles…"
    >
</form>

<div class="row-list">
    <?php foreach ($faqs as $faq): ?>
        <details class="faq"<?= $faq['open'] ? ' open' : '' ?>>
            <summary class="faq__question"><?= e($faq['question']) ?></summary>
            <p class="faq__answer"><?= e($faq['answer']) ?></p>
        </details>
    <?php endforeach; ?>
</div>

<section class="panel">
    <h2 class="panel__title">Still stuck?</h2>
    <div class="help-cta">
        <p class="help-cta__text"><?= e($moderator['line']) ?></p>
        <a class="btn btn--ghost" href="/messages/new?to=moderator">Contact moderator</a>
    </div>
</section>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
