<?php

declare(strict_types=1);

/**
 * What every controller shares: who is signed in, how a page is rendered, and
 * the request, flash, redirect and refusal plumbing (Rules/CONVENTIONS.md §6).
 *
 * Controllers stay HTTP-only: business rules live in services, SQL in models.
 */
abstract class Controller
{
    public function __construct(protected PDO $pdo)
    {
    }

    /** The signed-in account. AuthMiddleware guarantees one outside the sign-in pages. */
    protected function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    protected function role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    protected function signedIn(): bool
    {
        return $this->userId() > 0;
    }

    /** A query-string value as text: '' when missing or not a single value (?q[]=…). */
    protected function queryValue(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
    }

    /** A form value exactly as typed — passwords are never trimmed — or '' when missing. */
    protected function posted(string $field): string
    {
        return is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
    }

    /** The ?page= of a paged list, 1 when absent. */
    protected function page(): int
    {
        return max(1, (int) $this->queryValue('page'));
    }

    /**
     * Render views/<view>.php with $data as local variables.
     *
     * Every page also receives $viewer (the signed-in account shown in the
     * navigation bar) and $flash (a one-shot message left by a redirect). A
     * page that several roles share passes 'chrome' => chrome_for($this->role())
     * so each role sees its own navigation.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): void
    {
        $data['viewer'] = $this->viewer();
        $data['flash']  = $this->takeFlash();

        extract($data, EXTR_SKIP);

        require dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }

    /** A refusal or missing-record page with the right HTTP status. */
    protected function notice(int $status, string $title, string $body): void
    {
        http_response_code($status);

        $this->render('errors/notice', ['noticeTitle' => $title, 'noticeBody' => $body]);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . base_url() . $path, true, 303);
    }

    /** A message shown once, on the next page rendered. */
    protected function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Run one form action and send the member back: the confirmation it
     * returns, or a refused rule as an error message. A record that is missing
     * or someone else's gets the 404 page — never a hint that it exists.
     *
     * @param callable(): string        $action   does the work, returns the confirmation
     * @param array{0: string, 1: string} $notFound the 404 page's title and text
     */
    protected function attempt(callable $action, string $redirect, array $notFound): void
    {
        try {
            $this->flash($action());
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException | AccessDeniedException) {
            $this->notice(404, ...$notFound);

            return;
        }

        $this->redirect($redirect);
    }

    /** The ledger over this connection's pools and wallets, for every service that moves points. */
    protected static function ledgerService(PDO $pdo): LedgerService
    {
        return new LedgerService($pdo, new PointLedger($pdo), new PointPool($pdo), new Wallet($pdo));
    }

    protected function passwordResets(): PasswordResetService
    {
        return new PasswordResetService($this->pdo, new User($this->pdo), new PasswordReset($this->pdo));
    }

    protected function profiles(): ProfileService
    {
        return new ProfileService(
            $this->pdo,
            new User($this->pdo),
            new UserDivision($this->pdo),
            new AddressChange($this->pdo),
            new GnDivision($this->pdo),
            PhotoStore::uploads()
        );
    }

    /** "Good morning, Lawsan" — the greeting a dashboard opens with. */
    protected function greeting(string $who): string
    {
        $hour = (int) date('G');
        $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');

        return sprintf('Good %s, %s', $part, $who);
    }

    /**
     * The signed-in account as the navigation bar and page headings show it,
     * with its home division (empty for staff) and the moderator and liaison it
     * deals with, or null on the signed-out pages. Views name people from here,
     * never from typed-in text.
     *
     * The moderator is the home division's, or — for staff with no division —
     * the platform's first moderator, so no page names someone who does not
     * hold the role.
     *
     * @return array{id: int, name: string, division: string, greeting: string, initials: string, points: string,
     *               bond: string, company: string, moderator: string, liaison: string, unread: int}|null
     */
    private function viewer(): ?array
    {
        $id = $this->userId();

        if ($id <= 0) {
            return null;
        }

        $users   = new User($this->pdo);
        $account = $users->findWithDivision($id) ?? $users->find($id) ?? [];
        $name    = (string) ($account['full_name'] ?? '');
        $wallets = new Wallet($this->pdo);

        $company = $this->role() === 'sponsor'
            ? ((new Sponsor($this->pdo))->companyForUser($id) ?? $name)
            : '';

        return [
            'id'        => $id,
            'name'      => $name,
            'division'  => (string) ($account['division_name'] ?? ''),
            'greeting'  => $this->greeting($company !== '' ? $company : User::shortName($name)),
            'initials'  => User::initials($company !== '' ? $company : $name),
            'points'    => number_format($wallets->balance($id)) . ' pts',
            'bond'      => 'Bond: ' . number_format($wallets->bondLocked($id)) . ' pts',
            'company'   => $company,
            'moderator' => $users->homeModerator($id)['name'] ?? $users->firstNameInRole('moderator') ?? '',
            'liaison'   => $users->firstNameInRole('sponsor_liaison') ?? '',
            'unread'    => (new Notification($this->pdo))->unreadCount($id),
        ];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? ['type' => (string) $flash['type'], 'message' => (string) $flash['message']] : null;
    }
}
