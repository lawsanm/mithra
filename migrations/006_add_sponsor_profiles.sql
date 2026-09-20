-- ============================================================================
--  MITHRA — 006 Sponsor profiles for the Liaison's sponsor CRUD
--  (Plan §20.4 module 4.2, §15.1)
--
--  1. sponsors gains the profile fields the onboarding form collects: a
--     contact email, the state of the written agreement and its reference,
--     and the Liaison's internal notes.
--  2. Every sponsor already on file has recorded contributions, and a
--     contribution is only taken under a written agreement (§15.1), so those
--     rows are marked 'signed'.
--  3. Indexes for the list page's search, sort and agreement filter (§9).
--
--  Run after 005_create_account_security.sql.
-- ============================================================================

USE mithra;

ALTER TABLE sponsors
  ADD COLUMN contact_email     VARCHAR(150) NULL AFTER contact_phone,
  ADD COLUMN agreement_status  ENUM('signed','pending','verbal') NOT NULL DEFAULT 'pending' AFTER contact_email,
  ADD COLUMN agreement_details VARCHAR(255) NULL AFTER agreement_status,
  ADD COLUMN internal_notes    VARCHAR(500) NULL AFTER agreement_details,
  ADD COLUMN updated_at        DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- updated_at is set explicitly so the backfill does not look like an edit.
UPDATE sponsors SET agreement_status = 'signed', updated_at = NULL;

ALTER TABLE sponsors
  ADD KEY idx_sp_company (company_name),
  ADD KEY idx_sp_agreement (agreement_status, active),
  ADD KEY idx_sp_created (created_at);
