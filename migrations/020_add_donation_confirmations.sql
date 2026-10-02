-- ============================================================================
--  MITHRA — 020 Both sides confirm a donation handover (Plan §13.1)
--
--  §13.1 step 4: the donor confirms the transfer and the recipient confirms
--  receipt; the donation completes once both have. One handover_at could not
--  record that, so each side gets its own timestamp. handover_at stays as the
--  moment the second confirmation completed it.
--
--  A donation listing approved before this module existed has no donations
--  row, so nobody could request it (I8). Those rows are opened here.
-- ============================================================================

USE mithra;

ALTER TABLE donations
  ADD COLUMN donor_confirmed_at     DATETIME NULL AFTER handover_at,
  ADD COLUMN recipient_confirmed_at DATETIME NULL AFTER donor_confirmed_at,
  ADD KEY idx_don_item (item_id, status);

INSERT INTO donations (item_id, donor_id, selection_mode, status)
SELECT i.id, i.owner_id, 'donor_chooses', 'open'
  FROM items i
 WHERE i.listing_type = 'donation'
   AND i.status IN ('active', 'paused')
   AND NOT EXISTS (SELECT 1 FROM donations d WHERE d.item_id = i.id);
