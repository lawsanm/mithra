-- ============================================================================
--  MITHRA — 027 What an aid request says, and the conversation about it
--  (Plan 4.4, §12)
--
--    details          the member's own account of the need
--    evidence_photos  up to five supporting photos (aid-evidence/)
--    info_request     the Sponsor Liaison's question when more is needed
--    member_reply     the member's answer to it
--    decision_reason  why a request was rejected or adjusted
--    updated_at       a request can be edited while it waits for the vouch
-- ============================================================================

USE mithra;

ALTER TABLE aid_grants
  ADD COLUMN details         TEXT NULL AFTER purpose,
  ADD COLUMN evidence_photos JSON NULL AFTER evidence_path,
  ADD COLUMN info_request    VARCHAR(255) NULL AFTER approved_amount,
  ADD COLUMN member_reply    TEXT NULL AFTER info_request,
  ADD COLUMN decision_reason VARCHAR(255) NULL AFTER member_reply,
  ADD COLUMN updated_at      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
