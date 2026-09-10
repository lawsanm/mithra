-- ============================================================================
--  MITHRA — 003 Identity constraints for sign-in and sign-up
--
--  Sign-in accepts an email address or a mobile number, so each has to name at
--  most one account. Migration 001 left both unconstrained: `nic` was the only
--  unique identifier, and phone numbers are stored as people write them
--  ("+94 77 123 4567"), which no plain index can compare.
--
--  1. phone_digits — the national nine digits of `phone`, generated and kept in
--     step by MySQL. It is what AuthService compares on, so the login lookup is
--     an index seek instead of the table scan the inline REPLACE() chain cost
--     (§9), and it makes "same number, different spacing" a duplicate.
--  2. Unique keys on email and phone_digits. NULL emails stay legal and stay
--     repeatable — MySQL allows many NULLs in a unique index — so a member who
--     signs up with a mobile number only is unaffected.
--
--  Run after 002_seed_demo_data.sql. Both statements fail loudly on a database
--  that already holds duplicates: resolve those first, then re-run.
-- ============================================================================

USE mithra;

ALTER TABLE users
  ADD COLUMN phone_digits VARCHAR(9)
    AS (RIGHT(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), 9)) STORED
    AFTER phone;

ALTER TABLE users
  ADD UNIQUE KEY uk_users_email (email),
  ADD UNIQUE KEY uk_users_phone_digits (phone_digits);
