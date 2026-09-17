<?php

declare(strict_types=1);

/**
 * The pages that introduce Mithra to someone who has not joined yet: the
 * landing page and How It Works. Figma: Common → "Landing Page" (92:120) and
 * "About — How It Works" (92:178).
 *
 * A signed-in account has its own home, so the landing page sends it there.
 */
final class HomeController extends Controller
{
    /**
     * GET /.
     */
    public function index(): void
    {
        if ($this->signedIn()) {
            $this->redirect($this->homeFor($this->role()));

            return;
        }

        $this->render('home/index', [
            'stats' => [
                ['value' => number_format((new User($this->pdo))->countVerifiedMembers()), 'label' => 'verified members'],
                ['value' => number_format((new GnDivision($this->pdo))->countActive()), 'label' => 'GN divisions'],
                ['value' => number_format((new Item($this->pdo))->countShared()), 'label' => 'items shared'],
            ],
        ]);
    }

    /**
     * GET /how-it-works — open to everyone; a signed-in account keeps its own
     * navigation.
     */
    public function howItWorks(): void
    {
        $this->render('home/how-it-works', ['chrome' => chrome_for($this->signedIn() ? $this->role() : null)]);
    }
}
