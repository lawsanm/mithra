<?php

declare(strict_types=1);

/**
 * How Mithra works — the rules a newcomer should know before joining. Figma:
 * Common → "About — How It Works" (92:178).
 *
 * @var string $chrome public for a visitor, or the signed-in role's navigation
 */

$points = [
    ['Verified communities', 'You join with your NIC and GN division. A local moderator verifies every member, so you always know who you\'re dealing with.'],
    ['Points, not money', 'Every verified member starts with a welcome bonus of ' . number_format(VerificationService::WELCOME_BONUS) . ' points. Lending earns points; borrowing spends them. Points can\'t be bought or cashed out by members.'],
    ['Escrow protection', 'When a booking is accepted, the borrower\'s points sit in escrow. They\'re released to the lender only when the item is returned in good condition.'],
    ['Photo baselines & fair claims', 'Both parties photograph the item at handover. If damage is claimed at return, the photos plus your moderator settle it in person — capped at declared value.'],
    ['Donations, gifts & aid', 'Give items away for a Donor badge, gift points to neighbours (with caps), or request an aid grant from the sponsor-funded Aid Pool.'],
    ['Sponsors & transparency', 'Local businesses fund the pools as CSR. Every pool balance and contribution is public on the Transparency Dashboard.'],
];

$pageTitle = 'How it works';
$navActive = 'how-it-works';
$pageClass = 'page--public';

$chrome = $chrome ?? 'public';
include __DIR__ . '/../../partials/header.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title">How Mithra works</h1>
    <p class="page-intro__meta">A community lending network built on trust, not money. Here's what makes it tick.</p>
</header>

<ul class="row-list">
    <?php foreach ($points as [$title, $body]): ?>
        <li class="list-row">
            <div class="list-row__body">
                <h2 class="list-row__title"><?= e($title) ?></h2>
                <p class="list-row__meta"><?= e($body) ?></p>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($chrome === 'public'): ?>
    <div class="actions">
        <a class="btn btn--primary" href="<?= base_url() ?>/register">Register — it's free</a>
        <a class="btn btn--ghost" href="<?= base_url() ?>/transparency">View Transparency Dashboard</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
