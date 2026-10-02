<?php

declare(strict_types=1);

/**
 * Notifications (Plan 4.6) — the list every role's bell leads to, paged,
 * filtered, marked read and dismissed. Every service writes its own types
 * through Notification::push(); this only reads and tidies them.
 *
 * Every query carries the signed-in account's id, so nobody can read or
 * change another account's notifications.
 */
final class NotificationController extends Controller
{
    private Notification $notifications;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->notifications = new Notification($pdo);
    }

    /**
     * GET /notifications (and the sponsor's /sponsor/notifications).
     */
    public function index(): void
    {
        $me    = $this->userId();
        $group = is_string($_GET['type'] ?? null) && isset(Notification::GROUPS[$_GET['type']]) ? $_GET['type'] : '';
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->notifications->countForMember($me, $group);

        $filters = [];
        foreach (['' => 'All', 'bookings' => 'Bookings & donations', 'gifts-aid' => 'Gifts & aid', 'system' => 'System'] as $slug => $label) {
            $filters[] = ['label' => $label, 'slug' => $slug, 'active' => $group === $slug];
        }

        $sponsor = $this->role() === 'sponsor';

        $this->render($sponsor ? 'sponsor/notifications/index' : 'notifications/index', [
            'chrome'        => chrome_for($this->role()),
            'filters'       => $filters,
            'group'         => $group,
            'page'          => $page,
            'hasNextPage'   => $page * Notification::PER_PAGE < $total,
            'unread'        => $this->notifications->unreadCount($me),
            'notifications' => $this->notifications->displayForMember($me, $group, $page),
        ]);
    }

    /**
     * POST /notifications/{id}/read — mark it read, then follow its link.
     */
    public function read(int $id): void
    {
        $notification = $this->notifications->findForMember($id, $this->userId());

        if ($notification === null) {
            $this->notice(404, 'Notification not found', 'It may have been dismissed already.');

            return;
        }

        $this->notifications->markRead($id, $this->userId());
        $this->redirect((string) $notification['path']);
    }

    /**
     * POST /notifications/read-all.
     */
    public function readAll(): void
    {
        $count = $this->notifications->markAllRead($this->userId());
        $this->flash($count === 0 ? 'Nothing unread.' : $count . ' notification' . ($count === 1 ? '' : 's') . ' marked as read.');
        $this->redirect($this->role() === 'sponsor' ? '/sponsor/notifications' : '/notifications');
    }

    /**
     * POST /notifications/{id}/delete — dismiss one.
     */
    public function destroy(int $id): void
    {
        if (!$this->notifications->deleteOwned($id, $this->userId())) {
            $this->notice(404, 'Notification not found', 'It may have been dismissed already.');

            return;
        }

        $this->flash('Notification dismissed.');
        $this->redirect($this->role() === 'sponsor' ? '/sponsor/notifications' : '/notifications');
    }

    /**
     * GET /notifications/unread-count — JSON for the bell, polled every 8
     * seconds by public/js/polling.js (§21.4). One indexed COUNT.
     */
    public function unreadCount(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        print json_encode(['unread' => $this->notifications->unreadCount($this->userId())], JSON_THROW_ON_ERROR);
    }
}
