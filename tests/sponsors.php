<?php

declare(strict_types=1);

/**
 * Sponsor profile rules that need no database (Plan §20.4 module 4.2): what a
 * submitted onboarding or edit form becomes, and which values are refused.
 *
 *     C:\xampp\php\php.exe tests\sponsors.php
 *
 * Name uniqueness, deactivation and the list's search, sort and filter write
 * to or read from MySQL and are exercised against a running local database.
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param array<string, string> $input
 *
 * @return array<string, string> field => message, empty when the input is accepted
 */
function refusals(array $input): array
{
    try {
        SponsorService::profile($input);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

$valid = [
    'company_name'      => '  Northwind Co  ',
    'contact_person'    => 'T.H.K. Madushan',
    'contact_phone'     => '+94 11 234 5678',
    'contact_email'     => 'contact@northwind.lk',
    'agreement_status'  => 'signed',
    'agreement_details' => 'CSR-2026-014, quarterly',
    'internal_notes'    => 'Prefers email.',
];

// A complete form is stored with the database's column names, trimmed.
$profile = SponsorService::profile($valid);
check($profile['company_name'] === 'Northwind Co', 'Company name must be trimmed.');
check($profile['contact_name'] === 'T.H.K. Madushan', 'Contact person must map to contact_name.');
check($profile['contact_email'] === 'contact@northwind.lk', 'Email must be kept.');
check($profile['agreement_status'] === 'signed', 'Agreement status must be kept.');
check(array_keys($profile) === ['company_name', 'contact_name', 'contact_phone', 'contact_email',
    'agreement_status', 'agreement_details', 'internal_notes'], 'Profile must hold exactly the stored columns.');
$checks += 5;

// Optional fields left empty are stored as NULL, not as empty strings.
$minimal = SponsorService::profile(['company_name' => 'Texa', 'agreement_status' => 'pending',
    'contact_person' => '   ', 'contact_email' => '', 'contact_phone' => '']);
foreach (['contact_name', 'contact_phone', 'contact_email', 'agreement_details', 'internal_notes'] as $column) {
    check($minimal[$column] === null, "Empty $column must be stored as NULL.");
    $checks++;
}

// Every agreement status the forms offer is accepted; nothing else is.
foreach (array_keys(SponsorService::AGREEMENT_STATUSES) as $status) {
    check(refusals(['agreement_status' => $status] + $valid) === [], "$status must be accepted.");
    $checks++;
}
check(array_keys(SponsorService::AGREEMENT_STATUSES) === ['signed', 'pending', 'verbal'], 'Statuses must match the migration ENUM.');
foreach (['', 'Signed', 'cancelled', 'signed '] as $status) {
    check(isset(refusals(['agreement_status' => $status] + $valid)['agreement_status']), "Status '$status' must be refused.");
    $checks++;
}
$checks++;

// The company name is required.
foreach (['', '   '] as $name) {
    check(isset(refusals(['company_name' => $name] + $valid)['company_name']), 'A blank company name must be refused.');
    $checks++;
}

// Email formats.
foreach (['contact@northwind', 'not an email', 'a@b@c.lk'] as $email) {
    check(isset(refusals(['contact_email' => $email] + $valid)['contact_email']), "Email '$email' must be refused.");
    $checks++;
}

// Phone formats: digits, spaces, brackets and hyphens, an optional leading +.
foreach (['+94 11 234 5678', '011-2345678', '(011) 234 5678', '0771234567'] as $phone) {
    check(refusals(['contact_phone' => $phone] + $valid) === [], "Phone '$phone' must be accepted.");
    $checks++;
}
foreach (['12345', 'call me', '+94 11 234 5678 ext 9', '++94112345678'] as $phone) {
    check(isset(refusals(['contact_phone' => $phone] + $valid)['contact_phone']), "Phone '$phone' must be refused.");
    $checks++;
}

// Several problems are reported together, one message per field.
$errors = refusals(['company_name' => '', 'contact_email' => 'x', 'contact_phone' => 'x', 'agreement_status' => 'x']);
check(array_keys($errors) === ['company_name', 'contact_email', 'contact_phone', 'agreement_status'], 'Every bad field must be reported.');
$checks++;

echo 'Passed: ' . $checks . " sponsor checks — profile mapping, agreement statuses and contact formats.\n";
