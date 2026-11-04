<?php

declare(strict_types=1);

final class ModeratorExpenseController extends Controller
{
    public function show(int $id): void
    {
        $this->display($id, [], []);
    }

    public function update(int $id): void
    {
        $v = (new Validator($_POST))->inList('action', 'Action', ['save', 'pay', 'delete'])->integer('claim_id', 'Claim', 1);
        if ($v->value('action') === 'save') {
            $v->required('purpose', 'Purpose')->maxLength('purpose', 'Purpose', 255)->words('purpose', 'Purpose')
                ->required('receipt_reference', 'Original receipt reference')->maxLength('receipt_reference', 'Original receipt reference', 100)
                ->required('amount', 'Claimed rupees')->integer('amount', 'Claimed rupees', 1, 100000000)
                ->required('approved_amount', 'Approved rupees')->integer('approved_amount', 'Approved rupees', 0, 100000000);
        }
        if (!$v->passes()) {
            $this->display($id, $v->errors(), $v->values());
            return;
        }
        try {
            (new ModeratorExpenseService($this->pdo, new Moderator($this->pdo), new ModeratorExpenseClaim($this->pdo), new ModeratorConductHistory($this->pdo)))->save($id, $this->userId(), $v->values());
        } catch (ValidationException $exception) {
            $this->display($id, $exception->errors(), $v->values());
            return;
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Appointment not found', 'Choose an appointment from the moderator history.');
            return;
        }
        $this->flash('Expense record saved. No points were moved.');
        $this->redirect('/admin/moderators/expenses/' . $id);
    }

    private function display(int $id, array $errors, array $values): void
    {
        $appointment = (new Moderator($this->pdo))->lifecycle($id);
        if ($appointment === null) {
            $this->notice(404, 'Appointment not found', 'Choose an appointment from moderator history.');
            return;
        }
        if ($errors !== []) {
            http_response_code(422);
        }
        if ($values === [] && $this->queryValue('claim') !== '') {
            $claim = (new ModeratorExpenseClaim($this->pdo))->find((int) $this->queryValue('claim'));
            if ($claim !== null && (int) $claim['moderator_id'] === (int) $appointment['user_id'] && $claim['paid_at'] === null) {
                $values = array_map(static fn ($value): string => (string) $value, $claim);
                $values['claim_id'] = (string) $claim['id'];
                $values['action'] = 'save';
            }
        }
        $this->render('admin/moderators/expenses', ['appointment' => $appointment, 'errors' => $errors, 'values' => $values, 'rows' => (new ModeratorExpenseClaim($this->pdo))->forModerator((int) $appointment['user_id'], $this->page()), 'page' => $this->page()]);
    }
}
