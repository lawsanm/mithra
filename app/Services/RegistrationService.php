<?php

declare(strict_types=1);

/**
 * Sign-up rules: what a valid application looks like, and what one creates.
 *
 * Everything that could change for a business reason lives here — the shape of
 * an NIC, what counts as a Sri Lankan mobile number, how long a password must
 * be, the two documents an application carries (a photograph of the NIC and a
 * proof of address), and the fact that a new account starts pending
 * (Plan §18.1). The controller does the HTTP work, the models do the SQL
 * (Rules/CONVENTIONS.md §6).
 *
 * The write path ends at 'pending'. Nothing here ever sets a member 'active':
 * that is the moderator's decision, and it lives in VerificationService.
 */
final class RegistrationService
{
    /** Kept for callers and tests; the rule itself lives in PasswordPolicy. */
    public const MIN_PASSWORD = PasswordPolicy::MIN_LENGTH;

    public const MAX_PASSWORD_BYTES = PasswordPolicy::MAX_BYTES;

    /** The fields the first sign-up step collects; an error on one sends the applicant back there. */
    public const DETAIL_FIELDS = ['full_name', 'nic', 'phone', 'email', 'address', 'gn_division_id'];

    /** Public sign-up only ever creates members (§7.4). */
    private const MEMBER_ROLE = 'member';

    /** Sri Lankan mobile numbers are matched on their final nine digits. */
    public const PHONE_DIGITS = 9;

    /**
     * Identity documents are kept apart from item photos: they are served only
     * to the verifying moderator and the Admin (Plan §25.3).
     */
    public const DOCUMENT_FOLDER = 'identity-documents';

    /** How the proof of address is labelled on the membership row. */
    private const ADDRESS_PROOF = 'address';

    private PDO $pdo;
    private User $users;
    private UserDivision $memberships;
    private GnDivision $divisions;
    private PhotoStore $documents;

    public function __construct(
        PDO $pdo,
        User $users,
        UserDivision $memberships,
        GnDivision $divisions,
        PhotoStore $documents
    ) {
        $this->pdo         = $pdo;
        $this->users       = $users;
        $this->memberships = $memberships;
        $this->divisions   = $divisions;
        $this->documents   = $documents;
    }

    /**
     * Create one pending member and their pending home membership.
     *
     * @param array{full_name:string, nic:string, phone:string, email:string,
     *              address:string, gn_division_id:string} $input as typed, already
     *                                                     length-checked by the controller
     * @param string $password     as typed — never trimmed, never logged
     * @param string $confirmation the second password box
     * @param array{nic_photo: list<array<string, mixed>>, address_proof: list<array<string, mixed>>} $uploads
     *                             the two document uploads, normalised from
     *                             the request by the controller
     *
     * @throws ValidationException with one message per offending field
     *
     * @return int the new member's id
     */
    public function register(array $input, string $password, string $confirmation, array $uploads): int
    {
        $errors  = $this->detailErrors($input);
        $errors += PasswordPolicy::errors($password, $confirmation);
        $errors += $this->documentErrors($uploads);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $nic        = self::normaliseNic($input['nic']);
        $phone      = self::normalisePhone($input['phone']);
        $email      = trim($input['email']);
        $divisionId = (int) $input['gn_division_id'];

        $roleId = $this->users->roleIdFor(self::MEMBER_ROLE);

        if ($roleId === null) {
            // Fail closed: without the member role there is no safe role to
            // fall back on (§8).
            throw new RuntimeException('The member role is missing from the roles table.');
        }

        // Stored only once everything else is valid, so a typo in the NIC does
        // not leave orphaned identity documents behind.
        $nicPhoto     = $this->storeDocument($uploads['nic_photo'], 'nic_photo');
        $addressProof = $this->storeDocument($uploads['address_proof'], 'address_proof', [$nicPhoto]);

        return $this->insert([
            'nic_photo_path' => $nicPhoto,
            'role_id'       => $roleId,
            'full_name'     => trim($input['full_name']),
            'nic'           => (string) $nic,
            'phone'         => (string) $phone,
            'email'         => $email === '' ? null : $email,
            'address'       => trim($input['address']),
            'password_hash' => PasswordPolicy::hash($password),
        ], $divisionId, $addressProof);
    }

    /**
     * An NIC in either national format: the old 9 digits plus V or X, or the
     * 12-digit number issued since 2016. Returned upper-cased so two spellings
     * of one NIC cannot both be registered.
     */
    public static function normaliseNic(string $nic): ?string
    {
        $candidate = strtoupper((string) preg_replace('/\s+/', '', $nic));

        if (preg_match('/^\d{9}[VX]$/', $candidate) === 1 || preg_match('/^\d{12}$/', $candidate) === 1) {
            return $candidate;
        }

        return null;
    }

    /**
     * A Sri Lankan mobile number in any of the shapes people write it
     * (077 123 4567, +94 77 123 4567, 0094771234567), stored in one shape so
     * the login lookup and the uniqueness check agree.
     */
    public static function normalisePhone(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        // Strip whichever country prefix was used, then require the national
        // nine digits of a mobile line: 7 followed by eight more.
        foreach (['0094', '94', '0'] as $prefix) {
            if (str_starts_with($digits, $prefix) && strlen($digits) === strlen($prefix) + self::PHONE_DIGITS) {
                $digits = substr($digits, strlen($prefix));
                break;
            }
        }

        if (preg_match('/^7\d{8}$/', $digits) !== 1) {
            return null;
        }

        return sprintf('+94 %s %s %s', substr($digits, 0, 2), substr($digits, 2, 3), substr($digits, 5, 4));
    }

    /**
     * The last nine digits of a stored number — what both the login lookup and
     * the uniqueness check compare on.
     */
    public static function phoneDigits(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        return strlen($digits) <= self::PHONE_DIGITS ? $digits : substr($digits, -self::PHONE_DIGITS);
    }

    // ── Rules ───────────────────────────────────────────────────────────────

    /**
     * What is wrong with the personal and division details, the first step of
     * sign-up: NIC and mobile formats, the email address, the division, and
     * whether any of them is already registered.
     *
     * @param array{nic:string, phone:string, email:string, gn_division_id:string} $input
     *
     * @return array<string, string> field => message; empty when the details are usable
     */
    public function detailErrors(array $input): array
    {
        $errors = [];

        $nic = self::normaliseNic($input['nic']);
        if ($nic === null) {
            $errors['nic'] = 'Enter an NIC as 9 digits and a letter (199012345V) or 12 digits.';
        }

        $phone = self::normalisePhone($input['phone']);
        if ($phone === null) {
            $errors['phone'] = 'Enter a Sri Lankan mobile number, for example 077 123 4567.';
        }

        $email = trim($input['email']);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter an email address, or leave this empty to sign in with your mobile number.';
        }

        $divisionId = (int) $input['gn_division_id'];
        if ($divisionId < 1 || !$this->divisions->isActive($divisionId)) {
            $errors['gn_division_id'] = 'Choose the GN division you live in.';
        }

        // Only ask the database about values that are well-formed: a malformed
        // NIC has no business generating a "taken" message as well.
        return $errors + $this->availabilityErrors($nic, $phone, $email);
    }

    /**
     * Both documents are required, one file each.
     *
     * @param array<string, list<array<string, mixed>>> $uploads
     *
     * @return array<string, string>
     */
    private function documentErrors(array $uploads): array
    {
        $errors = [];
        $labels = [
            'nic_photo'     => 'Add a clear photograph of the front of your NIC.',
            'address_proof' => 'Add a proof of address, such as a utility bill or a Grama Niladhari letter.',
        ];

        foreach ($labels as $field => $message) {
            $files = array_values(array_filter(
                $uploads[$field] ?? [],
                static fn (array $u): bool => ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ));

            if (count($files) !== 1) {
                $errors[$field] = $message;
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $upload
     * @param list<string>               $discardOnFailure documents already stored for this application
     *
     * @throws ValidationException
     */
    private function storeDocument(array $upload, string $field, array $discardOnFailure = []): string
    {
        try {
            $stored = $this->documents->storeMany($upload, self::DOCUMENT_FOLDER, $field, 1);
        } catch (ValidationException $exception) {
            $this->discard($discardOnFailure);

            throw $exception;
        }

        return $stored[0];
    }

    /**
     * @param list<string> $paths
     */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            $this->documents->delete($path);
        }
    }

    /**
     * Which of these identifiers someone already signs in with. Checked before
     * the insert for the message; the unique indexes are what guarantee it.
     *
     * @return array<string, string>
     */
    private function availabilityErrors(?string $nic, ?string $phone, string $email): array
    {
        $errors = [];

        if ($nic !== null && $this->users->nicTaken($nic)) {
            $errors['nic'] = 'This NIC is already registered. Try signing in instead.';
        }

        if ($phone !== null && $this->users->phoneTaken(self::phoneDigits($phone))) {
            $errors['phone'] = 'This mobile number is already registered. Try signing in instead.';
        }

        if ($email !== '' && $this->users->emailTaken($email)) {
            $errors['email'] = 'This email address is already registered. Try signing in instead.';
        }

        return $errors;
    }

    /**
     * Two rows, one transaction: a member with no community is not a member
     * (§8). A unique index tripping here means someone registered the same
     * identifier between the check above and this insert — rare, and reported
     * as the same field message rather than a 500.
     *
     * @param array{role_id:int, full_name:string, nic:string, nic_photo_path:string,
     *              phone:string, email:?string, address:string, password_hash:string} $account
     */
    private function insert(array $account, int $divisionId, string $addressProof): int
    {
        $this->pdo->beginTransaction();

        try {
            $userId = $this->users->create($account);
            $this->memberships->createHome($userId, $divisionId, self::ADDRESS_PROOF, $addressProof);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            $this->discard([$account['nic_photo_path'], $addressProof]);

            if ($exception->getCode() === '23000') {
                throw ValidationException::field(
                    'form',
                    'Those details were registered a moment ago. Try signing in, or check the NIC and mobile number.'
                );
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            $this->discard([$account['nic_photo_path'], $addressProof]);

            throw $exception;
        }

        return $userId;
    }
}
