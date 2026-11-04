<?php

declare(strict_types=1);

final class LiaisonAssignmentController extends Controller
{
    public function index(): void
    {
        $this->display([], []);
    }

    public function update(): void
    {
        $v = (new Validator($_POST))->required('user_id', 'Account')->integer('user_id', 'Account', 1)->inList('action', 'Action', ['assign', 'remove']);
        if (!$v->passes()) {
            $this->display($v->errors(), $v->values());
            return;
        }
        try {
            (new LiaisonAssignmentService($this->pdo, new User($this->pdo), new Notification($this->pdo)))->assign((int) $v->value('user_id'), $this->userId(), $v->value('action'));
        } catch (ValidationException $exception) {
            $this->display($exception->errors(), $v->values());
            return;
        }
        $this->flash('Sponsor Liaison assignment updated. The account must sign in again to use its new role.');
        $this->redirect('/admin/moderators/liaison');
    }

    private function display(array $errors, array $values): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }
        $this->render('admin/moderators/liaison', ['rows' => (new User($this->pdo))->liaisonAssignmentAccounts($this->page()), 'page' => $this->page(), 'errors' => $errors, 'values' => $values]);
    }
}
