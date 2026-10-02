-- ============================================================================
--  MITHRA — 023 Return notes and the overdue flag (Plan 3.3, §7.6, §10.2)
--
--    return_records.lender_notes / borrower_notes
--        each side's condition note at return, as at handover
--    return_records.decided_at
--        when the lender accepted the return or raised a claim
--    bookings.overdue_flagged_at
--        set when an unreturned item passes 72 hours late and the moderator
--        is told, so the overdue job tells them only once
-- ============================================================================

USE mithra;

ALTER TABLE return_records
  ADD COLUMN lender_notes   TEXT NULL AFTER borrower_photos,
  ADD COLUMN borrower_notes TEXT NULL AFTER lender_notes,
  ADD COLUMN decided_at     DATETIME NULL AFTER borrower_decision;

ALTER TABLE bookings
  ADD COLUMN overdue_flagged_at DATETIME NULL AFTER closed_at;
