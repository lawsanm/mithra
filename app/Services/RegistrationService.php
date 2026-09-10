<?php

declare(strict_types=1);

/**
 * Sign-up rules: what a valid application looks like, and what one creates.
 *
 * Everything that could change for a business reason lives here — the shape of
 * an NIC, what counts as a Sri Lankan mobile number, how long a password must
 * be, and the fact that a new account starts pending (Proposal §19.1). The
 * controller does the HTTP work, the models do the SQL (Rules/CONVENTIONS.md §6).
 *
 * The write path ends at 'pending'. Nothing here ever sets a member 'active':
 * that is the moderator's decision, and it lives in VerificationService.
 */
final class RegistrationService
{
    /** Length beats composition rules, so length is all we ask for. */
    public const MIN_PASSWORD = 8;

    /**
     * bcrypt reads 72 bytes and silently ignores the rest — a longer password
     * would be accepted at sign-up and "work" with any 72-byte prefix later.
     * Refusing it is the honest option.
     */
    public const MAX_PASSWORD_BYTES = 72;

    /** Public sign-up only ever creates members (§7.4). */
    private const MEMBER_ROLE = 'member';

    /** Sri Lankan mobile numbers, stored the way the seeded accounts write them. */
    private const PHONE_DIGITS = 9;

    private PDO $pdo;
    private User $users;
    private UserDivision $memberships;
    private GnDivision $divisions;

    public function __construct(PDO $pdo, User $users, UserDivision $memberships, GnDivision $divisions)
    {
        $this->pdo         = $pdo;
        $this->users       = $users;
        $this->memberships = $memberships;
        $this->divisions   = $divisions;
    }

    /**
     * Create one pending member and their pending home membership.
     *
     * @param array{full_name:string, nic:string, phone:string, email:string,
     *              address:string, gn_division_id:string} $input as typed, already
     *                                                     length-checked by the controller
     * @param string $password     as typed — never trimmed, never logged
     * @param string $confirmation the second password box
     *
     * @throws ValidationException with one message per offending field
     *
     * @return int the new member's id
     */
    public function register(array $input, string $password, string $confirmation): int
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

        $errors += $this->passwordErrors($password, $confirmation);

        // Only ask the database about values that are well-formed: a malformed
        // NIC has no business generating a "taken" message as well.
        $errors += $this->availabilityErrors($nic, $phone, $email);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $roleId = $this->users->roleIdFor(self::MEMBER_ROLE);

        if ($roleId === null) {
            // Fail closed: without the member role there is no safe role to
            // fall back on (§8).
            throw new RuntimeException('The member role is missing from the roles table.');
        }

        return $this->insert([
            'role_id'       => $roleId,
            'full_name'     => trim($input['full_name']),
            'nic'           => (string) $nic,
            'phone'         => (string) $phone,
            'email'         => $email === '' ? null : $email,
            'address'       => trim($input['address']),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ], $divisionId);
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
     * @return array<string, string>
     */
    private function passwordErrors(string $password, string $confirmation): array
    {
        if ($password === '') {
            return ['password' => 'Password is required.'];
        }

        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return ['password' => sprintf('Choose a password of at least %d characters.', self::MIN_PASSWORD)];
        }

        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            return ['password' => sprintf('Choose a password of %d characters or fewer.', self::MAX_PASSWORD_BYTES)];
        }

        if ($password !== $confirmation) {
            return ['password_confirmation' => 'The two passwords do not match.'];
        }

        return [];
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
     * @param array{role_id:int, full_name:string, nic:string, phone:string,
     *              email:?string, address:string, password_hash:string} $account
     */
    private function insert(array $account, int $divisionId): int
    {
        $this->pdo->beginTransaction();

        try {
            $userId = $this->users->create($account);
            $this->memberships->createHome($userId, $divisionId);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();

            if ($exception->getCode() === '23000') {
                throw ValidationException::field(
                    'form',
                    'Those details were registered a moment ago. Try signing in, or check the NIC and mobile number.'
                );
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return $userId;
    }
}
