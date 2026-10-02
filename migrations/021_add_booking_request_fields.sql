-- ============================================================================
--  MITHRA — 021 What a booking request says, and why it ended (Plan §8, §18.3)
--
--    message         the borrower's note to the lender with the request
--    decline_reason  the lender's optional reason for declining
--    cancelled_by    who cancelled it — the borrower or the lender — so each
--                    side's history and the cancellation rating read right
-- ============================================================================

USE mithra;

ALTER TABLE bookings
  ADD COLUMN message        VARCHAR(255) NULL AFTER moderator_involved,
  ADD COLUMN decline_reason VARCHAR(255) NULL AFTER message,
  ADD COLUMN cancelled_by   INT UNSIGNED NULL AFTER decline_reason,
  ADD KEY idx_bk_requested (status, requested_at),
  ADD CONSTRAINT fk_bk_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL;
