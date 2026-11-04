<?php

declare(strict_types=1);

final class ModeratorManagementController extends Controller
{
    private ModeratorLifecycleService $lifecycle;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->lifecycle = new ModeratorLifecycleService($pdo, new Moderator($pdo), new ModeratorObjection($pdo), new ModeratorConductHistory($pdo), new GnDivision($pdo), new User($pdo), new ModeratorCandidate($pdo), new Wallet($pdo), self::ledgerService($pdo), new Notification($pdo));
    }

    public function show(int $id): void
    {
        $this->renderAppointment($id, [], []);
    }

    public function update(int $id): void
    {
        $validator = (new Validator($_POST))
            ->inList('action', 'Action', ['record_objection', 'dismiss', 'uphold', 'confirm', 'remove'])
            ->required('reason', 'Decision notes')->maxLength('reason', 'Decision notes', 255)->words('reason', 'Decision notes')
            ->integer('objection_id', 'Objection', 1)->integer('member_id', 'Member', 1);
        if (!$validator->passes()) {
            $this->renderAppointment($id, $validator->errors(), $validator->values());
            return;
        }
        try {
            $this->lifecycle->decide($id, $this->userId(), $validator->values() + ['objection_id' => '', 'bond_decision' => '', 'member_id' => '']);
        } catch (ValidationException $exception) {
            $this->renderAppointment($id, $exception->errors(), $validator->values());
            return;
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Appointment not found', 'Choose an appointment from Moderator management.');
            return;
        }
        $this->flash('The appointment decision has been saved.');
        $this->redirect('/admin/moderators/objections/' . $id);
    }

    public function history(): void
    {
        $this->render('admin/moderators/history', ['rows' => (new Moderator($this->pdo))->historyPage($this->page()), 'page' => $this->page()]);
    }

    private function renderAppointment(int $id, array $errors, array $values): void
    {
        $model = new Moderator($this->pdo);
        $appointment = $model->lifecycle($id);
        if ($appointment === null) {
            $this->notice(404, 'Appointment not found', 'Choose an appointment from Moderator management.');
            return;
        }
        if ($errors !== []) {
            http_response_code(422);
        }
        $this->render('admin/moderators/manage', [
            'appointment' => $appointment, 'errors' => $errors, 'values' => $values,
            'objections' => (new ModeratorObjection($this->pdo))->pageForAssignment($id, $this->page()),
            'history' => (new ModeratorConductHistory($this->pdo))->forModerator((int) $appointment['user_id'], $this->page()),
            'page' => $this->page(),
            'members' => (new ModeratorCandidate($this->pdo))->candidatesForDivision((int) $appointment['gn_division_id']),
        ]);
    }
}
