-- ============================================================================
--  MITHRA — 010 Lawsan's demo account uses a real inbox
--
--  id 4 (M. Lawsan, Member) moves from lawsan@email.com to lawsanm@gmail.com,
--  so a password-reset email for this account arrives somewhere it can be
--  read during the demo. The password is still "password".
--
--  Run after 009_rename_demo_accounts.sql.
-- ============================================================================

USE mithra;

UPDATE users SET email = 'lawsanm@gmail.com' WHERE id = 4;
