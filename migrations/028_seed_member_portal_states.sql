-- ============================================================================
--  MITHRA — 028 Demo records for the member portal's new states
--
--  One record in each state the walkthrough needs, so a fresh database shows
--  every booking step without clicking through it first:
--
--    Camping Tent      Tharshini borrows from Arun — accepted, handover half done
--    Extension Ladder  Nimal returned it to Arun yesterday — return to check
--    Baby Stroller     Lawsan returned it to Arun — a simple-path damage claim
--                      waits for Lawsan's answer
--    Kajan             asks to join Kollupitiya as a temporary member
--    Lawsan            a saved search and blocked dates on the Ladder (6 ft)
--
--  Points held for these bookings move exactly as the app would move them:
--  every ledger row is matched by the wallet and pool balances, so the
--  pools stay in step. Photos reuse the bundled demo catalog images.
--  Requested, in-progress, overdue, completed, pending-moderator, donation
--  and aid-grant states already come from migrations 002 and 016.
-- ============================================================================

USE mithra;

SET NAMES utf8mb4;

SET @arun      = (SELECT id FROM users WHERE email = 'arun@email.com');
SET @lawsan    = (SELECT id FROM users WHERE email = 'lawsanm@gmail.com');
SET @tharshini = (SELECT id FROM users WHERE email = 'tharshini@email.com');
SET @nimal     = (SELECT id FROM users WHERE email = 'nimal@email.com');
SET @kajan     = (SELECT id FROM users WHERE email = 'kajan@email.com');

SET @tent     = (SELECT id FROM items WHERE title = 'Camping Tent (4-person)' LIMIT 1);
SET @ladder   = (SELECT id FROM items WHERE title = 'Extension Ladder' LIMIT 1);
SET @stroller = (SELECT id FROM items WHERE title = 'Baby Stroller' LIMIT 1);
SET @ladder6  = (SELECT id FROM items WHERE title = 'Ladder (6 ft)' LIMIT 1);

SET @kollupitiya = (SELECT id FROM gn_divisions WHERE name = 'Kollupitiya' LIMIT 1);

-- ----------------------------------------------------------------------------
-- 1. Camping Tent: accepted, 3 days from the day after tomorrow, 18 pts/day.
--    54 pts charge + 18 pts buffer held in In-Flight. Arun has uploaded and
--    accepted his handover photos; Tharshini has not.
-- ----------------------------------------------------------------------------

INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, message, status, requested_at, accepted_at)
SELECT @tent, @tharshini, @arun, CURDATE() + INTERVAL 2 DAY, CURDATE() + INTERVAL 4 DAY, 'daily', 18,
       54, 18, 0, 'For a family trip to Horton Plains.', 'awaiting_handover', NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 2 HOUR
  FROM DUAL WHERE @tent IS NOT NULL AND @tharshini IS NOT NULL AND @arun IS NOT NULL;

SET @tent_booking = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT INTO point_ledger (from_user_id, to_pool_code, amount, reason, booking_id)
SELECT @tharshini, 'in_flight', 54, 'rental_charge', @tent_booking FROM DUAL WHERE @tent_booking IS NOT NULL
UNION ALL
SELECT @tharshini, 'in_flight', 18, 'buffer_hold', @tent_booking FROM DUAL WHERE @tent_booking IS NOT NULL;

UPDATE member_wallets SET balance = balance - 72 WHERE user_id = @tharshini AND @tent_booking IS NOT NULL;
UPDATE point_pools SET balance = balance - 72 WHERE pool_code = 'member_wallets' AND @tent_booking IS NOT NULL;
UPDATE point_pools SET balance = balance + 72 WHERE pool_code = 'in_flight' AND @tent_booking IS NOT NULL;

INSERT INTO handover_records (booking_id, lender_photos, lender_notes, lender_accepted_at)
SELECT @tent_booking, JSON_ARRAY('item-photos/demo/tent.jpg'), 'All poles and pegs packed; small mark on the flysheet.', NOW() - INTERVAL 1 HOUR
  FROM DUAL WHERE @tent_booking IS NOT NULL;

INSERT INTO notifications (user_id, type, payload)
SELECT @tharshini, 'booking_accepted', JSON_OBJECT('title', 'Your request was accepted', 'detail', 'View your booking for handover details.',
       'icon', 'check-circle', 'href', CONCAT('/bookings/', @tent_booking), 'booking_id', @tent_booking)
  FROM DUAL WHERE @tent_booking IS NOT NULL;

-- ----------------------------------------------------------------------------
-- 2. Extension Ladder: 5 days ending yesterday, 8 pts/day. Handed over,
--    charge paid out to Arun, buffer still held; Nimal uploaded return photos
--    yesterday and Arun has not decided yet.
-- ----------------------------------------------------------------------------

INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at)
SELECT @ladder, @nimal, @arun, CURDATE() - INTERVAL 5 DAY, CURDATE() - INTERVAL 1 DAY, 'daily', 8,
       40, 8, 0, 'awaiting_return', NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 6 DAY
  FROM DUAL WHERE @ladder IS NOT NULL AND @nimal IS NOT NULL AND @arun IS NOT NULL;

SET @ladder_booking = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT INTO point_ledger (from_user_id, from_pool_code, to_pool_code, to_user_id, amount, reason, booking_id)
SELECT @nimal, NULL, 'in_flight', NULL, 40, 'rental_charge', @ladder_booking FROM DUAL WHERE @ladder_booking IS NOT NULL
UNION ALL
SELECT @nimal, NULL, 'in_flight', NULL, 8, 'buffer_hold', @ladder_booking FROM DUAL WHERE @ladder_booking IS NOT NULL
UNION ALL
SELECT NULL, 'in_flight', NULL, @arun, 40, 'rental_payout', @ladder_booking FROM DUAL WHERE @ladder_booking IS NOT NULL;

UPDATE member_wallets SET balance = balance - 48 WHERE user_id = @nimal AND @ladder_booking IS NOT NULL;
UPDATE member_wallets SET balance = balance + 40 WHERE user_id = @arun AND @ladder_booking IS NOT NULL;
UPDATE point_pools SET balance = balance - 8 WHERE pool_code = 'member_wallets' AND @ladder_booking IS NOT NULL;
UPDATE point_pools SET balance = balance + 8 WHERE pool_code = 'in_flight' AND @ladder_booking IS NOT NULL;

INSERT INTO handover_records (booking_id, handover_at, lender_photos, borrower_photos, lender_notes, borrower_notes,
                              lender_accepted_at, borrower_accepted_at)
SELECT @ladder_booking, NOW() - INTERVAL 5 DAY, JSON_ARRAY('item-photos/demo/extension-ladder.jpg'),
       JSON_ARRAY('item-photos/demo/extension-ladder.jpg'), 'Locks work; rubber feet worn.', 'Agreed — feet are worn.',
       NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY
  FROM DUAL WHERE @ladder_booking IS NOT NULL;

INSERT INTO return_records (booking_id, return_at, borrower_photos, borrower_notes)
SELECT @ladder_booking, NOW() - INTERVAL 20 HOUR, JSON_ARRAY('item-photos/demo/extension-ladder.jpg'), 'Returned clean, same condition.'
  FROM DUAL WHERE @ladder_booking IS NOT NULL;

UPDATE items SET status = 'borrowed' WHERE id = @ladder AND @ladder_booking IS NOT NULL;

-- ----------------------------------------------------------------------------
-- 3. Baby Stroller: Lawsan returned it two days ago; Arun raised a minor
--    claim of 30 pts (within 20% of the 220-pt declared value), so it waits
--    for Lawsan to accept or contest. Buffer still held.
-- ----------------------------------------------------------------------------

INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at)
SELECT @stroller, @lawsan, @arun, CURDATE() - INTERVAL 6 DAY, CURDATE() - INTERVAL 3 DAY, 'daily', 10,
       40, 10, 0, 'awaiting_return', NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 7 DAY
  FROM DUAL WHERE @stroller IS NOT NULL AND @lawsan IS NOT NULL AND @arun IS NOT NULL;

SET @stroller_booking = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT INTO point_ledger (from_user_id, from_pool_code, to_pool_code, to_user_id, amount, reason, booking_id)
SELECT @lawsan, NULL, 'in_flight', NULL, 40, 'rental_charge', @stroller_booking FROM DUAL WHERE @stroller_booking IS NOT NULL
UNION ALL
SELECT @lawsan, NULL, 'in_flight', NULL, 10, 'buffer_hold', @stroller_booking FROM DUAL WHERE @stroller_booking IS NOT NULL
UNION ALL
SELECT NULL, 'in_flight', NULL, @arun, 40, 'rental_payout', @stroller_booking FROM DUAL WHERE @stroller_booking IS NOT NULL;

UPDATE member_wallets SET balance = balance - 50 WHERE user_id = @lawsan AND @stroller_booking IS NOT NULL;
UPDATE member_wallets SET balance = balance + 40 WHERE user_id = @arun AND @stroller_booking IS NOT NULL;
UPDATE point_pools SET balance = balance - 10 WHERE pool_code = 'member_wallets' AND @stroller_booking IS NOT NULL;
UPDATE point_pools SET balance = balance + 10 WHERE pool_code = 'in_flight' AND @stroller_booking IS NOT NULL;

INSERT INTO handover_records (booking_id, handover_at, lender_photos, borrower_photos, lender_accepted_at, borrower_accepted_at)
SELECT @stroller_booking, NOW() - INTERVAL 6 DAY, JSON_ARRAY('item-photos/demo/stroller.jpg'), JSON_ARRAY('item-photos/demo/stroller.jpg'),
       NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 6 DAY
  FROM DUAL WHERE @stroller_booking IS NOT NULL;

INSERT INTO return_records (booking_id, return_at, lender_photos, borrower_photos, lender_decision, decided_at)
SELECT @stroller_booking, NOW() - INTERVAL 2 DAY, JSON_ARRAY('item-photos/demo/stroller.jpg'), JSON_ARRAY('item-photos/demo/stroller.jpg'),
       'claim_raised', NOW() - INTERVAL 30 HOUR
  FROM DUAL WHERE @stroller_booking IS NOT NULL;

INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, evidence_photos, track, status, created_at)
SELECT @stroller_booking, @arun, 'minor', 'The canopy clip is cracked — it was whole at handover.', 30,
       JSON_ARRAY('item-photos/demo/stroller.jpg'), 'simple', 'awaiting_borrower', NOW() - INTERVAL 30 HOUR
  FROM DUAL WHERE @stroller_booking IS NOT NULL;

UPDATE items SET status = 'borrowed' WHERE id = @stroller AND @stroller_booking IS NOT NULL;

INSERT INTO notifications (user_id, type, payload)
SELECT @lawsan, 'claim_raised', JSON_OBJECT('title', 'Damage claim on Baby Stroller', 'detail', 'The lender asks for 30 pts. Accept or contest within 48 hours.',
       'icon', 'alert-triangle', 'href', CONCAT('/bookings/', @stroller_booking, '#claim'), 'booking_id', @stroller_booking)
  FROM DUAL WHERE @stroller_booking IS NOT NULL;

-- ----------------------------------------------------------------------------
-- 4. Kajan (home: Wellawatte) asks to join Kollupitiya for a work posting.
-- ----------------------------------------------------------------------------

INSERT INTO user_divisions (user_id, gn_division_id, membership_type, proof_type, status, created_at)
SELECT @kajan, @kollupitiya, 'temporary', 'employer_letter', 'pending', NOW() - INTERVAL 1 DAY
  FROM DUAL
 WHERE @kajan IS NOT NULL AND @kollupitiya IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM user_divisions WHERE user_id = @kajan AND gn_division_id = @kollupitiya);

-- ----------------------------------------------------------------------------
-- 5. Lawsan's saved search, and dates blocked on the Ladder (6 ft).
-- ----------------------------------------------------------------------------

INSERT INTO saved_searches (user_id, name, filters)
SELECT @lawsan, 'Tools near me', JSON_OBJECT('category', 'tools-hardware') FROM DUAL WHERE @lawsan IS NOT NULL;

INSERT INTO item_availability_blocks (item_id, start_date, end_date, note)
SELECT @ladder6, CURDATE() + INTERVAL 10 DAY, CURDATE() + INTERVAL 12 DAY, 'Painting the house'
  FROM DUAL WHERE @ladder6 IS NOT NULL;
