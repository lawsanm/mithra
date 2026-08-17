<?php

declare(strict_types=1);

/**
 * Admin screens - every action reads from models and renders a view.
 */
final class AdminController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function dashboard(): void
    {
        $userModel = new User($this->pdo);
        $bookingModel = new Booking($this->pdo);
        $disputeModel = new Dispute($this->pdo);
        $poolModel = new PointPool($this->pdo);
        $cronModel = new CronRun($this->pdo);

        $admin = ['name' => $_SESSION['display_name'] ?? 'Admin'];
        $stats = [
            ['label' => 'Total members',     'value' => number_format($userModel->countByStatus('active')),  'note' => '+' . $userModel->countJoinedThisMonth() . ' this month', 'primary' => true],
            ['label' => 'Active bookings',    'value' => (string) $bookingModel->countActive(),              'note' => number_format($poolModel->balance('in_flight')) . ' pts in escrow'],
            ['label' => 'Escalated disputes', 'value' => (string) $disputeModel->countEscalated(),           'note' => $disputeModel->countPastTimer() . ' past 7-day timer'],
            ['label' => 'Points in system',   'value' => number_format($poolModel->totalPoints()),           'note' => 'All wallets + pools', 'primary' => true],
        ];
        $invariant = $cronModel->lastInvariantResult();
        $cronJobs = $cronModel->recentJobs(4);

        include __DIR__ . '/../../views/admin/dashboard/index.php';
    }

    public function divisions(): void
    {
        $divisions = (new GnDivision($this->pdo))->allWithStaff();

        include __DIR__ . '/../../views/admin/divisions/index.php';
    }

    public function divisionShow(int $id): void
    {
        $divisionModel = new GnDivision($this->pdo);
        $division = $divisionModel->findWithStaff($id);
        $stats = $divisionModel->divisionStats($id);

        include __DIR__ . '/../../views/admin/divisions/show.php';
    }

    public function createDivision(): void
    {
        $divisionModel = new GnDivision($this->pdo);

        try {
            $divisionModel->create((string) ($_POST['name'] ?? ''), (string) ($_POST['district'] ?? ''));
            $_SESSION['flash_success'] = 'Division created.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/divisions');
    }

    public function updateDivision(int $id): void
    {
        $divisionModel = new GnDivision($this->pdo);

        try {
            $divisionModel->updateDetails($id, (string) ($_POST['name'] ?? ''), (string) ($_POST['district'] ?? ''));
            $_SESSION['flash_success'] = 'Division updated.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/divisions/' . $id);
    }

    public function archiveDivision(int $id): void
    {
        (new GnDivision($this->pdo))->archive($id);
        $_SESSION['flash_success'] = 'Division archived.';

        header('Location: /admin/divisions');
    }

    public function divisionApprovals(int $id): void
    {
        $divisionModel = new GnDivision($this->pdo);
        $division = $divisionModel->find($id);
        $pending = (new User($this->pdo))->pendingRegistrations($id);
        $approvalStats = $divisionModel->approvalStats($id);

        include __DIR__ . '/../../views/admin/divisions/approvals.php';
    }

    public function moderators(): void
    {
        $modModel = new Moderator($this->pdo);
        $nominations = $modModel->pendingNominations();
        $candidates = $modModel->eligibleCandidates();
        $activeTab = $_GET['tab'] ?? 'nominations';

        include __DIR__ . '/../../views/admin/moderators/index.php';
    }

    public function moderatorPerformance(): void
    {
        $modModel = new Moderator($this->pdo);
        $moderators = $modModel->allWithPerformance();
        $candidates = $modModel->eligibleCandidates();

        include __DIR__ . '/../../views/admin/moderators/performance.php';
    }

    public function appointModerator(int $divisionId): void
    {
        $division = (new GnDivision($this->pdo))->find($divisionId);
        $candidates = (new Moderator($this->pdo))->bootstrapCandidates($divisionId);

        include __DIR__ . '/../../views/admin/moderators/appoint.php';
    }

    public function disputes(): void
    {
        $disputes = (new Dispute($this->pdo))->escalatedList();

        include __DIR__ . '/../../views/admin/disputes/index.php';
    }

    public function disputeShow(int $id): void
    {
        $disputeModel = new Dispute($this->pdo);
        $dispute = $disputeModel->findWithHistory($id);
        $evidence = $disputeModel->evidencePhotos($id);

        include __DIR__ . '/../../views/admin/disputes/show.php';
    }

    public function disasterMode(): void
    {
        $divisions = (new GnDivision($this->pdo))->allWithDisasterStatus();

        include __DIR__ . '/../../views/admin/disaster/index.php';
    }

    public function categories(): void
    {
        $categories = (new ItemCategory($this->pdo))->allWithListingCount();

        include __DIR__ . '/../../views/admin/categories/index.php';
    }

    public function pools(): void
    {
        $poolModel = new PointPool($this->pdo);
        $cronModel = new CronRun($this->pdo);
        $pools = $poolModel->allBalances();
        $invariant = $cronModel->lastInvariantResult();
        $scheduledJobs = $cronModel->scheduledJobs();

        include __DIR__ . '/../../views/admin/pools/index.php';
    }

    public function writeoffs(): void
    {
        $poolModel = new PointPool($this->pdo);
        $writeoffModel = new WriteOff($this->pdo);
        $reserveBalance = $poolModel->balance('reserve');
        $candidates = $writeoffModel->candidates();
        $yearStats = $writeoffModel->yearStats();

        include __DIR__ . '/../../views/admin/pools/writeoffs.php';
    }

    public function policies(): void
    {
        $policies = [];

        include __DIR__ . '/../../views/admin/pools/policies.php';
    }

    public function ledger(): void
    {
        $filter = $_GET['type'] ?? 'all';
        $search = $_GET['q'] ?? '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $entries = (new PointLedger($this->pdo))->adminList($filter, $search, $page);

        include __DIR__ . '/../../views/admin/ledger/index.php';
    }

    public function users(): void
    {
        $search = $_GET['q'] ?? '';
        $division = $_GET['division'] ?? '';
        $status = $_GET['status'] ?? '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $users = (new User($this->pdo))->adminList($search, $division, $status, $page);
        $divisions = (new GnDivision($this->pdo))->allNames();

        include __DIR__ . '/../../views/admin/users/index.php';
    }

    public function cronJobs(): void
    {
        $jobs = (new CronRun($this->pdo))->allJobs();

        include __DIR__ . '/../../views/admin/cron/index.php';
    }

    public function notifications(): void
    {
        $notifications = (new Notification($this->pdo))->forAdmin((int) $_SESSION['user_id'], 7);

        include __DIR__ . '/../../views/admin/notifications/index.php';
    }

    public function settingsProfile(): void
    {
        $admin = (new User($this->pdo))->find((int) $_SESSION['user_id']);
        $activeTab = 'profile';

        include __DIR__ . '/../../views/admin/settings/profile.php';
    }

    public function settingsSecurity(): void
    {
        $activeTab = 'security';

        include __DIR__ . '/../../views/admin/settings/security.php';
    }

    public function settingsNotifications(): void
    {
        $activeTab = 'notifications';

        include __DIR__ . '/../../views/admin/settings/notifications.php';
    }
}
