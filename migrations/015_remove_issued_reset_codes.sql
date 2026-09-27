-- ============================================================================
--  MITHRA — 015 Password resets are emailed links only
--
--  Every account now has an email address, so the one-time reset code a
--  moderator or the Admin issued in person is gone. Any codes still on file
--  are removed, and password_resets loses the issuer column and the
--  'issued_code' channel that only those codes used.
--
--  Run after 014_add_division_province_postal_code.sql.
-- ============================================================================

USE mithra;

DELETE FROM password_resets WHERE channel = 'issued_code';

ALTER TABLE password_resets
  DROP FOREIGN KEY fk_pr_issuer,
  DROP COLUMN issued_by,
  MODIFY channel ENUM('email') NOT NULL;
