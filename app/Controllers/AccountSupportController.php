<?php

declare(strict_types=1);

/**
 * Helping members with their accounts (Plan §16.3): the moderator reviews
 * address changes in their division, with the proof.
 *
 * HTTP plumbing only; the rules live in ProfileService. RbacMiddleware has
 * already limited each path to its role.
 */
final class AccountSupportController extends Controller
{
    // ── Address changes (moderator) ─────────────────────────────────────────

    /**
     * GET /moderator/address-changes.
     */
    public function addressChanges(): void
    {
        $this->renderAddressChanges([]);
    }

    /**
     * POST /moderator/address-changes/{id}/approve.
     */
    public function approveAddress(int $id): void
    {
        $this->decideAddress($id, true);
    }

    /**
     * POST /moderator/address-changes/{id}/reject.
     */
    public function rejectAddress(int $id): void
    {
        $this->decideAddress($id, false);
    }

    // ── Plumbing ────────────────────────────────────────────────────────────

    private function decideAddress(int $id, bool $approve): void
    {
        $reason = $this->posted('reason');

        try {
            $name = $this->profiles()->decideAddressChange($id, $this->userId(), $approve, $reason);
        } catch (ValidationException $exception) {
            http_response_code(422);
            $this->renderAddressChanges($exception->errors() + ['for' => (string) $id]);

            return;
        } catch (AccessDeniedException | RecordNotFoundException $exception) {
            $this->notice(404, 'Request not found', 'This address change is not in your division, or it no longer exists.');

            return;
        }

        $this->flash($approve ? "{$name}'s new address is now on file." : "{$name}'s address change was rejected.");
        $this->redirect('/moderator/address-changes');
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderAddressChanges(array $errors): void
    {
        try {
            $rows = $this->profiles()->addressQueue($this->userId());
        } catch (AccessDeniedException $exception) {
            $this->notice(403, 'No queue here', 'This account does not moderate a division.');

            return;
        }

        $this->render('moderator/address-changes/index', [
            'errors'  => $errors,
            'changes' => array_map(static fn (array $row): array => [
                'id'       => (int) $row['id'],
                'name'     => (string) $row['full_name'],
                'initials' => User::initials((string) $row['full_name']),
                'from'     => (string) $row['current_address'],
                'to'       => (string) $row['new_address'],
                'sent'     => date('j M Y', strtotime((string) $row['created_at'])),
                'proof'    => photo_url((string) $row['proof_file_path']),
            ], $rows),
        ]);
    }
}
