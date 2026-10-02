-- ============================================================================
--  MITHRA — 024 Several evidence photos per damage claim (Plan 3.4, §10.3)
--
--  The claim form takes 1–5 photos of the damage; evidence_path held one.
--  evidence_photos holds the list. evidence_path stays for claims recorded
--  before this, and the photo proxy checks both.
-- ============================================================================

USE mithra;

ALTER TABLE damage_claims
  ADD COLUMN evidence_photos JSON NULL AFTER evidence_path,
  ADD KEY idx_dc_booking (booking_id, status);
