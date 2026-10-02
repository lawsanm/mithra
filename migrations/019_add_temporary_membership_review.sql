-- ============================================================================
--  MITHRA — 019 Temporary community review, renewal and expiry (Plan §6.5)
--
--  A temporary membership is applied for with proof, decided by that
--  division's moderator with a reason the member sees, lasts 6 months, and can
--  be extended with fresh proof while active or within the 14-day grace period.
--
--    decision_reason       the moderator's reason for a rejection
--    renewal_proof_path    fresh proof sent with an extension request
--    renewal_requested_at  set while an extension waits for the moderator
--    expiry_reminded_at    the 14-days-before reminder was sent (keeps the job idempotent)
-- ============================================================================

USE mithra;

ALTER TABLE user_divisions
  ADD COLUMN decision_reason      VARCHAR(255) NULL AFTER status,
  ADD COLUMN renewal_proof_path   VARCHAR(255) NULL AFTER decision_reason,
  ADD COLUMN renewal_requested_at DATETIME NULL AFTER renewal_proof_path,
  ADD COLUMN expiry_reminded_at   DATETIME NULL AFTER renewal_requested_at;
