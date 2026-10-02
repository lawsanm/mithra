<?php

declare(strict_types=1);

/**
 * Gifting (Plan 4.5) — the gift history with its caps, and sending a gift.
 * The rules live in GiftService. Gifts cannot be undone (§11.1), so there is
 * no edit or delete.
 */
final class GiftController extends Controller
{
    public static function service(PDO $pdo): GiftService
    {
        return new GiftService(
            $pdo,
            new Gift($pdo),
            new User($pdo),
            new UserDivision($pdo),
            new Booking($pdo),
            new DamageClaim($pdo),
            new Dispute($pdo),
            new GnDivision($pdo),
            self::ledgerService($pdo),
            new Notification($pdo)
        );
    }

    /**
     * What the Send a gift modal needs, on the Gifts page and the Wallet page.
     *
     * @param array<string, string> $draft  recipient, amount, reason
     * @param array<string, string> $errors per-field messages
     *
     * @return array<string, mixed>
     */
    public static function modalData(PDO $pdo, int $me, array $draft = [], array $errors = []): array
    {
        $gifts     = new Gift($pdo);
        $sentToday = $gifts->sentToday($me);

        return [
            'recipients'    => (new User($pdo))->giftableExcept($me),
            'giftSentToday' => $sentToday,
            'giftRemaining' => max(0, Gift::DAILY_CAP - $sentToday),
            'giftDraft'     => $draft,
            'giftErrors'    => $errors,
            'giftOpen'      => $errors !== [] || ($draft['recipient'] ?? '') !== '',
        ];
    }

    /**
     * GET /gifts (and /gifts/new) — ?box=sent|received&page=N; ?to=ID opens
     * the Send a gift dialog with that member chosen.
     */
    public function index(): void
    {
        $to = (int) $this->queryValue('to');

        $this->renderPage($to > 0 ? ['recipient' => (string) $to] : [], []);
    }

    /**
     * POST /gifts — send one.
     */
    public function store(): void
    {
        $input = new Validator($_POST);
        $input
            ->required('recipient', 'Recipient')->integer('recipient', 'Recipient', 1)
            ->required('amount', 'Amount')->integer('amount', 'Amount', 1)
            ->required('reason', 'Reason')->maxLength('reason', 'Reason', GiftService::REASON_MAX);

        try {
            if (!$input->passes()) {
                throw new ValidationException($input->errors());
            }

            self::service($this->pdo)->send(
                $this->userId(),
                (int) $input->value('recipient'),
                (int) $input->value('amount'),
                $input->value('reason')
            );
        } catch (ValidationException $exception) {
            $this->renderPage($input->values(), $exception->errors());

            return;
        }

        $this->flash('Gift sent. Thank you for looking after your neighbours.');
        $this->redirect('/gifts?box=sent');
    }

    /**
     * @param array<string, string> $draft
     * @param array<string, string> $errors
     */
    private function renderPage(array $draft, array $errors): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $me    = $this->userId();
        $gifts = new Gift($this->pdo);
        $box   = $this->queryValue('box') === 'received' ? 'received' : 'sent';
        $page  = $this->page();
        $total = $gifts->countForMember($me, $box);

        $this->render('gifts/index', [
            'tabs' => [
                ['label' => 'Sent (' . $gifts->countForMember($me, 'sent') . ')',         'box' => 'sent',     'active' => $box === 'sent'],
                ['label' => 'Received (' . $gifts->countForMember($me, 'received') . ')', 'box' => 'received', 'active' => $box === 'received'],
            ],
            'box'         => $box,
            'page'        => $page,
            'hasNextPage' => $page * GiftService::PER_PAGE < $total,
            'caps' => [
                ['label' => 'Sent today',     'value' => $gifts->sentToday($me) . ' / ' . Gift::DAILY_CAP . ' pts daily cap'],
                ['label' => 'Sent this year', 'value' => $gifts->sentThisYear($me) . ' / ' . Gift::ANNUAL_CAP . ' pts annual cap'],
            ],
            'gifts' => array_map(
                static fn (array $gift): array => [
                    'id'        => (int) $gift['id'],
                    'initials'  => User::initials((string) $gift['counterparty']),
                    'name'      => (string) $gift['counterparty'],
                    'note'      => '“' . $gift['reason'] . '”',
                    'amount'    => ($box === 'sent' ? '−' : '+') . $gift['amount'] . ' pts',
                    'direction' => $box === 'sent' ? 'out' : 'in',
                    'date'      => date('j M Y', strtotime((string) $gift['sent_at'])),
                ],
                $gifts->pageForMember($me, $box, $page, GiftService::PER_PAGE)
            ),
        ] + self::modalData($this->pdo, $me, $draft, $errors));
    }
}
