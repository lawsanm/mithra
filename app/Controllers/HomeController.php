<?php

declare(strict_types=1);

/**
 * The public home page at / — what Mithra is, how it works and the common
 * questions, for a visitor deciding whether to register.
 *
 * A signed-in account has nothing to decide here, so the logo takes it
 * straight to its own home screen instead.
 */
final class HomeController extends Controller
{
    /**
     * GET /.
     */
    public function index(): void
    {
        if ($this->userId() > 0) {
            $this->redirect(home_for($this->role()));

            return;
        }

        $this->render('home/index');
    }
}
