<?php

declare(strict_types=1);

/**
 * The Sponsor Liaison onboards a company together with its sponsor login
 * (Plan §15.10), so the company can sign in to its CSR dashboard, reports and
 * branding.
 *
 * Sponsors never register themselves: the Liaison agrees the written terms
 * offline (§15.1), then creates the company and a login in the contact
 * person's name, active at once, with a starting password the Liaison hands
 * over in person.
 *
 * The controller checks presence and length; the formats, uniqueness and the
 * two-row write live here (Rules/CONVENTIONS.md §6).
 */
final class SponsorAccountService
{
    private const SPONSOR_ROLE = 'sponsor';

    private PDO $pdo;
    private User $users;
    private Sponsor $sponsors;

    public function __construct(PDO $pdo, User $users, Sponsor $sponsors)
    {
        $this->pdo      = $pdo;
        $this->users    = $users;
        $this->sponsors = $sponsors;
    }

    /**
     * Create an active sponsor profile and an active sponsor login linked to it.
     *
     * The login belongs to the contact person, so their name and the contact
     * email become the account's name and sign-in email.
     *
     * @param array<string, string> $input the onboarding form's values, with
     *                                     login_nic, login_phone and login_address
     * @param string $password     the starting password the Liaison set
     * @param string $confirmation the second password box
     *
     * @throws ValidationException with every offending field at once
     *
     * @return int the new sponsor profile's id
     */
    public function onboardWithLogin(array $input, string $password, string $confirmation): int
    {
        $errors  = [];
        $profile = null;

        try {
            $profile = SponsorService::profile($input);
        } catch (ValidationException $exception) {
            $errors += $exception->errors();
        }

        if ($profile !== null && $this->sponsors->nameTaken($profile['company_name'])) {
            $errors['company_name'] = $profile['company_name'] . ' is already on file as a sponsor.';
        }

        $name = trim((string) ($input['contact_person'] ?? ''));
        if ($name === '') {
            $errors['contact_person'] = 'Enter the contact person — the login is in their name.';
        }

        $email = trim((string) ($input['contact_email'] ?? ''));
        if ($email === '' && !isset($errors['contact_email'])) {
            $errors['contact_email'] = 'Enter the contact email — the sponsor signs in with it.';
        }

        $nic = RegistrationService::normaliseNic((string) ($input['login_nic'] ?? ''));
        if ($nic === null) {
            $errors['login_nic'] = 'Enter an NIC as 9 digits and a letter (199012345V) or 12 digits.';
        }

        $phone = RegistrationService::normalisePhone((string) ($input['login_phone'] ?? ''));
        if ($phone === null) {
            $errors['login_phone'] = 'Enter a Sri Lankan mobile number, for example 077 123 4567.';
        }

        $address = trim((string) ($input['login_address'] ?? ''));
        if ($address === '') {
            $errors['login_address'] = 'Enter the company address.';
        }

        $errors += PasswordPolicy::errors($password, $confirmation);
        $errors += $this->availabilityErrors($nic, $phone, isset($errors['contact_email']) ? '' : $email);

        if ($errors !== [] || $profile === null) {
            throw new ValidationException($errors);
        }

        $roleId = $this->users->roleIdFor(self::SPONSOR_ROLE)
            ?? throw new RuntimeException('The sponsor role is missing from the roles table.');

        return $this->insert(
            [
                'role_id'        => $roleId,
                'full_name'      => $name,
                'nic'            => (string) $nic,
                'nic_photo_path' => null,
                'phone'          => (string) $phone,
                'email'          => $email,
                'address'        => $address,
                'password_hash'  => PasswordPolicy::hash($password),
            ],
            $profile
        );
    }

    /**
     * Which of these identifiers someone already signs in with, keyed to the
     * onboarding form's fields. Checked before the insert for the message; the
     * unique indexes are what guarantee it.
     *
     * @return array<string, string>
     */
    private function availabilityErrors(?string $nic, ?string $phone, string $email): array
    {
        $errors = [];

        if ($nic !== null && $this->users->nicTaken($nic)) {
            $errors['login_nic'] = 'This NIC is already registered to another account.';
        }

        if ($phone !== null && $this->users->phoneTaken(RegistrationService::phoneDigits($phone))) {
            $errors['login_phone'] = 'This mobile number is already registered to another account.';
        }

        if ($email !== '' && $this->users->emailTaken($email)) {
            $errors['contact_email'] = 'This email address is already registered to another account.';
        }

        return $errors;
    }

    /**
     * Two rows, one transaction: a sponsor login with no company behind it
     * would sign in to an empty dashboard.
     *
     * @param array<string, mixed>  $account
     * @param array<string, ?string> $profile
     */
    private function insert(array $account, array $profile): int
    {
        try {
            return Database::transaction($this->pdo, function () use ($account, $profile): int {
                $userId = $this->users->create($account);
                $this->users->markActive($userId);

                return $this->sponsors->createWithLogin($profile, $userId);
            });
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            throw ValidationException::field('form', 'Those details were registered a moment ago. Check the company name, NIC, mobile number and email.');
        }
    }
}
