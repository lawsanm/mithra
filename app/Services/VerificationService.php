<?php

declare(strict_types=1);

/**
 * Member verification: the moderator's decision on a home-membership
 * application (Proposal §19.1, module 1.2).
 *
 * This is the other half of sign-up. Registration leaves an account pending
 * and unable to sign in; approval here is what lets that member through
 * AuthService. Both steps of an approval — the membership row and the account
 * status — move together or not at all.
 *
 * Every method takes the acting moderator's id and refuses an application from
 * a division they do not moderate: an id in a URL is never trusted (§8).
 */
final class VerificationService
{
    /** Queue filters, as the pills on the screen offer them. */
    public const FILTERS = ['', 'pending', 'active', 'rejected'];

    private User $users;
    private UserDivision $memberships;
    private GnDivision $divisions;
    private Wallet $wallets;
    private PDO $pdo;

    public function __construct(PDO $pdo, User $users, UserDivision $memberships, GnDivision $divisions, Wallet $wallets)
    {
        $this->pdo         = $pdo;
        $this->users       = $users;
        $this->memberships = $memberships;
        $this->divisions   = $divisions;
        $this->wallets     = $wallets;
    }

    /**
     * The division this moderator reviews for.
     *
     * @throws AccessDeniedException when they moderate nowhere — an account
     *                               that is not a moderator has no queue
     */
    public function divisionFor(int $moderatorId): int
    {
        $divisionId = $this->divisions->moderatedBy($moderatorId);

        if ($divisionId === null) {
            throw new AccessDeniedException('This account does not moderate a division.');
        }

        return $divisionId;
    }

    /**
     * Every application in this moderator's division, optionally filtered.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(int $moderatorId, string $filter): array
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : '';

        return $this->memberships->queueForDivision($this->divisionFor($moderatorId), $filter);
    }

    public function pendingCount(int $moderatorId): int
    {
        return $this->memberships->countPendingForDivision($this->divisionFor($moderatorId));
    }

    /**
     * One application this moderator is entitled to read.
     *
     * @throws RecordNotFoundException when no such application exists
     * @throws AccessDeniedException   when it belongs to another division
     *
     * @return array<string, mixed>
     */
    public function review(int $id, int $moderatorId): array
    {
        $application = $this->memberships->findForReview($id);

        if ($application === null) {
            throw new RecordNotFoundException('No such verification.');
        }

        if ((int) $application['moderator_id'] !== $moderatorId) {
            throw new AccessDeniedException('This application belongs to another division.');
        }

        return $application;
    }

    /**
     * Approve one membership: the community lets them in, and the account they
     * registered with becomes one they can sign in to.
     *
     * @throws ValidationException when the application was already decided
     *
     * @return string the applicant's name, for the confirmation message
     */
    public function approve(int $id, int $moderatorId): string
    {
        $application = $this->pendingOrFail($id, $moderatorId);
        $memberId    = (int) $application['user_id'];

        $this->pdo->beginTransaction();

        try {
            if (!$this->memberships->decide($id, $moderatorId, 'active')) {
                // Another moderator decided it between the read and this
                // write. Fail closed rather than approve twice.
                throw $this->alreadyDecided();
            }

            $this->users->markActive($memberId);
            $this->wallets->openFor($memberId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return (string) $application['full_name'];
    }

    /**
     * Reject one application. The membership is closed; the account stays
     * pending and signs in nowhere, because `users.status` has no rejected
     * state to move it to — AuthService reads the membership for the refusal
     * message instead.
     *
     * @return string the applicant's name
     */
    public function reject(int $id, int $moderatorId): string
    {
        $application = $this->pendingOrFail($id, $moderatorId);

        if (!$this->memberships->decide($id, $moderatorId, 'rejected')) {
            throw $this->alreadyDecided();
        }

        return (string) $application['full_name'];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingOrFail(int $id, int $moderatorId): array
    {
        $application = $this->review($id, $moderatorId);

        if ($application['status'] !== 'pending') {
            throw $this->alreadyDecided();
        }

        return $application;
    }

    private function alreadyDecided(): ValidationException
    {
        return ValidationException::field('form', 'This application has already been decided.');
    }
}
