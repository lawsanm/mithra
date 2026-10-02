<?php

declare(strict_types=1);

/**
 * The Sponsor Liaison's sponsor CRUD (Plan §20.4 module 4.2): keep a sponsor
 * company's contact and agreement details current, and deactivate it when the
 * relationship ends. Onboarding, which creates the company together with its
 * login, is SponsorAccountService.
 *
 * Deactivating is the delete. Contributions and the point ledger keep pointing
 * at the row — every point must stay traceable to the sponsor that funded it
 * (§15.3) — so a sponsor is never removed.
 *
 * The controller checks that each field is present and short enough; the rules
 * here are the formats and the ones that need the database.
 */
final class SponsorService
{
    /** Agreement status => label, in the order the forms offer them. */
    public const AGREEMENT_STATUSES = [
        'signed'  => 'Signed agreement on file',
        'pending' => 'Pending signature',
        'verbal'  => 'Verbal agreement only',
    ];

    public function __construct(private Sponsor $sponsors)
    {
    }

    /**
     * @param array<string, string> $input validated form values
     *
     * @throws RecordNotFoundException|ValidationException
     */
    public function update(int $id, array $input): void
    {
        $sponsor = $this->existing($id);
        $current = $sponsor['user_id'] === null ? null : (int) $sponsor['user_id'];

        $profile = $this->validProfile($input, $current, $id);

        $this->sponsors->updateProfile($id, $profile);
    }

    /**
     * The profile to store, keeping the company's current login link: the
     * login is created with the company at onboarding and never changes.
     * Every refused field is reported together, one message per field.
     *
     * @param array<string, string> $input
     *
     * @return array{user_id: ?int, company_name: string, contact_name: ?string, contact_phone: ?string,
     *               contact_email: ?string, agreement_status: string,
     *               agreement_details: ?string, internal_notes: ?string}
     *
     * @throws ValidationException
     */
    private function validProfile(array $input, ?int $currentUserId, int $sponsorId): array
    {
        $errors  = [];
        $profile = [];

        try {
            $profile = self::profile($input);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $name = trim((string) ($input['company_name'] ?? ''));

        if (!isset($errors['company_name']) && $name !== '' && $this->sponsors->nameTaken($name, $sponsorId)) {
            $errors['company_name'] = $name . ' is already on file as a sponsor.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['user_id' => $currentUserId] + $profile;
    }

    /**
     * @return string the deactivated sponsor's company name
     *
     * @throws RecordNotFoundException|ValidationException
     */
    public function deactivate(int $id): string
    {
        $sponsor = $this->existing($id);

        if ((int) $sponsor['active'] === 0) {
            throw ValidationException::field('active', $sponsor['company_name'] . ' is already inactive.');
        }

        $this->sponsors->setActive($id, false);

        return (string) $sponsor['company_name'];
    }

    /**
     * @return string the reactivated sponsor's company name
     *
     * @throws RecordNotFoundException|ValidationException
     */
    public function reactivate(int $id): string
    {
        $sponsor = $this->existing($id);

        if ((int) $sponsor['active'] === 1) {
            throw ValidationException::field('active', $sponsor['company_name'] . ' is already active.');
        }

        $this->sponsors->setActive($id, true);

        return (string) $sponsor['company_name'];
    }

    /**
     * The row to store from a submitted form: optional fields left empty
     * become NULL, and the contact formats are checked. Needs no database.
     *
     * @param array<string, string> $input
     *
     * @return array{company_name: string, contact_name: ?string, contact_phone: ?string,
     *               contact_email: ?string, agreement_status: string,
     *               agreement_details: ?string, internal_notes: ?string}
     *
     * @throws ValidationException
     */
    public static function profile(array $input): array
    {
        $profile = [
            'company_name'      => trim((string) ($input['company_name'] ?? '')),
            'contact_name'      => Validator::optional($input, 'contact_person'),
            'contact_phone'     => Validator::optional($input, 'contact_phone'),
            'contact_email'     => Validator::optional($input, 'contact_email'),
            'agreement_status'  => (string) ($input['agreement_status'] ?? ''),
            'agreement_details' => Validator::optional($input, 'agreement_details'),
            'internal_notes'    => Validator::optional($input, 'internal_notes'),
        ];

        $errors = [];

        if ($profile['company_name'] === '') {
            $errors['company_name'] = 'Company name is required.';
        }

        if ($profile['contact_email'] !== null && filter_var($profile['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact_email'] = 'Enter an email address, for example contact@company.lk.';
        }

        if ($profile['contact_phone'] !== null && preg_match('/^\+?[0-9 ()-]{7,20}$/', $profile['contact_phone']) !== 1) {
            $errors['contact_phone'] = 'Enter a phone number using digits, spaces and an optional leading +.';
        }

        if (!array_key_exists($profile['agreement_status'], self::AGREEMENT_STATUSES)) {
            $errors['agreement_status'] = 'Choose a valid agreement status.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException
     */
    private function existing(int $id): array
    {
        return $this->sponsors->find($id) ?? throw new RecordNotFoundException('No such sponsor.');
    }
}
