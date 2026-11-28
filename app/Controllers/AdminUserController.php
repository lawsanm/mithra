<?php

declare(strict_types=1);

/** Admin account suspension actions; read screens remain in AdminController. */
final class AdminUserController extends Controller
{
    private AdminUserSuspensionService $suspensions;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->suspensions = new AdminUserSuspensionService(
            $pdo,
            new User($pdo),
            new Booking($pdo),
            new AccountSuspension($pdo),
            new Notification($pdo),
            TrustController::service($pdo)
        );
    }

    public function changeSuspension(int $id): void
    {
        $actionInput = (new Validator($_POST))->inList('action', 'Action', ['suspend', 'reinstate']);
        if (!$actionInput->passes()) {
            $this->flash(implode(' ', $actionInput->errors()), 'error');
            $this->redirect('/admin/users/' . $id);

            return;
        }
        $action = $actionInput->value('action');
        if ($action === 'suspend') {
            $validator = (new Validator($_POST))
                ->required('reason', 'Suspension reason')
                ->maxLength('reason', 'Suspension reason', 255)
                ->words('reason', 'Suspension reason');
            if (!$validator->passes()) {
                $this->flash(implode(' ', $validator->errors()), 'error');
                $this->redirect('/admin/users/' . $id);

                return;
            }
        }

        $this->attempt(function () use ($id, $action): string {
            if ($action === 'suspend') {
                $reason = trim((string) ($_POST['reason'] ?? ''));
                $this->suspensions->suspend($id, $this->userId(), $reason);

                return 'Member account suspended and the reason recorded.';
            }
            if ($action === 'reinstate') {
                $this->suspensions->reinstate($id);

                return 'Member account reinstated. Its suspension history was retained.';
            }

            throw ValidationException::field('form', 'Choose a valid account action.');
        }, '/admin/users/' . $id, ['Member not found', 'Return to Users and choose an available member account.']);
    }
}
