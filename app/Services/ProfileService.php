<?php

declare(strict_types=1);

/**
 * A member's own details (Plan §20.1 module 1.1): name, mobile, email, the
 * receive-gifts preference (§11.1), and a change of home address.
 *
 * Contact details change at once. A home address does not: it was verified by
 * the division moderator, so a new one waits with its proof until that
 * moderator approves it (§18.1). The NIC is fixed — it is the identity that was
 * verified, and a mistake there is corrected by the moderator, not the member.
 */
final class ProfileService
{
    private const NAME_MAX = 150;

    private const ADDRESS_MAX = 255;

    private const REASON_MAX = 255;

    public function __construct(
        private PDO $pdo,
        private User $users,
        private UserDivision $memberships,
        private AddressChange $changes,
        private GnDivision $divisions,
        private PhotoStore $documents
    ) {
    }

    /**
     * @param array{full_name:string, phone:string, email:string} $input
     *
     * @throws ValidationException
     */
    public function updateContact(int $userId, array $input): void
    {
        $errors = [];

        $name = trim($input['full_name']);
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            $errors['full_name'] = 'Enter your name as it appears on your NIC.';
        }

        $phone = RegistrationService::normalisePhone($input['phone']);
        if ($phone === null) {
            $errors['phone'] = 'Enter a Sri Lankan mobile number, for example 077 123 4567.';
        } elseif ($this->users->phoneTakenByOther(RegistrationService::phoneDigits($phone), $userId)) {
            $errors['phone'] = 'Another account already uses this mobile number.';
        }

        $email = trim($input['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter your email address, for example you@email.com.';
        } elseif ($this->users->emailTakenByOther($email, $userId)) {
            $errors['email'] = 'Another account already uses this email address.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->users->updateContact($userId, $name, (string) $phone, $email);
    }

    /**
     * Ask to move home address. Any earlier pending request is withdrawn — the
     * newest one is what the moderator reviews.
     *
     * @param list<array<string, mixed>> $proofUpload
     *
     * @throws ValidationException
     */
    public function requestAddressChange(int $userId, string $newAddress, array $proofUpload): void
    {
        $newAddress = trim($newAddress);
        $account    = $this->users->findAccount($userId);
        $home       = $this->users->findWithDivision($userId);
        $divisionId = $home !== null && $home['membership_status'] === 'active' ? (int) $home['division_id'] : null;
        $errors     = [];

        if ($newAddress === '' || mb_strlen($newAddress) > self::ADDRESS_MAX) {
            $errors['address'] = 'Enter your new home address.';
        } elseif ($account !== null && $newAddress === trim((string) $account['address'])) {
            $errors['address'] = 'That is already your address.';
        }

        $files = PhotoStore::chosen($proofUpload);
        if (count($files) !== 1) {
            $errors['address_proof'] = 'Add one proof of the new address, such as a utility bill.';
        }

        if ($divisionId === null) {
            $errors['address'] = 'Your home community is not verified yet, so your address cannot change.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $proof = $this->documents->storeMany($files, RegistrationService::DOCUMENT_FOLDER, 'address_proof', 1)[0];

        Database::transaction($this->pdo, function () use ($userId, $divisionId, $newAddress, $proof): void {
            $this->changes->withdrawPendingFor($userId);
            $this->changes->create($userId, $divisionId, $newAddress, $proof);
        }, fn () => $this->documents->delete($proof));
    }

    public function setGiftReceive(int $userId, bool $enabled): void
    {
        $this->users->setGiftReceive($userId, $enabled);
    }

    // ── The moderator's side ────────────────────────────────────────────────

    /**
     * @throws AccessDeniedException when the account moderates no division
     *
     * @return list<array<string, mixed>>
     */
    public function addressQueue(int $moderatorId): array
    {
        return $this->changes->pendingForDivision($this->divisions->moderatedByOrFail($moderatorId));
    }

    /**
     * Approve or reject one address change in this moderator's division.
     *
     * @throws ValidationException|AccessDeniedException|RecordNotFoundException
     *
     * @return string the member's name, for the confirmation message
     */
    public function decideAddressChange(int $id, int $moderatorId, bool $approve, ?string $reason): string
    {
        $change = $this->changes->findWithDivision($id);

        if ($change === null) {
            throw new RecordNotFoundException('No such address change.');
        }

        if ((int) $change['moderator_id'] !== $moderatorId || (int) $change['user_id'] === $moderatorId) {
            throw new AccessDeniedException('This request belongs to another division, or is your own.');
        }

        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

        if (!$approve && $reason === null) {
            throw ValidationException::field('reason', 'Tell the member why, so they can send better proof.');
        }

        if ($reason !== null && mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::field('reason', sprintf('Keep the reason to %d characters.', self::REASON_MAX));
        }

        if ($reason !== null && !Validator::hasLetters($reason)) {
            throw ValidationException::field('reason', 'Write the reason in words, so the member knows what to fix.');
        }

        Database::transaction($this->pdo, function () use ($id, $approve, $moderatorId, $reason, $change): void {
            if (!$this->changes->decide($id, $approve ? 'approved' : 'rejected', $moderatorId, $reason)) {
                throw ValidationException::field('form', 'This request has already been decided.');
            }

            if ($approve) {
                $this->users->updateAddress((int) $change['user_id'], (string) $change['new_address']);
                $this->memberships->replaceHomeProof((int) $change['user_id'], (string) $change['proof_file_path']);
            }
        });

        return (string) $change['full_name'];
    }
}
